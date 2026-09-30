<?php

namespace App\Models;

use App\Models\Concerns\TemEndereco;
use Database\Factories\PontoColetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local onde as amostras são coletadas, sempre de um cliente (RF09, RN03).
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $identificacao
 * @property bool $ativo
 */
class PontoColeta extends Model
{
    /** @use HasFactory<PontoColetaFactory> */
    use HasFactory, TemEndereco;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'pontos_coleta';

    protected $fillable = ['identificacao', 'tipo_amostra_id', 'cep', 'logradouro', 'numero', 'complemento', 'bairro',
        'cidade', 'uf', 'referencia', 'latitude', 'longitude', 'ativo'];

    protected $attributes = ['ativo' => true];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipoAmostra(): BelongsTo
    {
        return $this->belongsTo(TipoAmostra::class);
    }

    /** Link para ver as coordenadas no mapa, quando informadas. */
    public function linkMapa(): ?string
    {
        return $this->latitude !== null && $this->longitude !== null
            ? "https://www.openstreetmap.org/?mlat={$this->latitude}&mlon={$this->longitude}#map=17/{$this->latitude}/{$this->longitude}"
            : null;
    }
}
