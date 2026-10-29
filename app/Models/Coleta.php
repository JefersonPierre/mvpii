<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Coleta agendada a partir de um orçamento aprovado (RF27), com o registro do que foi coletado (RF33).
 *
 * @property int $id
 * @property int $orcamento_id
 * @property int $coletor_id
 * @property Carbon $data
 * @property string $periodo
 * @property string|null $horario
 * @property string $situacao
 * @property Carbon|null $data_hora_coleta
 */
class Coleta extends Model
{
    const CREATED_AT = 'criado_em';

    const UPDATED_AT = 'atualizado_em';

    public const AGENDADA = 'AGENDADA';

    public const REALIZADA = 'REALIZADA';

    public const NAO_REALIZADA = 'NAO_REALIZADA';

    public const CANCELADA = 'CANCELADA';

    /** RN31 */
    public const SITUACOES = [
        self::AGENDADA => 'Agendada',
        self::REALIZADA => 'Realizada',
        self::NAO_REALIZADA => 'Não realizada',
        self::CANCELADA => 'Cancelada',
    ];

    public const PERIODOS = ['MANHA' => 'Manhã', 'TARDE' => 'Tarde'];

    /** RN41: origem do registro da coleta. */
    public const ORIGENS = [
        'COMPUTADOR' => 'Computador',
        'CELULAR' => 'Celular com conexão',
        'CELULAR_SEM_CONEXAO' => 'Celular sem conexão',
    ];

    /** RN44: distância máxima entre a localização da coleta e o ponto antes do alerta. */
    public const DISTANCIA_MAXIMA_METROS = 200;

    /** Motivos de coleta não realizada e de amostra não coletada (UC16 3a). */
    public const MOTIVOS_NAO_COLETA = [
        'PONTO_INACESSIVEL' => 'Ponto inacessível',
        'SEM_AGUA' => 'Sem água no ponto',
        'CLIENTE_AUSENTE' => 'Cliente ausente',
        'OUTRO' => 'Outro',
    ];

    protected $fillable = ['coletor_id', 'data', 'periodo', 'horario', 'instrucoes'];

    protected $attributes = ['situacao' => self::AGENDADA];

    protected function casts(): array
    {
        return ['data' => 'date', 'data_hora_coleta' => 'datetime', 'registro_recebido_em' => 'datetime',
            'conferir_localizacao' => 'boolean', 'ordem_visita' => 'integer'];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function coletor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'coletor_id');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrada_por_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'criado_por_id');
    }

    public function amostras(): HasMany
    {
        return $this->hasMany(Amostra::class)->orderBy('sequencia');
    }

    public function sincronizacoes(): HasMany
    {
        return $this->hasMany(Sincronizacao::class)->orderByDesc('recebida_em');
    }

    public function nomeOrigemRegistro(): ?string
    {
        return $this->origem_registro ? (self::ORIGENS[$this->origem_registro] ?? $this->origem_registro) : null;
    }

    /** RN47: a coleta fica no mapa pelo primeiro ponto com coordenadas. */
    public function pontoNoMapa(): ?PontoColeta
    {
        return $this->amostras->map->pontoColeta->first(fn (?PontoColeta $p) => $p?->temCoordenadas());
    }

    public function nomeSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }

    public function nomePeriodo(): string
    {
        return self::PERIODOS[$this->periodo] ?? $this->periodo;
    }

    /** Ex.: "14/10/2026 · Manhã · 09:00". */
    public function quando(): string
    {
        return collect([$this->data->format('d/m/Y'), $this->nomePeriodo(), $this->horarioFormatado()])->filter()->implode(' · ');
    }

    public function horarioFormatado(): ?string
    {
        return $this->horario ? substr($this->horario, 0, 5) : null;
    }

    public function nomeMotivoNaoRealizada(): ?string
    {
        return $this->motivo_nao_realizada ? (self::MOTIVOS_NAO_COLETA[$this->motivo_nao_realizada] ?? $this->motivo_nao_realizada) : null;
    }

    /** RN31: só a coleta Agendada pode ser cancelada. */
    public function podeCancelar(): bool
    {
        return $this->situacao === self::AGENDADA;
    }

    /** RN31: a Agendada ou Não realizada pode ser reagendada. */
    public function podeReagendar(): bool
    {
        return in_array($this->situacao, [self::AGENDADA, self::NAO_REALIZADA], true);
    }

    /** RN32: só a coleta Agendada é registrada. */
    public function podeRegistrar(): bool
    {
        return $this->situacao === self::AGENDADA;
    }
}
