<?php

namespace App\Models;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contato de um cliente (RF06). */
class Contato extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $fillable = ['nome', 'cargo', 'telefone', 'email', 'principal'];

    protected function casts(): array
    {
        return ['principal' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function telefoneFormatado(): string
    {
        return $this->telefone ? Documento::formatarTelefone($this->telefone) : '';
    }

    /** Resumo usado no histórico de alterações (RN08). */
    public function resumo(): string
    {
        return collect([
            $this->nome,
            $this->cargo,
            $this->telefoneFormatado(),
            $this->email,
            $this->principal ? 'principal' : null,
        ])->filter()->implode(' · ');
    }
}
