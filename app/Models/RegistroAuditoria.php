<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RN08: uma linha por campo alterado. */
class RegistroAuditoria extends Model
{
    public $timestamps = false;

    protected $table = 'registros_auditoria';

    protected $fillable = ['usuario_id', 'entidade', 'registro_id', 'acao', 'campo', 'valor_anterior', 'valor_novo', 'data_hora'];

    protected function casts(): array
    {
        return ['data_hora' => 'datetime'];
    }

    /** Quem fez a alteração. */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
