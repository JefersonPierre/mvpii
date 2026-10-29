<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto opcional tirada pelo coletor no registro da coleta (RN45).
 *
 * @property int $id
 * @property int $amostra_id
 * @property string $caminho
 * @property int $tamanho
 */
class FotoAmostra extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'fotos_amostra';

    /** RN45 */
    public const MAXIMO_POR_AMOSTRA = 3;

    public const TAMANHO_MAXIMO = 1024 * 1024;

    public function amostra(): BelongsTo
    {
        return $this->belongsTo(Amostra::class);
    }

    public function sincronizacao(): BelongsTo
    {
        return $this->belongsTo(Sincronizacao::class);
    }
}
