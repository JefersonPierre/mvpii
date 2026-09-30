<?php

namespace App\Services;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Pacote;
use App\Models\Parametro;
use App\Support\CalculoOrcamento;
use App\Support\Numero;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// UC08 – Elaborar orçamento (RF17, RF20, RN12, RN13, RN15, RN16), com histórico (RN08).
class CadastroOrcamentos
{
    public const ENTIDADE = 'ORCAMENTO';

    public const CAMPOS_AUDITADOS = ['cliente', 'contato', 'tipo_amostra', 'itens', 'taxa_coleta', 'desconto_percentual',
        'valor_total', 'validade_dias', 'condicoes_pagamento', 'observacoes', 'situacao', 'data_envio', 'envio_email',
        'valido_ate', 'data_resposta', 'motivo_recusa'];

    public function __construct(private readonly Auditoria $auditoria) {}

    /**
     * Cria ou altera um rascunho.
     *
     * @param  array<string, mixed>  $dados  dados validados pelo OrcamentoRequest
     */
    public function salvarRascunho(Orcamento $orcamento, array $dados): Orcamento
    {
        return DB::transaction(function () use ($orcamento, $dados) {
            $novo = ! $orcamento->exists;
            $antes = $novo ? [] : $this->valores($orcamento);

            $orcamento->fill($dados);
            if ($novo) {
                $this->numerar($orcamento);
                $orcamento->criado_por_id = Auth::id();
            }
            $orcamento->save();

            $this->gravarItens($orcamento, $this->itensDoFormulario($orcamento, $dados['itens']));
            $this->recalcular($orcamento);

            $this->registrar($orcamento, $novo ? Auditoria::INCLUSAO : Auditoria::ALTERACAO, $antes);

            return $orcamento;
        });
    }

    /**
     * RF20 / UC08 1b: novo orçamento (novo número) a partir de um existente, com os preços atuais do catálogo.
     * Itens que saíram do catálogo (inativos) ficam de fora e são devolvidos para avisar o usuário.
     *
     * @return array{0: Orcamento, 1: list<string>}
     */
    public function duplicar(Orcamento $origem): array
    {
        return DB::transaction(function () use ($origem) {
            $copia = $origem->replicate(['ano', 'sequencia', 'revisao', 'substitui_id', 'situacao', 'data_envio', 'valido_ate',
                'envio_email', 'pdf_caminho', 'data_resposta', 'motivo_recusa', 'motivo_detalhe', 'criado_por_id']);
            $copia->situacao = Orcamento::RASCUNHO;
            $copia->revisao = 1;
            $copia->criado_por_id = Auth::id();
            $this->numerar($copia);
            $copia->save();

            $itens = [];
            $ignorados = [];
            foreach ($origem->itens as $item) {
                $catalogo = $item->tipo === 'PACOTE' ? Pacote::find($item->pacote_id) : Parametro::find($item->parametro_id);
                if (! $catalogo?->ativo) {
                    $ignorados[] = $item->descricao;

                    continue;
                }
                $itens[] = $this->itemDoCatalogo($catalogo, $item->quantidade);
            }
            $this->gravarItens($copia, $itens);
            $this->recalcular($copia);
            $this->registrar($copia, Auditoria::INCLUSAO, []);

            return [$copia, $ignorados];
        });
    }

    /**
     * RN16 / UC09 1a: depois de enviado, mudança só por nova revisão. A revisão mantém o número, os itens e os preços;
     * a anterior passa a Substituída.
     */
    public function novaRevisao(Orcamento $anterior): Orcamento
    {
        return DB::transaction(function () use ($anterior) {
            $revisao = $anterior->replicate(['situacao', 'data_envio', 'valido_ate', 'envio_email', 'pdf_caminho',
                'data_resposta', 'motivo_recusa', 'motivo_detalhe', 'criado_por_id']);
            $revisao->situacao = Orcamento::RASCUNHO;
            $revisao->revisao = Orcamento::where(['ano' => $anterior->ano, 'sequencia' => $anterior->sequencia])->max('revisao') + 1;
            $revisao->substitui_id = $anterior->id;
            $revisao->criado_por_id = Auth::id();
            $revisao->save();

            $this->gravarItens($revisao, $anterior->itens->map(fn (OrcamentoItem $i) => $i->only(
                ['tipo', 'pacote_id', 'parametro_id', 'descricao', 'parametros_incluidos', 'preco_unitario', 'quantidade']))->all());
            $this->recalcular($revisao);
            $this->registrar($revisao, Auditoria::INCLUSAO, []);

            $this->mudarSituacao($anterior, Orcamento::SUBSTITUIDO);

            return $revisao;
        });
    }

    /**
     * RN17: orçamento enviado e sem resposta após a data de validade passa para Expirado.
     * Roda todo dia (agendador) e também ao abrir a tela de orçamentos. Devolve quantos expiraram.
     */
    public function expirarVencidos(): int
    {
        $vencidos = Orcamento::where('situacao', Orcamento::ENVIADO)->whereDate('valido_ate', '<', today())->get();

        $this->auditoria->comoSistema(fn () => $vencidos->each(fn (Orcamento $o) => $this->mudarSituacao($o, Orcamento::EXPIRADO)));

        return $vencidos->count();
    }

    /** Muda a situação (e outros campos do envio ou da resposta) registrando no histórico. */
    public function mudarSituacao(Orcamento $orcamento, string $situacao, array $campos = []): void
    {
        DB::transaction(function () use ($orcamento, $situacao, $campos) {
            $antes = $this->valores($orcamento);
            $orcamento->forceFill([...$campos, 'situacao' => $situacao])->save();
            $this->registrar($orcamento, Auditoria::ALTERACAO, $antes);
        });
    }

