<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Coleta;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\PontoColeta;
use App\Models\Usuario;
use App\Support\Perfis;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// UC14 – Registrar agendamento de coleta (RF27–RF29, RN27–RN31), com histórico (RN08, RNF12).
class AgendamentoColetas
{
    public function __construct(private readonly Rastreabilidade $rastreio) {}

    /**
     * RF28: saldo de amostras de cada item do orçamento.
     *
     * @return Collection<int, array{item: OrcamentoItem, aprovadas: int, agendadas: int, coletadas: int, recebidas: int, saldo: int}>
     */
    public function saldo(Orcamento $orcamento): Collection
    {
        $contagens = DB::table('amostra_itens')
            ->join('amostras', 'amostras.id', '=', 'amostra_itens.amostra_id')
            ->join('orcamento_itens', 'orcamento_itens.id', '=', 'amostra_itens.orcamento_item_id')
            ->where('orcamento_itens.orcamento_id', $orcamento->id)
            ->whereIn('amostras.situacao', Amostra::OCUPAM_SALDO)
            ->groupBy('amostra_itens.orcamento_item_id', 'amostras.situacao')
            ->select('amostra_itens.orcamento_item_id', 'amostras.situacao', DB::raw('COUNT(*) as total'))
            ->get()
            ->groupBy('orcamento_item_id');

        return $orcamento->itens()->get()->mapWithKeys(function (OrcamentoItem $item) use ($contagens) {
            $porSituacao = collect($contagens->get($item->id))->pluck('total', 'situacao')->map(fn ($n) => (int) $n);
            $agendadas = $porSituacao->get(Amostra::PREVISTA, 0);
            $coletadas = $porSituacao->get(Amostra::COLETADA, 0);
            $recebidas = $porSituacao->get(Amostra::RECEBIDA, 0);

            return [$item->id => [
                'item' => $item,
                'aprovadas' => $item->quantidade,
                'agendadas' => $agendadas,
                'coletadas' => $coletadas,
                'recebidas' => $recebidas,
                'saldo' => $item->quantidade - $agendadas - $coletadas - $recebidas,
            ]];
        });
    }

    /**
     * RN27: pontos ativos do cliente do orçamento com o mesmo tipo de amostra.
     *
     * @return EloquentCollection<int, PontoColeta>
     */
    public function pontosPermitidos(Orcamento $orcamento): EloquentCollection
    {
        return PontoColeta::where(['cliente_id' => $orcamento->cliente_id, 'tipo_amostra_id' => $orcamento->tipo_amostra_id, 'ativo' => true])
            ->orderByNome('identificacao')->get();
    }

    /**
     * RN29: coletores ativos (mais o atual, para não sumir da lista ao reagendar).
     *
     * @return EloquentCollection<int, Usuario>
     */
    public function coletores(?int $atual = null): EloquentCollection
    {
        return Usuario::query()
            ->where(fn ($q) => $q->ativosComPerfil(Perfis::COLETOR)->when($atual, fn ($q) => $q->orWhere('id', $atual)))
            ->orderByNome()->get();
    }

