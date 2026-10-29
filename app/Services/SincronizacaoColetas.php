<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Configuracao;
use App\Models\Dispositivo;
use App\Models\FotoAmostra;
use App\Models\Limite;
use App\Models\Parametro;
use App\Models\RegistroAuditoria;
use App\Models\Sincronizacao;
use App\Models\Usuario;
use App\Support\Numero;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// MOD10 – UC22 Sincronizar registros do celular (RF39, RF41, RN38, RN40–RN42) e UC23 Resolver conflito (RF42, RN43).
class SincronizacaoColetas
{
    public const ENTIDADE = 'SINCRONIZACAO';

    public function __construct(
        private readonly RegistroColetas $registro,
        private readonly Roteirizacao $roteiro,
        private readonly ResultadosAmostras $resultados,
        private readonly AgendamentoColetas $agendamento,
        private readonly Auditoria $auditoria,
    ) {}

    /**
     * RF39 / RN38: o que o aparelho guarda – só as coletas Agendadas do próprio coletor para hoje e amanhã, na ordem do
     * roteiro, sem valores do orçamento.
     *
     * @return array<string, mixed>
     */
    public function dadosDoAparelho(Usuario $coletor): array
    {
        $config = Configuracao::atual();
        $dias = [];
        foreach ([today(), today()->addDay()] as $dia) {
            $coletas = $this->roteiro->coletasDoDia($coletor->id, $dia->toDateString(), somenteAgendadas: true);
            $distancias = $this->roteiro->distancias($coletas);
            $links = $this->roteiro->links($coletas);
            $dias[] = [
                'data' => $dia->toDateString(),
                'links' => $links,
                'coletas' => $coletas->map(fn (Coleta $c) => $this->coletaParaAparelho($c, $distancias[$c->id] ?? null))->all(),
            ];
        }

        return [
            'gerado_em' => now()->toIso8601String(),
            'usuario' => ['id' => $coletor->id, 'nome' => $coletor->nome],
            'laboratorio' => [
                'nome' => $config->dadosLaboratorio()['nome'],
                'lat' => $config->laboratorio_latitude,
                'lng' => $config->laboratorio_longitude,
            ],
            'motivos' => Coleta::MOTIVOS_NAO_COLETA,
            'distancia_maxima' => Coleta::DISTANCIA_MAXIMA_METROS,
            'dias' => $dias,
        ];
    }

    /**
     * UC22: aplica o registro enviado pelo celular. O mesmo identificador é aceito uma única vez (RN40); se a coleta
     * não está mais Agendada para o coletor, o registro vira conflito e a coleta não muda (RN42).
     *
     * @param  array<string, mixed>  $dados  validados pelo SincronizacaoRequest
     * @return array{uuid: string, situacao: string, mensagem: string, coleta: array{id: int, situacao: string}}
     */
    public function sincronizar(Usuario $usuario, ?Dispositivo $dispositivo, array $dados): array
    {
        return DB::transaction(function () use ($usuario, $dispositivo, $dados) {
            $anterior = Sincronizacao::where('uuid', $dados['uuid'])->lockForUpdate()->first();
            if ($anterior) {
                if ($anterior->usuario_id !== $usuario->id) {
                    throw ValidationException::withMessages(['uuid' => 'Identificador de registro já usado por outro usuário.']);
                }

                return $anterior->resposta; // RN40: devolve o resultado do primeiro envio
            }

            $coleta = Coleta::with('amostras.pontoColeta')->lockForUpdate()->find($dados['coleta_id'])
                ?? throw ValidationException::withMessages(['coleta_id' => 'Coleta não encontrada.']);
            $fotos = $this->separarFotos($dados);

            $sincronizacao = new Sincronizacao;
            $sincronizacao->forceFill([
                'uuid' => $dados['uuid'], 'coleta_id' => $coleta->id, 'usuario_id' => $usuario->id,
                'dispositivo_id' => $dispositivo?->id, 'origem' => $dados['origem'], 'dados' => $dados,
                'recebida_em' => now(),
            ]);

            $conflito = $this->motivoConflito($coleta, $usuario, $dados);
            if ($conflito) {
                $sincronizacao->forceFill(['situacao' => Sincronizacao::CONFLITO, 'motivo_conflito' => $conflito])->save();
                $mensagem = "Enviado com conflito: {$conflito} A Recepção vai decidir o que fazer com este registro.";
            } else {
                $this->aplicar($coleta, $dados, $usuario->id, $sincronizacao->recebida_em);
                $sincronizacao->forceFill(['situacao' => Sincronizacao::APLICADA])->save();
                $mensagem = $dados['acao'] === Coleta::NAO_REALIZADA ? 'Coleta registrada como Não realizada.' : 'Coleta registrada como Realizada.';
            }
            $this->guardarFotos($sincronizacao, $coleta, $fotos);

            $resposta = [
                'uuid' => $sincronizacao->uuid,
                'situacao' => $sincronizacao->situacao,
                'mensagem' => $mensagem,
                'coleta' => ['id' => $coleta->id, 'situacao' => $coleta->fresh()->situacao],
            ];
            $sincronizacao->forceFill(['resposta' => $resposta])->save();

            return $resposta;
        });
    }

