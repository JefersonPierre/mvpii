<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurações comerciais do orçamento (RF22), critérios de recebimento das amostras (RF35) e dados do laboratório
 * usados no laudo e na rota (RF56). Existe uma única linha.
 *
 * @property int $validade_dias
 * @property string $taxa_coleta
 * @property string $desconto_maximo
 * @property string|null $condicoes_comerciais
 * @property int $prazo_recebimento_horas
 * @property string $temperatura_maxima_recebimento
 * @property string|null $laboratorio_nome
 * @property float|null $laboratorio_latitude
 * @property float|null $laboratorio_longitude
 * @property string|null $responsavel_tecnico
 * @property string|null $registro_profissional
 * @property string|null $observacoes_laudo
 */
class Configuracao extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    protected $table = 'configuracoes';

    protected $fillable = ['validade_dias', 'taxa_coleta', 'desconto_maximo', 'condicoes_comerciais',
        'prazo_recebimento_horas', 'temperatura_maxima_recebimento', 'laboratorio_nome', 'laboratorio_endereco',
        'laboratorio_telefone', 'laboratorio_latitude', 'laboratorio_longitude', 'responsavel_tecnico',
        'registro_profissional', 'observacoes_laudo'];

    protected function casts(): array
    {
        return ['validade_dias' => 'integer', 'taxa_coleta' => 'decimal:2', 'desconto_maximo' => 'decimal:2',
            'prazo_recebimento_horas' => 'integer', 'temperatura_maxima_recebimento' => 'decimal:1',
            'laboratorio_latitude' => 'float', 'laboratorio_longitude' => 'float'];
    }

    public static function atual(): self
    {
        return self::query()->firstOrFail();
    }

    /**
     * RF56: dados do laboratório no laudo. O que não foi preenchido em Configurações vem do .env (seção "dados" de
     * config/laboratorio.php), como no orçamento.
     *
     * @return array{nome: string, endereco: string|null, telefone: string|null, email: string|null, cnpj: string|null}
     */
    public function dadosLaboratorio(): array
    {
        $env = config('laboratorio.dados');

        return [
            'nome' => $this->laboratorio_nome ?: $env['nome'],
            'endereco' => $this->laboratorio_endereco ?: $env['endereco'],
            'telefone' => $this->laboratorio_telefone ?: $env['telefone'],
            'email' => $env['email'],
            'cnpj' => $env['cnpj'],
        ];
    }

    public function temCoordenadasLaboratorio(): bool
    {
        return $this->laboratorio_latitude !== null && $this->laboratorio_longitude !== null;
    }

    /** UC26: o laudo só é emitido com o responsável técnico configurado (UC29). */
    public function laudoConfigurado(): bool
    {
        return filled($this->responsavel_tecnico) && filled($this->registro_profissional);
    }
}