    /** RN29: quantas coletas agendadas o coletor já tem no mesmo dia e período. */
    public function coletasDoColetorNoPeriodo(int $coletorId, string $data, string $periodo, ?int $ignorar = null): int
    {
        return Coleta::where(['coletor_id' => $coletorId, 'periodo' => $periodo, 'situacao' => Coleta::AGENDADA])
            ->whereDate('data', $data)
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))
            ->count();
    }

    /**
     * Agenda a coleta, gera os códigos das amostras (RN30) e grava a coleta como Agendada.
     *
     * @param  array{coletor_id: int, data: string, periodo: string, horario?: string|null, instrucoes?: string|null,
     *               amostras: list<array{ponto_coleta_id: int, itens: list<int>}>}  $dados  validados pelo AgendamentoRequest
     *
     * @throws ValidationException orçamento não aprovado, ponto não permitido ou saldo insuficiente
     */
    public function agendar(Orcamento $orcamento, array $dados): Coleta
    {
        return DB::transaction(function () use ($orcamento, $dados) {
            Orcamento::whereKey($orcamento->id)->lockForUpdate()->first(); // um agendamento por vez no mesmo orçamento
            $orcamento->refresh();
            if (! $orcamento->permiteAgendarColeta()) {
                throw ValidationException::withMessages(['orcamento' => 'Só é possível agendar coleta de orçamento Aprovado.']);
            }
            $this->validarPontos($orcamento, $dados['amostras']);
            $this->validarSaldo($orcamento, collect($dados['amostras'])->pluck('itens'));

            $coleta = new Coleta(collect($dados)->only(['coletor_id', 'data', 'periodo', 'horario', 'instrucoes'])->all());
            $coleta->orcamento_id = $orcamento->id;
            $coleta->criado_por_id = Auth::id();
            $coleta->save();
            $this->rastreio->registrarColeta($coleta, Auditoria::INCLUSAO, []);

            foreach ($dados['amostras'] as $linha) {
                $amostra = new Amostra;
                $amostra->ponto_coleta_id = $linha['ponto_coleta_id'];
                $amostra->coleta_id = $coleta->id;
                $this->numerar($amostra);
                $amostra->save();
                $amostra->itens()->sync($linha['itens']);
                $this->rastreio->registrarAmostra($amostra, Auditoria::INCLUSAO, []);
            }

            return $coleta;
        });
    }

    /**
     * UC14 1a / RN31: nova data, período ou coletor, com motivo. A coleta Não realizada volta a Agendada e as amostras
     * voltam a Previstas, com os mesmos códigos, se o orçamento ainda tiver saldo.
     *
     * @param  array{coletor_id: int, data: string, periodo: string, horario?: string|null, instrucoes?: string|null, motivo: string}  $dados
     */
    public function reagendar(Coleta $coleta, array $dados): void
    {
        DB::transaction(function () use ($coleta, $dados) {
            if (! $coleta->podeReagendar()) {
                throw ValidationException::withMessages(['coleta' => 'Só a coleta Agendada ou Não realizada pode ser reagendada.']);
            }
            $voltam = $coleta->situacao === Coleta::NAO_REALIZADA
                ? $coleta->amostras()->with('itens')->where('situacao', Amostra::NAO_COLETADA)->get()
                : collect();
            if ($voltam->isNotEmpty()) {
                $this->validarSaldo($coleta->orcamento, $voltam->map(fn (Amostra $a) => $a->itens->pluck('id')->all()));
            }

            $antes = $this->rastreio->valoresColeta($coleta);
            $coleta->fill(collect($dados)->only(['coletor_id', 'data', 'periodo', 'horario', 'instrucoes'])->all());
            $coleta->forceFill(['situacao' => Coleta::AGENDADA, 'motivo' => $dados['motivo'],
                'data_hora_coleta' => null, 'condicoes_tempo' => null, 'motivo_nao_realizada' => null,
                'motivo_nao_realizada_detalhe' => null, 'registrada_por_id' => null])->save();
            $coleta->unsetRelation('coletor');
            $this->rastreio->registrarColeta($coleta, Auditoria::ALTERACAO, $antes);

            foreach ($voltam as $amostra) {
                $this->mudarAmostra($amostra, Amostra::PREVISTA, ['motivo_nao_coleta' => null, 'observacoes_coleta' => null,
                    'coletada_por_id' => null]);
            }
        });
    }

    /** UC14 1b / RN31: cancela a coleta Agendada e as amostras previstas, devolvendo o saldo (RN28). */
    public function cancelar(Coleta $coleta, string $motivo): void
    {
        DB::transaction(function () use ($coleta, $motivo) {
            if (! $coleta->podeCancelar()) {
                throw ValidationException::withMessages(['coleta' => 'Só a coleta Agendada pode ser cancelada.']);
            }
            $antes = $this->rastreio->valoresColeta($coleta);
            $coleta->forceFill(['situacao' => Coleta::CANCELADA, 'motivo' => $motivo])->save();
            $this->rastreio->registrarColeta($coleta, Auditoria::ALTERACAO, $antes);

            $coleta->amostras()->where('situacao', Amostra::PREVISTA)->get()
                ->each(fn (Amostra $a) => $this->mudarAmostra($a, Amostra::CANCELADA));
        });
    }

    /** Muda a situação da amostra (e outros campos) registrando no histórico. */
    public function mudarAmostra(Amostra $amostra, string $situacao, array $campos = []): void
    {
        $antes = $this->rastreio->valoresAmostra($amostra);
        $amostra->forceFill([...$campos, 'situacao' => $situacao])->save();
        $this->rastreio->registrarAmostra($amostra, Auditoria::ALTERACAO, $antes);
    }

    /**
     * RN28: quantas amostras de cada item passam do saldo, considerando as que se quer agendar.
     *
     * @param  array<int, int>  $saldos  item => saldo disponível
     * @param  iterable<list<int>>  $amostras  itens de cada amostra
     * @return array<int, int> item => quantidade pedida, só dos itens que passam do saldo
     */
    public static function excedentes(array $saldos, iterable $amostras): array
    {
        $pedidos = [];
        foreach ($amostras as $itens) {
            foreach (array_unique($itens) as $item) {
                $pedidos[$item] = ($pedidos[$item] ?? 0) + 1;
            }
        }

        return array_filter($pedidos, fn ($quantidade, $item) => $quantidade > ($saldos[$item] ?? 0), ARRAY_FILTER_USE_BOTH);
    }

    /** @param  iterable<list<int>>  $amostras */
    private function validarSaldo(Orcamento $orcamento, iterable $amostras): void
    {
        $saldo = $this->saldo($orcamento);
        $excedentes = self::excedentes($saldo->map(fn ($s) => $s['saldo'])->all(), $amostras);
        if ($excedentes === []) {
            return;
        }
        $mensagens = collect($excedentes)->map(function ($pedidas, $itemId) use ($saldo) {
            $linha = $saldo->get($itemId);
            if (! $linha) {
                return 'Item que não pertence a este orçamento.';
            }
            $restam = max(0, $linha['saldo']);

            return "{$linha['item']->descricao}: ".($restam === 1 ? 'só resta 1 amostra' : "só restam {$restam} amostras")
                ." no orçamento e foram pedidas {$pedidas}.";
        })->values()->all();

        throw ValidationException::withMessages(['amostras' => $mensagens]);
    }

    /** @param  list<array{ponto_coleta_id: int}>  $amostras */
    private function validarPontos(Orcamento $orcamento, array $amostras): void
    {
        $permitidos = $this->pontosPermitidos($orcamento)->modelKeys();
        foreach ($amostras as $i => $linha) {
            if (! in_array((int) $linha['ponto_coleta_id'], $permitidos, true)) {
                throw ValidationException::withMessages(["amostras.$i.ponto_coleta_id" => 'O ponto de coleta deve estar ativo, ser do cliente do orçamento e ter o mesmo tipo de amostra.']);
            }
        }
    }

    /** RN30: próximo código do ano, ex.: AM-2026-00001. */
    private function numerar(Amostra $amostra): void
    {
        $amostra->ano = (int) now()->format('Y');
        $amostra->sequencia = (int) Amostra::where('ano', $amostra->ano)
            ->orderByDesc('sequencia')->lockForUpdate()->value('sequencia') + 1;
        $amostra->codigo = sprintf('AM-%d-%05d', $amostra->ano, $amostra->sequencia);
    }
}