    /** RN36: o coletor só envia registros de coletas que são ou já foram dele (pelo histórico da coleta). */
    public function coletaJaFoiDoColetor(Coleta $coleta, Usuario $usuario): bool
    {
        return $coleta->coletor_id === $usuario->id
            || RegistroAuditoria::where(['entidade' => Rastreabilidade::COLETA, 'registro_id' => $coleta->id, 'campo' => 'coletor'])
                ->where(fn ($q) => $q->where('valor_anterior', $usuario->nome)->orWhere('valor_novo', $usuario->nome))
                ->exists();
    }

    /**
     * RN42: a coleta mudou enquanto o coletor estava sem conexão? Devolve o motivo do conflito, ou null.
     *
     * @param  array<string, mixed>  $dados
     */
    public function motivoConflito(Coleta $coleta, Usuario $usuario, array $dados): ?string
    {
        return match (true) {
            $coleta->situacao === Coleta::CANCELADA => 'a coleta foi cancelada.',
            $coleta->situacao !== Coleta::AGENDADA => 'a coleta já foi registrada por outro meio.',
            $coleta->coletor_id !== $usuario->id => 'a coleta foi passada a outro coletor.',
            isset($dados['agendada_para']) && $coleta->data->toDateString() !== $dados['agendada_para'] => 'a coleta foi reagendada para '.$coleta->data->format('d/m/Y').'.',
            default => null,
        };
    }

    /**
     * UC23 4: aceitar aplica o registro à coleta como registrada pelo coletor (RN43). A coleta cancelada, não realizada
     * ou reagendada volta a ser do coletor, na data da coleta, com as amostras do registro de novo previstas (RN28).
     */
    public function aceitar(Sincronizacao $sincronizacao): void
    {
        DB::transaction(function () use ($sincronizacao) {
            $sincronizacao = $this->conflitoPendente($sincronizacao);
            $coleta = Coleta::with(['amostras.itens', 'orcamento'])->lockForUpdate()->findOrFail($sincronizacao->coleta_id);
            $dados = $sincronizacao->dados;

            if ($coleta->situacao === Coleta::REALIZADA) {
                throw ValidationException::withMessages(['sincronizacao' => 'A coleta já foi registrada e as amostras seguiram para o laboratório: descarte este registro.']);
            }

            $ids = array_map('intval', array_column($dados['amostras'] ?? [], 'id'));
            $voltam = $coleta->amostras->whereIn('id', $ids)->whereIn('situacao', [Amostra::CANCELADA, Amostra::NAO_COLETADA]);
            $this->agendamento->reabrirParaRegistro($coleta, $voltam, $sincronizacao->usuario_id,
                Carbon::parse($dados['data_hora_coleta'])->toDateString(), 'Registro do celular aceito (conflito de sincronização).');

            $this->aplicar($coleta->fresh(), $dados, $sincronizacao->usuario_id, $sincronizacao->recebida_em);
            $this->resolver($sincronizacao, Sincronizacao::ACEITA, null);
        });
    }

