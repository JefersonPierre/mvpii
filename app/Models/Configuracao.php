<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurações comerciais do orçamento (RF22). Existe uma única linha.
 *
 * @property int $validade_dias
 * @property string $taxa_coleta
 * @property string $desconto_maximo
 * @property string|null $condicoes_comerciais
 */
class Configuracao extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'configuracoes';

    protected $fillable = ['validade_dias', 'taxa_coleta', 'desconto_maximo', 'condicoes_comerciais'];

    protected function casts(): array
    {
        return ['validade_dias' => 'integer', 'taxa_coleta' => 'decimal:2', 'desconto_maximo' => 'decimal:2'];
    }

    public static function atual(): self
    {
        return self::query()->firstOrFail();
    }
}
