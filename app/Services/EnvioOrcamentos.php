<?php

namespace App\Services;

use App\Mail\OrcamentoMail;
use App\Models\Orcamento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

// UC09 – Enviar orçamento (RF18, RF19): gera o PDF, envia por e-mail e marca como Enviado.
class EnvioOrcamentos
{
    public function __construct(private readonly CadastroOrcamentos $orcamentos) {}

    /** Conteúdo do PDF. No rascunho é uma prévia; a validade mostrada conta a partir de hoje. */
    public function pdf(Orcamento $orcamento): string
    {
        $orcamento->loadMissing(['cliente', 'contato', 'tipoAmostra', 'itens']);

        return Pdf::loadView('orcamentos.pdf', [
            'orcamento' => $orcamento,
            'laboratorio' => config('laboratorio.dados'),
            'validoAte' => $orcamento->valido_ate ?? $this->validadeSeEnviadoHoje($orcamento),
        ])->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true) // embute só os caracteres usados: anexo bem menor
            ->output();
    }

    public function nomeArquivo(Orcamento $orcamento): string
    {
        return "{$orcamento->numero()}-r{$orcamento->revisao}.pdf";
    }

    /** PDF guardado no envio, ou gerado na hora (rascunho). */
    public function pdfGuardadoOuPrevia(Orcamento $orcamento): string
    {
        return $orcamento->pdf_caminho && Storage::disk('local')->exists($orcamento->pdf_caminho)
            ? Storage::disk('local')->get($orcamento->pdf_caminho)
            : $this->pdf($orcamento);
    }

    /**
     * Envia o PDF por e-mail (ou registra o envio manual quando $email é null – UC09 2a).
     * Se o e-mail falhar, o orçamento continua em Rascunho (UC09 3a) e a exceção sobe com mensagem amigável.
     */
    public function enviar(Orcamento $orcamento, ?string $email, ?string $mensagem): void
    {
        if (! $orcamento->editavel()) {
            throw new RuntimeException('Só é possível enviar orçamento em Rascunho.');
        }

        // A validade conta a partir do envio (RN17). O PDF (sem data gravada ainda) já sai com ela.
        $validoAte = $this->validadeSeEnviadoHoje($orcamento);
        $conteudo = $this->pdf($orcamento);

        if ($email) {
            try {
                Mail::to($email)->send(new OrcamentoMail($orcamento, $mensagem ?? '', $validoAte, $conteudo, $this->nomeArquivo($orcamento)));
            } catch (Throwable $erro) {
                Log::error("Falha ao enviar o orçamento {$orcamento->numeroComRevisao()}: {$erro->getMessage()}");
                throw new RuntimeException('Não foi possível enviar o e-mail agora. O orçamento continua em Rascunho; tente de novo em instantes.');
            }
        }

        $caminho = 'orcamentos/'.$this->nomeArquivo($orcamento);
        Storage::disk('local')->put($caminho, $conteudo);

        $this->orcamentos->mudarSituacao($orcamento, Orcamento::ENVIADO, [
            'data_envio' => now(),
            'valido_ate' => $validoAte,
            'envio_email' => $email,
            'pdf_caminho' => $caminho,
        ]);
    }

    private function validadeSeEnviadoHoje(Orcamento $orcamento): Carbon
    {
        return today()->addDays($orcamento->validade_dias);
    }
}