    /** UC23 4a: descartar exige motivo; a coleta não muda e o registro fica guardado (RN43). */
    public function descartar(Sincronizacao $sincronizacao, string $motivo): void
    {
        DB::transaction(function () use ($sincronizacao, $motivo) {
            $this->resolver($this->conflitoPendente($sincronizacao), Sincronizacao::DESCARTADA, $motivo);
        });
    }

    /** @param  array<string, mixed>  $dados */
    private function aplicar(Coleta $coleta, array $dados, int $responsavelId, Carbon $recebidaEm): void
    {
        $comum = [
            'data_hora_coleta' => Carbon::parse($dados['data_hora_coleta'])->format('Y-m-d H:i:s'),
            'condicoes_tempo' => $dados['condicoes_tempo'] ?? null,
            'origem_registro' => $dados['origem'],
            'registro_recebido_em' => $recebidaEm,
            'latitude' => $dados['latitude'] ?? null,
            'longitude' => $dados['longitude'] ?? null,
        ];

        if ($dados['acao'] === Coleta::NAO_REALIZADA) {
            $this->registro->registrarNaoRealizada($coleta, [...$comum,
                'motivo_nao_realizada' => $dados['motivo_nao_realizada'],
                'motivo_nao_realizada_detalhe' => $dados['motivo_nao_realizada_detalhe'] ?? null,
            ], $responsavelId);

            return;
        }

        $amostras = collect($dados['amostras'] ?? [])->mapWithKeys(fn (array $a) => [(int) $a['id'] => [
            'situacao' => $a['situacao'],
            'temperatura' => Numero::ler($a['temperatura'] ?? null),
            'observacoes' => $a['observacoes'] ?? null,
            'motivo' => $a['motivo'] ?? null,
            'medicoes' => collect($a['medicoes'] ?? [])->map(fn ($v) => Numero::ler($v))->all(),
        ]])->all();

        $this->registro->registrar($coleta, [...$comum, 'amostras' => $amostras], $responsavelId);
    }

    private function conflitoPendente(Sincronizacao $sincronizacao): Sincronizacao
    {
        $sincronizacao = Sincronizacao::lockForUpdate()->findOrFail($sincronizacao->id);
        if ($sincronizacao->situacao !== Sincronizacao::CONFLITO) {
            throw ValidationException::withMessages(['sincronizacao' => 'Este conflito já foi resolvido.']);
        }

        return $sincronizacao;
    }

    private function resolver(Sincronizacao $sincronizacao, string $situacao, ?string $motivo): void
    {
        $antes = ['situacao' => $sincronizacao->nomeSituacao(), 'motivo_descarte' => null];
        $sincronizacao->forceFill([
            'situacao' => $situacao, 'motivo_descarte' => $motivo, 'resolvida_por_id' => Auth::id(), 'resolvida_em' => now(),
        ])->save();
        $this->auditoria->registrar(self::ENTIDADE, $sincronizacao->id, Auditoria::ALTERACAO, $antes,
            ['situacao' => $sincronizacao->nomeSituacao(), 'motivo_descarte' => $motivo], ['situacao', 'motivo_descarte']);
    }

