<?php

namespace App\Services;

use App\Mail\LaudoMail;
use App\Models\Amostra;
use App\Models\Configuracao;
use App\Models\Laudo;
use App\Support\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// MOD13 – UC26 Emitir e enviar laudo (RF52, RF53, RN54, RN56, RNF17) e UC27 Retificar laudo (RF54, RN55).
class EmissaoLaudos
{
    public const ENTIDADE = 'LAUDO';

    /** Sem letras e números parecidos (0/O, 1/I), para o código ser digitado na verificação. */
    private const ALFABETO_CODIGO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly AgendamentoColetas $agendamento,
        private readonly Auditoria $auditoria,
    ) {}

    /** UC26 3: pré-visualização do laudo, sem número nem código (nada é gravado). */
    public function previa(Amostra $amostra): string
    {
        $laudo = new Laudo;
        $laudo->forceFill(['revisao' => ($amostra->laudoVigente?->revisao ?? 0) + 1, 'ano' => (int) now()->format('Y'), 'sequencia' => 0, 'codigo_verificacao' => 'PREVIA', 'emitido_em' => now(),
            'conclusao' => $amostra->conformidade]);

        return $this->pdf($laudo, $amostra, previa: true);
    }

    /**
     * UC26 4–5: emite o laudo de cada amostra Liberada (RN54). Na retificação, o novo laudo mantém o número com a
     * revisão seguinte e o anterior fica Substituído (RN55).
     *
     * @param  Collection<int, Amostra>  $amostras
     * @return Collection<int, Laudo>
     */
    public function emitir(Collection $amostras): Collection
    {
        $config = Configuracao::atual();
        if (! $config->laudoConfigurado()) {
            throw ValidationException::withMessages(['amostras' => 'Configure o responsável técnico e o registro profissional em Configurações antes de emitir laudos.']);
        }

        return DB::transaction(fn () => $amostras->map(function (Amostra $amostra) {
            $amostra = Amostra::with(['laudoVigente'])->lockForUpdate()->findOrFail($amostra->id);
            if ($amostra->situacao !== Amostra::LIBERADA) {
                throw ValidationException::withMessages(['amostras' => "A amostra {$amostra->codigo} não está com os resultados liberados: o laudo não pode ser emitido."]);
            }

            $anterior = $amostra->laudoVigente;
            $laudo = new Laudo;
            $laudo->forceFill([
                'amostra_id' => $amostra->id,
                'codigo_verificacao' => $this->novoCodigo(),
                'conclusao' => $amostra->conformidade,
                'emitido_por_id' => Auth::id(),
                'emitido_em' => now(),
                'pdf_caminho' => '',
                'pdf_hash' => '',
            ]);
            if ($anterior) {
                $laudo->forceFill(['ano' => $anterior->ano, 'sequencia' => $anterior->sequencia, 'revisao' => $anterior->revisao + 1,
                    'substitui_id' => $anterior->id, 'motivo_retificacao' => $amostra->motivo_retificacao]);
            } else {
                $this->numerar($laudo);
            }
            $laudo->setRelation('substitui', $anterior);

            // RNF17: o PDF é guardado com o seu hash; o laudo emitido não é gerado de novo.
            $conteudo = $this->pdf($laudo, $amostra);
            $laudo->pdf_caminho = 'laudos/'.$laudo->nomeArquivo();
            $laudo->pdf_hash = hash('sha256', $conteudo);
            Storage::disk('local')->put($laudo->pdf_caminho, $conteudo);
            $laudo->save();

            if ($anterior) {
                $antes = $this->valores($anterior);
                $anterior->forceFill(['situacao' => Laudo::SUBSTITUIDO])->save();
                $this->auditar($anterior, $antes);
            }
            $this->auditar($laudo, []);
            $this->agendamento->mudarAmostra($amostra, Amostra::LAUDO_EMITIDO, ['motivo_retificacao' => null]);

            return $laudo;
        }));
    }

    /**
     * RF53 / RN56: envia ao contato principal do cliente, num e-mail por coleta. Devolve os avisos para o usuário
     * (contato sem e-mail ou falha no envio); o laudo continua emitido.
     *
     * @param  Collection<int, Laudo>  $laudos
     * @return list<string>
     */
    public function enviar(Collection $laudos): array
    {
        $avisos = [];
        $laudos->load(['amostra.pontoColeta', 'amostra.coleta.orcamento.cliente.contatoPrincipal']);
        $laboratorio = Configuracao::atual()->dadosLaboratorio()['nome'];

        foreach ($laudos->groupBy(fn (Laudo $l) => $l->amostra->coleta_id) as $doMesmoEmail) {
            $coleta = $doMesmoEmail->first()->amostra->coleta;
            $contato = $coleta->orcamento->cliente->contatoPrincipal;
            $numeros = $doMesmoEmail->map->numeroComRevisao()->implode(', ');
            if (! $contato?->email) {
                $avisos[] = "O contato principal do cliente não tem e-mail: entregue o laudo {$numeros} por outro meio.";

                continue;
            }

            try {
                Mail::to($contato->email)->send(new LaudoMail($coleta, $contato->nome, $doMesmoEmail->map(fn (Laudo $l) => [
                    'numero' => $l->numeroComRevisao(),
                    'ponto' => $l->amostra->pontoColeta->identificacao,
                    'conclusao' => $l->nomeConclusao(),
                    'pdf' => Storage::disk('local')->get($l->pdf_caminho),
                    'arquivo' => $l->nomeArquivo(),
                ])->values()->all(), $laboratorio));
            } catch (Throwable $erro) {
                Log::error("Falha ao enviar o laudo {$numeros}: {$erro->getMessage()}");
                $avisos[] = "Não foi possível enviar o e-mail do laudo {$numeros} agora: tente reenviar pelo laudo.";

                continue;
            }

            foreach ($doMesmoEmail as $laudo) {
                $antes = $this->valores($laudo);
                $laudo->forceFill(['envio_email' => $contato->email, 'enviado_em' => now(), 'enviado_por_id' => Auth::id()])->save();
                $this->auditar($laudo, $antes);
            }
        }

        return $avisos;
    }

    /**
     * UC27: a retificação exige motivo e reabre os resultados para correção e nova liberação (RN55). Quando a correção
     * é só nos dados do cliente ou do ponto (UC27 5a), a amostra volta direto para Liberada, pronta para o novo laudo.
     */
    public function retificar(Laudo $laudo, string $motivo, bool $reabrirResultados): void
    {
        DB::transaction(function () use ($laudo, $motivo, $reabrirResultados) {
            $laudo = Laudo::with('amostra')->lockForUpdate()->findOrFail($laudo->id);
            if (! $laudo->vigente() || $laudo->amostra->situacao !== Amostra::LAUDO_EMITIDO) {
                throw ValidationException::withMessages(['motivo' => 'Só o laudo Vigente pode ser retificado, e uma retificação por vez.']);
            }
            $this->agendamento->mudarAmostra($laudo->amostra, $reabrirResultados ? Amostra::EM_ANALISE : Amostra::LIBERADA, [
                'motivo_retificacao' => $motivo,
                ...($reabrirResultados ? ['liberada_em' => null, 'liberada_por_id' => null] : []),
            ]);
        });
    }

    /** Conteúdo do PDF do laudo (RF52). */
    public function pdf(Laudo $laudo, Amostra $amostra, bool $previa = false): string
    {
        $amostra->load(['coleta.orcamento.cliente', 'coleta.orcamento.tipoAmostra', 'coleta.coletor', 'pontoColeta', 'legislacao',
            'resultados.parametro', 'recebidaPor', 'liberadaPor']);
        $config = Configuracao::atual();
        $ordem = $amostra->parametrosAnalise()->pluck('id')->flip();

        return Pdf::loadView('laudos.pdf', [
            'laudo' => $laudo,
            'amostra' => $amostra,
            'resultados' => $amostra->resultados->sortBy(fn ($r) => $ordem[$r->parametro_id] ?? PHP_INT_MAX)->values(),
            'config' => $config,
            'laboratorio' => $config->dadosLaboratorio(),
            'previa' => $previa,
            'qrCode' => $previa ? null : QrCode::dataUri($laudo->urlVerificacao()),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }

    /** RN54: próximo número do ano, ex.: LAU-2026-0001. */
    private function numerar(Laudo $laudo): void
    {
        $laudo->ano = (int) now()->format('Y');
        $laudo->sequencia = (int) Laudo::where('ano', $laudo->ano)->orderByDesc('sequencia')->lockForUpdate()->value('sequencia') + 1;
        $laudo->revisao = 1;
    }

    /** RN57: código de verificação impresso no laudo, ex.: 7KQ4-M2XP-9C. */
    private function novoCodigo(): string
    {
        do {
            $codigo = collect(range(1, 10))->map(fn () => self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)])->implode('');
        } while (Laudo::where('codigo_verificacao', $codigo)->exists());

        return $codigo;
    }

    private function valores(Laudo $laudo): array
    {
        return [
            'numero' => $laudo->numeroComRevisao(),
            'situacao' => $laudo->nomeSituacao(),
            'conclusao' => $laudo->nomeConclusao(),
            'pdf_hash' => $laudo->pdf_hash,
            'envio_email' => $laudo->envio_email,
            'motivo_retificacao' => $laudo->motivo_retificacao,
        ];
    }

    private function auditar(Laudo $laudo, array $antes): void
    {
        $this->auditoria->registrar(self::ENTIDADE, $laudo->id, $antes === [] ? Auditoria::INCLUSAO : Auditoria::ALTERACAO,
            $antes, $this->valores($laudo), ['numero', 'situacao', 'conclusao', 'pdf_hash', 'envio_email', 'motivo_retificacao']);
    }

    /** Código formatado para leitura: 7KQ4M2XP9C → 7KQ4-M2XP-9C. */
    public static function formatarCodigo(string $codigo): string
    {
        return implode('-', str_split(Str::upper($codigo), 4));
    }
}
