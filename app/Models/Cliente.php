<?php

namespace App\Models;

use App\Models\Concerns\TemEndereco;
use App\Support\Documento;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cliente ou interessado do laboratório (RF04, RF05).
 *
 * @property int $id
 * @property bool $interessado
 * @property string $tipo_pessoa
 * @property string $nome
 * @property string|null $documento
 * @property string|null $cidade
 * @property bool $ativo
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, TemEndereco;

    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const UFS = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE',
        'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];

    protected $fillable = ['interessado', 'tipo_pessoa', 'nome', 'nome_fantasia', 'documento', 'cep', 'logradouro',
        'numero', 'complemento', 'bairro', 'cidade', 'uf', 'observacoes', 'ativo'];

    protected $attributes = ['ativo' => true, 'interessado' => false];

    protected function casts(): array
    {
        return ['interessado' => 'boolean', 'ativo' => 'boolean'];
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(Contato::class)->orderByDesc('principal')->orderBy('nome');
    }

    public function contatoPrincipal(): HasOne
    {
        return $this->hasOne(Contato::class)->where('principal', true);
    }

    public function pontosColeta(): HasMany
    {
        return $this->hasMany(PontoColeta::class)->orderByDesc('ativo')->orderBy('identificacao');
    }

    public function documentoFormatado(): string
    {
        return $this->documento ? Documento::formatar($this->documento) : '';
    }
}