    /** @return array<string, mixed> */
    private function coletaParaAparelho(Coleta $coleta, ?int $distancia): array
    {
        $ponto = $coleta->pontoNoMapa();

        return [
            'id' => $coleta->id,
            'agendada_para' => $coleta->data->toDateString(),
            'periodo' => $coleta->nomePeriodo(),
            'horario' => $coleta->horarioFormatado(),
            'cliente' => $coleta->orcamento->cliente->nome,
            'instrucoes' => $coleta->instrucoes,
            'lat' => $ponto?->latitude,
            'lng' => $ponto?->longitude,
            'distancia_metros' => $distancia,
            'amostras' => $coleta->amostras->where('situacao', Amostra::PREVISTA)->values()->map(fn (Amostra $a) => [
                'id' => $a->id,
                'codigo' => $a->codigo,
                'itens' => $a->descricaoItens(),
                'ponto' => [
                    'identificacao' => $a->pontoColeta->identificacao,
                    'endereco' => $a->pontoColeta->enderecoCompleto(),
                    'referencia' => $a->pontoColeta->referencia,
                    'lat' => $a->pontoColeta->latitude,
                    'lng' => $a->pontoColeta->longitude,
                ],
                'parametros_campo' => $a->parametrosDeCampo()->map(fn (Parametro $p) => $this->parametroDeCampo($a, $p))->values()->all(),
            ])->all(),
        ];
    }

    /** UC21 3c: o limite vai junto para o aparelho destacar a medição fora da faixa. */
    private function parametroDeCampo(Amostra $amostra, Parametro $parametro): array
    {
        $legislacoes = $this->resultados->legislacoesAplicaveis($amostra);
        /** @var Limite|null $limite */
        $limite = $legislacoes->count() === 1 ? $this->resultados->limite($legislacoes->first(), $amostra, $parametro) : null;

        return [
            'id' => $parametro->id,
            'nome' => $parametro->nome,
            'unidade' => $parametro->unidade,
            'limite' => $limite ? ['tipo' => $limite->tipo, 'min' => $limite->valor_minimo === null ? null : (float) $limite->valor_minimo,
                'max' => $limite->valor_maximo === null ? null : (float) $limite->valor_maximo, 'descricao' => $limite->descricao()] : null,
        ];
    }

    /**
     * Tira as fotos dos dados guardados no registro (ficam em arquivo) e confere o limite de RN45.
     *
     * @param  array<string, mixed>  $dados
     * @return array<int, list<string>> amostra => conteúdos das fotos
     */
    private function separarFotos(array &$dados): array
    {
        $fotos = [];
        foreach ($dados['amostras'] ?? [] as $i => $amostra) {
            $lista = [];
            foreach (array_slice($amostra['fotos'] ?? [], 0, FotoAmostra::MAXIMO_POR_AMOSTRA) as $foto) {
                $conteudo = base64_decode(preg_replace('#^data:image/[a-z]+;base64,#', '', (string) $foto), true);
                if ($conteudo === false || strlen($conteudo) > FotoAmostra::TAMANHO_MAXIMO || ! @getimagesizefromstring($conteudo)) {
                    throw ValidationException::withMessages(["amostras.$i.fotos" => 'Foto inválida ou maior que 1 MB.']);
                }
                $lista[] = $conteudo;
            }
            $fotos[(int) $amostra['id']] = $lista;
            $dados['amostras'][$i]['fotos'] = count($lista);
        }

        return $fotos;
    }

    /** @param  array<int, list<string>>  $fotos */
    private function guardarFotos(Sincronizacao $sincronizacao, Coleta $coleta, array $fotos): void
    {
        $amostras = $coleta->amostras->keyBy('id');
        foreach ($fotos as $amostraId => $lista) {
            $amostra = $amostras->get($amostraId);
            foreach ($amostra ? $lista : [] as $n => $conteudo) {
                $caminho = "fotos/{$amostra->codigo}/".Str::lower($sincronizacao->uuid).'-'.($n + 1).'.jpg';
                Storage::disk('local')->put($caminho, $conteudo);
                $foto = new FotoAmostra;
                $foto->forceFill(['amostra_id' => $amostra->id, 'sincronizacao_id' => $sincronizacao->id,
                    'caminho' => $caminho, 'tamanho' => strlen($conteudo)])->save();
            }
        }
    }
}
