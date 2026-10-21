<?php

namespace App\Services;

use App\Models\Amostra;
use App\Models\Configuracao;
use App\Support\CriteriosRecebimento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// UC17 – Registrar recebimento da amostra (RF34, RN28, RN33, RN34, RN35), com histórico (RNF12).
class RecebimentoAmostras
{
    public function __construct(private readonly AgendamentoColetas $agendamento) {}

    /** RN34: separação de funções – quem registrou a coleta da amostra não registra o seu recebimento. */
    public static function mesmoResponsavelDaColeta(Amostra $amostra, int $usuarioId): bool
    {
        return $amostra->coletada_por_id === $usuarioId;
    }

    /**
     * Alertas de prazo e temperatura (RN35) para a amostra recebida no momento e na temperatura informados.
     *
     * @return list<string>
     */
    public function alertas(Amostra $amostra, Carbon $recebidaEm, float|string|null $temperatura): array
    {
        $config = Configuracao::atual();
        $minutos = CriteriosRecebimento::minutosDesdeColeta($amostra->coleta->data_hora_coleta, $recebidaEm);

        return CriteriosRecebimento::alertas($minutos, $temperatura, $config->prazo_recebimento_horas, $config->temperatura_maxima_recebimento);
    }

    /**
     * Aceita ou rejeita uma ou mais amostras Coletadas da mesma coleta (UC17 1a). A rejeitada volta ao saldo (RN28).
     *
     * @param  Collection<int, Amostra>  $amostras
     * @param  array{recebida_em: string, temperatura_recebimento: string, decisao: string, observacoes_recebimento?: string|null,
     *               motivo_rejeicao?: string|null, frasco_integro?: bool, identificacao_legivel?: bool, volume_suficiente?: bool,
     *               preservacao_adequada?: bool}  $dados
     *
     * @throws ValidationException
     */
    public function registrar(Collection $amostras, array $dados): void
    {
        DB::transaction(function () use ($amostras, $dados) {
            $recebidaEm = Carbon::parse($dados['recebida_em']);
            $aceitar = $dados['decisao'] === 'ACEITAR';

            foreach ($amostras as $amostra) {
                $amostra = Amostra::with('coleta')->lockForUpdate()->findOrFail($amostra->id);
                if ($amostra->situacao !== Amostra::COLETADA) {
                    throw ValidationException::withMessages(['amostras' => "A amostra {$amostra->codigo} não está Coletada e não pode ser recebida."]);
                }
                if (self::mesmoResponsavelDaColeta($amostra, (int) Auth::id())) {
                    throw ValidationException::withMessages(['amostras' => "Você registrou a coleta da amostra {$amostra->codigo}: o recebimento deve ser feito por outro analista."]);
                }
                if ($recebidaEm->lt($amostra->coleta->data_hora_coleta)) {
                    throw ValidationException::withMessages(['recebida_em' => 'O recebimento não pode ser anterior à coleta ('.$amostra->coleta->data_hora_coleta->format('d/m/Y H:i').').']);
                }

                $alertas = $this->alertas($amostra, $recebidaEm, $dados['temperatura_recebimento']);
                if ($aceitar && $alertas && blank($dados['observacoes_recebimento'] ?? null)) {
                    throw ValidationException::withMessages(['observacoes_recebimento' => 'Fora dos critérios de recebimento: registre uma observação para aceitar.']);
                }

                $this->agendamento->mudarAmostra($amostra, $aceitar ? Amostra::RECEBIDA : Amostra::REJEITADA, [
                    'recebida_em' => $recebidaEm,
                    'recebida_por_id' => Auth::id(),
                    'temperatura_recebimento' => $dados['temperatura_recebimento'],
                    ...collect(Amostra::CONDICOES_FRASCO)->map(fn ($nome, $campo) => (bool) ($dados[$campo] ?? false))->all(),
                    'fora_criterios' => $alertas !== [],
                    'observacoes_recebimento' => $dados['observacoes_recebimento'] ?? null,
                    'motivo_rejeicao' => $aceitar ? null : $dados['motivo_rejeicao'],
                ]);
            }
        });
    }
}
