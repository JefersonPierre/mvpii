<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Grava um cadastro simples (catálogo técnico) e registra o histórico na mesma transação (RN08).
class CadastroComHistorico
{
    public function __construct(private readonly Auditoria $auditoria) {}

    /**
     * @param  list<string>  $campos  campos auditados
     * @param  Closure(Model): void|null  $depoisDeGravar  ex.: sincronizar relações muitos-para-muitos
     * @param  Closure(Model): array<string, mixed>|null  $valores  valores para o histórico (padrão: os campos do model)
     */
    public function salvar(Model $registro, array $dados, string $entidade, array $campos,
        ?Closure $depoisDeGravar = null, ?Closure $valores = null): Model
    {
        $valores ??= fn (Model $m) => $m->only($campos);

        return DB::transaction(function () use ($registro, $dados, $entidade, $campos, $depoisDeGravar, $valores) {
            $novo = ! $registro->exists;
            $antes = $novo ? [] : $valores($registro);

            $registro->fill($dados)->save();
            if ($depoisDeGravar) {
                $depoisDeGravar($registro);
            }

            $this->auditoria->registrar($entidade, $registro->getKey(), $novo ? Auditoria::INCLUSAO : Auditoria::ALTERACAO,
                $antes, $valores($registro), $campos);

            return $registro;
        });
    }

    /** RN04: inativar ou reativar, com histórico. */
    public function alterarSituacao(Model $registro, bool $ativo, string $entidade, array $campos, ?Closure $valores = null): void
    {
        if ($registro->ativo === $ativo) {
            return;
        }
        $valores ??= fn (Model $m) => $m->only($campos);

        DB::transaction(function () use ($registro, $ativo, $entidade, $campos, $valores) {
            $antes = $valores($registro);
            $registro->update(['ativo' => $ativo]);
            $this->auditoria->registrar($entidade, $registro->getKey(), $ativo ? Auditoria::REATIVACAO : Auditoria::INATIVACAO,
                $antes, $valores($registro), $campos);
        });
    }

    /** Histórico de um registro, do mais recente para o mais antigo. */
    public function historico(string $entidade, int $id): LengthAwarePaginator
    {
        return RegistroAuditoria::with('responsavel')
            ->where(['entidade' => $entidade, 'registro_id' => $id])
            ->orderByDesc('data_hora')->orderByDesc('id')
            ->paginate(30);
    }
}