    /** RN12: próximo número do ano, começando em 1 a cada ano. */
    private function numerar(Orcamento $orcamento): void
    {
        $orcamento->ano = (int) now()->format('Y');
        // Trava a linha do maior número (o PostgreSQL não aceita FOR UPDATE junto com MAX).
        $orcamento->sequencia = (int) Orcamento::where('ano', $orcamento->ano)
            ->orderByDesc('sequencia')->lockForUpdate()->value('sequencia') + 1;
        $orcamento->revisao = 1;
    }

    /**
     * Itens do formulário. Um item que já estava no orçamento mantém o preço copiado antes (RN15);
     * item novo copia o preço atual do catálogo.
     *
     * @param  array<int|string, array{id?: int|null, referencia: string, quantidade: int|string}>  $linhas
     */
    private function itensDoFormulario(Orcamento $orcamento, array $linhas): array
    {
        $existentes = $orcamento->itens()->get()->keyBy('id');
        $itens = [];
        foreach ($linhas as $linha) {
            $quantidade = (int) $linha['quantidade'];
            $atual = isset($linha['id']) ? $existentes->get((int) $linha['id']) : null;
            if ($atual && $atual->referencia() === $linha['referencia']) {
                $itens[] = [...$atual->only(['tipo', 'pacote_id', 'parametro_id', 'descricao', 'parametros_incluidos', 'preco_unitario']),
                    'quantidade' => $quantidade];

                continue;
            }
            [$tipo, $id] = explode(':', $linha['referencia']);
            $itens[] = $this->itemDoCatalogo($tipo === 'PACOTE' ? Pacote::findOrFail($id) : Parametro::findOrFail($id), $quantidade);
        }

        return $itens;
    }

    /** RN15: copia nome e preço do catálogo. */
    private function itemDoCatalogo(Pacote|Parametro $catalogo, int $quantidade): array
    {
        if ($catalogo instanceof Pacote) {
            return [
                'tipo' => 'PACOTE', 'pacote_id' => $catalogo->id, 'parametro_id' => null,
                'descricao' => 'Pacote '.$catalogo->nome,
                'parametros_incluidos' => $catalogo->parametros()->pluck('nome')->implode(', '),
                'preco_unitario' => $catalogo->preco, 'quantidade' => $quantidade,
            ];
        }

        return [
            'tipo' => 'PARAMETRO', 'pacote_id' => null, 'parametro_id' => $catalogo->id,
            'descricao' => $catalogo->nome, 'parametros_incluidos' => null,
            'preco_unitario' => $catalogo->preco, 'quantidade' => $quantidade,
        ];
    }

    private function gravarItens(Orcamento $orcamento, array $itens): void
    {
        $orcamento->itens()->delete();
        foreach (array_values($itens) as $ordem => $item) {
            $orcamento->itens()->create([...$item, 'subtotal' => 0, 'ordem' => $ordem]);
        }
    }

    /** RN13: o servidor sempre recalcula; o cálculo da tela é só uma prévia. */
    private function recalcular(Orcamento $orcamento): void
    {
        $itens = $orcamento->itens()->get();
        $calculo = CalculoOrcamento::calcular(
            $itens->map(fn (OrcamentoItem $i) => ['preco' => $i->preco_unitario, 'quantidade' => $i->quantidade])->all(),
            $orcamento->taxa_coleta,
            $orcamento->desconto_percentual,
        );
        $itens->each(fn (OrcamentoItem $item, int $i) => $item->update(['subtotal' => $calculo['subtotais'][$i]]));

        $orcamento->forceFill([
            'subtotal_itens' => $calculo['subtotal_itens'],
            'valor_desconto' => $calculo['valor_desconto'],
            'valor_total' => $calculo['valor_total'],
        ])->save();
        $orcamento->unsetRelation('itens');
    }

    private function registrar(Orcamento $orcamento, string $acao, array $antes): void
    {
        $this->auditoria->registrar(self::ENTIDADE, $orcamento->id, $acao, $antes, $this->valores($orcamento), self::CAMPOS_AUDITADOS);
    }

    /** Valores legíveis para o histórico (RN08). */
    private function valores(Orcamento $orcamento): array
    {
        $orcamento->load('cliente', 'contato', 'tipoAmostra'); // recarrega: o cliente pode ter mudado no formulário
        $itens = $orcamento->itens()->get()->map(fn (OrcamentoItem $i) => "{$i->descricao} × {$i->quantidade} (".Numero::moeda($i->preco_unitario).')');

        return [
            'cliente' => $orcamento->cliente?->nome,
            'contato' => $orcamento->contato?->nome,
            'tipo_amostra' => $orcamento->tipoAmostra?->nome,
            'itens' => $itens->implode('; '),
            'taxa_coleta' => $orcamento->taxa_coleta,
            'desconto_percentual' => $orcamento->desconto_percentual,
            'valor_total' => $orcamento->valor_total,
            'validade_dias' => $orcamento->validade_dias,
            'condicoes_pagamento' => $orcamento->condicoes_pagamento,
            'observacoes' => $orcamento->observacoes,
            'situacao' => Orcamento::SITUACOES[$orcamento->situacao] ?? $orcamento->situacao,
            'data_envio' => $orcamento->data_envio?->format('d/m/Y H:i'),
            'envio_email' => $orcamento->envio_email,
            'valido_ate' => $orcamento->valido_ate?->format('d/m/Y'),
            'data_resposta' => $orcamento->data_resposta?->format('d/m/Y'),
            'motivo_recusa' => collect([$orcamento->nomeMotivoRecusa(), $orcamento->motivo_detalhe])->filter()->implode(': ') ?: null,
        ];
    }
}
