<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\PontoColeta;
use Illuminate\Support\Facades\DB;

// UC04 – Cadastrar ponto de coleta (RF09, RF10, RN03, RN04), com histórico (RN08).
class CadastroPontos
{
    public const ENTIDADE = 'PONTO_COLETA';

    public const CAMPOS_AUDITADOS = ['identificacao', 'tipo_amostra', 'cep', 'logradouro', 'numero', 'complemento',
        'bairro', 'cidade', 'uf', 'referencia', 'latitude', 'longitude', 'ativo'];

    public function __construct(private readonly Auditoria $auditoria) {}

    /** @param  array<string, mixed>  $dados  dados validados pelo PontoColetaRequest */
    public function salvar(Cliente $cliente, PontoColeta $ponto, array $dados): PontoColeta
    {
        return DB::transaction(function () use ($cliente, $ponto, $dados) {
            $novo = ! $ponto->exists;
            $antes = $novo ? [] : $this->valores($ponto);

            $ponto->fill(collect($dados)->except('usar_endereco_cliente')->all());
            $ponto->cliente()->associate($cliente)->save();

            $this->auditoria->registrar(self::ENTIDADE, $ponto->id, $novo ? Auditoria::INCLUSAO : Auditoria::ALTERACAO,
                $antes, $this->valores($ponto), self::CAMPOS_AUDITADOS);

            return $ponto;
        });
    }

    public function alterarSituacao(PontoColeta $ponto, bool $ativo): void
    {
        if ($ponto->ativo === $ativo) {
            return;
        }
        DB::transaction(function () use ($ponto, $ativo) {
            $antes = $this->valores($ponto);
            $ponto->update(['ativo' => $ativo]);
            $this->auditoria->registrar(self::ENTIDADE, $ponto->id, $ativo ? Auditoria::REATIVACAO : Auditoria::INATIVACAO,
                $antes, $this->valores($ponto), self::CAMPOS_AUDITADOS);
        });
    }

    /** No histórico, o tipo de amostra aparece pelo nome (e não pelo código). */
    private function valores(PontoColeta $ponto): array
    {
        return [
            ...$ponto->only(self::CAMPOS_AUDITADOS),
            'tipo_amostra' => $ponto->tipoAmostra()->value('nome'),
        ];
    }
}
