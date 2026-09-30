// Regras de negócio do acesso (RN09). Funções puras para facilitar os testes.

export const MAX_TENTATIVAS = 5;
export const MINUTOS_BLOQUEIO = 15;
export const MINUTOS_VALIDADE_LINK = 60;

/** RN09: mínimo de 8 caracteres, com letras e números. */
export function senhaAtendeRegra(senha: string): boolean {
  return senha.length >= 8 && /[A-Za-z]/.test(senha) && /\d/.test(senha);
}

export function estaBloqueado(bloqueadoAte: Date | null, agora: Date): boolean {
  return bloqueadoAte !== null && bloqueadoAte.getTime() > agora.getTime();
}

export interface ResultadoFalha {
  tentativasFalhas: number;
  bloqueadoAte: Date | null;
  bloqueou: boolean;
}

/** Conta uma tentativa incorreta; na quinta seguida bloqueia por 15 minutos e zera o contador. */
export function registrarFalha(tentativasAtuais: number, agora: Date): ResultadoFalha {
  const tentativas = tentativasAtuais + 1;
  if (tentativas >= MAX_TENTATIVAS) {
    return {
      tentativasFalhas: 0,
      bloqueadoAte: new Date(agora.getTime() + MINUTOS_BLOQUEIO * 60_000),
      bloqueou: true,
    };
  }
  return { tentativasFalhas: tentativas, bloqueadoAte: null, bloqueou: false };
}
