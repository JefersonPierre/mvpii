<?php

namespace App\Services;

use App\Models\Legislacao;
use App\Models\Limite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// UC06 – Cadastrar legislação e limites (RF15, RF16, RN06, RN07), com histórico (RN08).
class CadastroLegislacoes
{
    public const ENTIDADE = 'LEGISLACAO';

    public const CAMPOS_AUDITADOS = ['nome', 'orgao_emissor', 'inicio_vigencia', 'fim_vigencia', 'tipos_amostra', 'substituida_por'];

    public function __construct(
        private readonly CadastroComHistorico $cadastro,
        private readonly Auditoria $auditoria,
    ) {}

    /** @param  array<string, mixed>  $dados  dados validados pelo LegislacaoRequest */
    public function salvar(Legislacao $legislacao, array $dados): Legislacao
    {
        return DB::transaction(function () use ($legislacao, $dados) {
            $this->cadastro->salvar(
                $legislacao,
                collect($dados)->only(['nome', 'orgao_emissor', 'inicio_vigencia', 'fim_vigencia'])->all(),
                self::ENTIDADE,
                self::CAMPOS_AUDITADOS,
                fn (Legislacao $l) => $l->tiposAmostra()->sync($dados['tipos_amostra']),
                $this->valores(...),
            );

            if (! empty($dados['substitui_id'])) {
                $this->substituir(Legislacao::findOrFail($dados['substitui_id']), $legislacao,
                    Carbon::parse($dados['fim_anterior']), (bool) ($dados['copiar_limites'] ?? false));
            }

            return $legislacao;
        });
    }

    /**
     * UC06 1a / RN07: a anterior recebe a data de fim e a indicação da substituta; os limites dela são mantidos.
     * Opcionalmente, copia os limites para a nova (só dos tipos de amostra que a nova abrange).
     */
    private function substituir(Legislacao $anterior, Legislacao $nova, Carbon $fimAnterior, bool $copiarLimites): void
    {
        $this->cadastro->salvar(
            $anterior,
            ['fim_vigencia' => $fimAnterior, 'substituida_por_id' => $nova->id],
            self::ENTIDADE,
            self::CAMPOS_AUDITADOS,
            valores: $this->valores(...),
        );

        if (! $copiarLimites) {
            return;
        }
        $tipos = $nova->tiposAmostra()->pluck('tipos_amostra.id');
        $anterior->limites()->whereIn('tipo_amostra_id', $tipos)->get()->each(function (Limite $limite) use ($nova) {
            $copia = $nova->limites()->create($limite->only(['parametro_id', 'tipo_amostra_id', 'tipo', 'valor_minimo', 'valor_maximo', 'observacao']));
            $this->registrarLimite($nova, Auditoria::INCLUSAO, $copia, null, $this->resumo($copia));
        });
    }

    public function salvarLimite(Legislacao $legislacao, Limite $limite, array $dados): Limite
    {
        return DB::transaction(function () use ($legislacao, $limite, $dados) {
            $novo = ! $limite->exists;
            $antes = $novo ? null : $this->resumo($limite);
            $limite->fill($dados);
            $limite->legislacao()->associate($legislacao)->save();
            $this->registrarLimite($legislacao, $novo ? Auditoria::INCLUSAO : Auditoria::ALTERACAO, $limite, $antes, $this->resumo($limite));

            return $limite;
        });
    }

    /** Limite lançado por engano pode ser removido enquanto a legislação estiver em vigor (fica no histórico). */
    public function removerLimite(Limite $limite): void
    {
        DB::transaction(function () use ($limite) {
            $this->registrarLimite($limite->legislacao, Auditoria::ALTERACAO, $limite, $this->resumo($limite), null);
            $limite->delete();
        });
    }

    /** Os limites ficam no histórico da legislação, identificados pelo parâmetro e tipo de amostra. */
    private function registrarLimite(Legislacao $legislacao, string $acao, Limite $limite, ?string $antes, ?string $depois): void
    {
        $limite->loadMissing('parametro', 'tipoAmostra');
        $campo = mb_substr("Limite {$limite->parametro->nome} / {$limite->tipoAmostra->nome}", 0, 120);
        $this->auditoria->registrar(self::ENTIDADE, $legislacao->id, $acao, [$campo => $antes], [$campo => $depois], [$campo]);
    }

    private function resumo(Limite $limite): string
    {
        $limite->loadMissing('parametro');

        return collect([$limite->nomeTipo().': '.$limite->descricao(), $limite->observacao])->filter()->implode(' – ');
    }

    private function valores(Legislacao $legislacao): array
    {
        return [
            ...$legislacao->only(['nome', 'orgao_emissor']),
            'inicio_vigencia' => $legislacao->inicio_vigencia?->toDateString(),
            'fim_vigencia' => $legislacao->fim_vigencia?->toDateString(),
            'tipos_amostra' => $legislacao->tiposAmostra()->pluck('nome')->ordenarPorNome()->implode(', '),
            'substituida_por' => $legislacao->substituidaPor()->value('nome'),
        ];
    }
}
