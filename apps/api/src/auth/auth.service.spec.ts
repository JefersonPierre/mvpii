import { BadRequestException, ForbiddenException, UnauthorizedException } from '@nestjs/common';
import * as bcrypt from 'bcryptjs';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  const agora = new Date('2026-09-30T10:00:00Z');
  let usuario: any;
  let prisma: any;
  let mail: any;
  let service: AuthService;

  beforeEach(async () => {
    usuario = {
      id: 1,
      nome: 'Gerente',
      email: 'gerente@laboratorio.local',
      senhaHash: await bcrypt.hash('Senha1234', 4),
      tentativasFalhas: 0,
      bloqueadoAte: null,
      ativo: true,
    };
    prisma = {
      usuario: {
        findUnique: jest.fn(async () => usuario),
        update: jest.fn(async ({ data }) => Object.assign(usuario, data)),
      },
      tokenRecuperacaoSenha: { create: jest.fn(), findUnique: jest.fn(), update: jest.fn() },
      $transaction: jest.fn(async (ops) => Promise.all(ops)),
    };
    mail = { enviar: jest.fn() };
    const jwt = { signAsync: jest.fn(async () => 'token-jwt') };
    const config = { get: (_: string, padrao: string) => padrao };
    service = new AuthService(prisma, jwt as any, mail, config as any);
  });

  it('CT01: entra com e-mail e senha válidos e zera as tentativas', async () => {
    usuario.tentativasFalhas = 2;
    const r = await service.login(' Gerente@Laboratorio.local ', 'Senha1234', agora);
    expect(r.token).toBe('token-jwt');
    expect(r.usuario).toEqual({ id: 1, nome: 'Gerente', email: 'gerente@laboratorio.local' });
    expect(usuario.tentativasFalhas).toBe(0);
  });

  it('recusa e-mail desconhecido com mensagem genérica', async () => {
    prisma.usuario.findUnique.mockResolvedValueOnce(null);
    await expect(service.login('x@y.com', 'Senha1234', agora)).rejects.toThrow(UnauthorizedException);
  });

  it('CT02: bloqueia na quinta senha incorreta seguida e recusa até o fim do bloqueio', async () => {
    for (let i = 1; i <= 4; i++) {
      await expect(service.login(usuario.email, 'errada', agora)).rejects.toThrow(`Tentativa ${i} de 5`);
    }
    await expect(service.login(usuario.email, 'errada', agora)).rejects.toThrow(ForbiddenException);
    expect(usuario.bloqueadoAte).toEqual(new Date('2026-09-30T10:15:00Z'));

    // mesmo com a senha certa, continua bloqueado dentro dos 15 minutos
    await expect(service.login(usuario.email, 'Senha1234', new Date('2026-09-30T10:10:00Z'))).rejects.toThrow(
      'bloqueado',
    );
    const r = await service.login(usuario.email, 'Senha1234', new Date('2026-09-30T10:16:00Z'));
    expect(r.token).toBe('token-jwt');
  });

  it('RN10: usuário inativo não entra', async () => {
    usuario.ativo = false;
    await expect(service.login(usuario.email, 'Senha1234', agora)).rejects.toThrow('inativo');
  });

  it('RF02: envia o link de recuperação com validade de 1 hora', async () => {
    await service.solicitarRecuperacao(usuario.email, agora);
    const dados = prisma.tokenRecuperacaoSenha.create.mock.calls[0][0].data;
    expect(dados.expiraEm).toEqual(new Date('2026-09-30T11:00:00Z'));
    expect(mail.enviar).toHaveBeenCalledWith(expect.objectContaining({ para: usuario.email }));
    expect(mail.enviar.mock.calls[0][0].texto).toContain('/nova-senha?token=');
  });

  it('RF02: não envia nada para e-mail desconhecido', async () => {
    prisma.usuario.findUnique.mockResolvedValueOnce(null);
    await service.solicitarRecuperacao('x@y.com', agora);
    expect(mail.enviar).not.toHaveBeenCalled();
  });

  it('RF02: recusa link expirado e senha fora da RN09', async () => {
    prisma.tokenRecuperacaoSenha.findUnique.mockResolvedValue({
      id: 9,
      usuarioId: 1,
      usadoEm: null,
      expiraEm: new Date('2026-09-30T09:59:00Z'),
    });
    await expect(service.redefinirSenha('abc', 'curta', agora)).rejects.toThrow(BadRequestException);
    await expect(service.redefinirSenha('abc', 'NovaSenha123', agora)).rejects.toThrow('expirado');
  });

  it('RF02: troca a senha com link válido', async () => {
    prisma.tokenRecuperacaoSenha.findUnique.mockResolvedValue({
      id: 9,
      usuarioId: 1,
      usadoEm: null,
      expiraEm: new Date('2026-09-30T10:30:00Z'),
    });
    await service.redefinirSenha('abc', 'NovaSenha123', agora);
    expect(await bcrypt.compare('NovaSenha123', usuario.senhaHash)).toBe(true);
    expect(prisma.tokenRecuperacaoSenha.update).toHaveBeenCalledWith({ where: { id: 9 }, data: { usadoEm: agora } });
  });
});
