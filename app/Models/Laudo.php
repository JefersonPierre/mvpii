<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Laudo de uma amostra (RF52), numerado por ano (RN54). O laudo emitido não muda: a correção gera uma nova revisão,
 * que substitui a anterior (RN55, RNF17).
 *
 * @property int $id
 * @property int $amostra_id
 * @property int $ano
 * @property int $sequencia
 * @property int $revisao
 * @property string $codigo_verificacao
 * @property string $situacao
 * @property string $conclusao
 * @property string $pdf_caminho
 * @property string $pdf_hash
 * @property Carbon $emitido_em
 * @property Carbon|null $enviado_em
 */
class Laudo extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const VIGENTE = 'VIGENTE';

    public const SUBSTITUIDO = 'SUBSTITUIDO';

    public const SITUACOES = [self::VIGENTE => 'Vigente', self::SUBSTITUIDO => 'Substituído'];

    protected $attributes = ['situacao' => self::VIGENTE, 'revisao' => 1];

    protected function casts(): array
    {
        return ['emitido_em' => 'datetime', 'enviado_em' => 'datetime'];
    }

    public function amostra(): BelongsTo
    {
        return $this->belongsTo(Amostra::class);
    }

    public function substitui(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substitui_id');
    }

    public function substituidoPor(): HasOne
    {
        return $this->hasOne(self::class, 'substitui_id');
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emitido_por_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'enviado_por_id');
    }

    /** Ex.: LAU-2026-0001 */
    public function numero(): string
    {
        return sprintf('LAU-%d-%04d', $this->ano, $this->sequencia);
    }

    /** Ex.: LAU-2026-0001 rev. 2 */
    public function numeroComRevisao(): string
    {
        return $this->numero().' rev. '.$this->revisao;
    }

    public function nomeSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }

    public function nomeConclusao(): string
    {
        return Amostra::CONFORMIDADES[$this->conclusao] ?? $this->conclusao;
    }

    public function vigente(): bool
    {
        return $this->situacao === self::VIGENTE;
    }

    public function nomeArquivo(): string
    {
        return "{$this->numero()}-r{$this->revisao}.pdf";
    }

    /** RN57: endereço da página pública de verificação, impresso no laudo como QR Code. */
    public function urlVerificacao(): string
    {
        return route('laudos.verificar', ['codigo' => $this->codigo_verificacao]);
    }
}
