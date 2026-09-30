import { MAX_TENTATIVAS, estaBloqueado, registrarFalha, senhaAtendeRegra } from './regras';

describe('RN09 – regra de senha', () => {
  it.each([
    ['Admin12345', true],
    ['abc12345', true],
    ['abc1234', false], // menos de 8 caracteres
    ['abcdefgh', false], // sem número
    ['12345678', false], // sem letra
  ])('%s → %s', (senha, esperado) => {
    expect(senhaAtendeRegra(senha)).toBe(esperado);
  });
});

describe('RN09 – bloqueio por tentativas', () => {
  const agora = new Date('2026-09-30T10:00:00Z');

  it('conta as tentativas sem bloquear até a quarta', () => {
    const r = registrarFalha(3, agora);
    expect(r).toEqual({ tentativasFalhas: 4, bloqueadoAte: null, bloqueou: false });
  });

  it('bloqueia por 15 minutos na quinta tentativa seguida (CT02)', () => {
    const r = registrarFalha(MAX_TENTATIVAS - 1, agora);
    expect(r.bloqueou).toBe(true);
    expect(r.tentativasFalhas).toBe(0);
    expect(r.bloqueadoAte).toEqual(new Date('2026-09-30T10:15:00Z'));
  });

  it('considera o bloqueio só até a data final', () => {
    const fim = new Date('2026-09-30T10:15:00Z');
    expect(estaBloqueado(fim, new Date('2026-09-30T10:14:59Z'))).toBe(true);
    expect(estaBloqueado(fim, new Date('2026-09-30T10:15:00Z'))).toBe(false);
    expect(estaBloqueado(null, agora)).toBe(false);
  });
});
