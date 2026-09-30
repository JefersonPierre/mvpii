import { BadRequestException, ConflictException } from '@nestjs/common';
import { UsuariosService } from './usuarios.service';

describe('UsuariosService', () => {
  let banco: any[];
  let prisma: any;
  let auditoria: any;
  let auth: any;
  let service: UsuariosService;

  const selecionar = (u: any) => ({ id: u.id, nome: u.nome, email: u.email, ativo: u.ativo, criadoEm: u.criadoEm });

  beforeEach(() => {
    banco = [{ id: 1, nome: 'Gerente', email: 'gerente@lab.com', ativo: true, criadoEm: new Date() }];
    const usuario = {
      findUnique: jest.fn(async ({ where }) => {
        const u = banco.find((x) => (where.id ? x.id === where.id : x.email === where.email));
        return u ? selecionar(u) : null;
      }),
      create: jest.fn(async ({ data }) => {
        const u = { id: banco.length + 1, ativo: true, criadoEm: new Date(), ...data };
        banco.push(u);
        return selecionar(u);
      }),
      update: jest.fn(async ({ where, data }) => selecionar(Object.assign(banco.find((x) => x.id === where.id), data))),
    };
    prisma = { usuario, $transaction: jest.fn(async (fn) => fn(prisma)) };
    auditoria = { registrar: jest.fn() };
    auth = { enviarLinkSenha: jest.fn() };
    service = new UsuariosService(prisma, auditoria, auth);
  });

  it('cadastra o usuário, registra o histórico e envia o e-mail para criar a senha', async () => {
    const u = await service.criar({ nome: ' Maria ', email: 'Maria@Lab.com' }, 1);
    expect(u).toMatchObject({ nome: 'Maria', email: 'maria@lab.com', ativo: true });
    expect(auditoria.registrar.mock.calls[0][0]).toMatchObject({ acao: 'INCLUSAO', usuarioId: 1, registroId: u.id });
    expect(auth.enviarLinkSenha).toHaveBeenCalledWith(u, 'convite');
  });

  it('CT04 / RN11: não cadastra e-mail já existente', async () => {
    await expect(service.criar({ nome: 'Outro', email: 'GERENTE@lab.com' }, 1)).rejects.toThrow(ConflictException);
    expect(auth.enviarLinkSenha).not.toHaveBeenCalled();
  });

  it('RN11: não permite trocar o e-mail para um que já existe', async () => {
    banco.push({ id: 2, nome: 'Técnico', email: 'tecnico@lab.com', ativo: true });
    await expect(service.editar(2, { email: 'gerente@lab.com' }, 1)).rejects.toThrow('E-mail já cadastrado.');
  });

  it('RN08: registra o valor anterior e o novo na edição', async () => {
    banco.push({ id: 2, nome: 'Técnico', email: 'tecnico@lab.com', ativo: true });
    await service.editar(2, { nome: 'Técnico Chefe' }, 1);
    const r = auditoria.registrar.mock.calls[0][0];
    expect(r.acao).toBe('ALTERACAO');
    expect(r.antes.nome).toBe('Técnico');
    expect(r.depois.nome).toBe('Técnico Chefe');
  });

  it('RN10: inativa e reativa o usuário, registrando a ação', async () => {
    banco.push({ id: 2, nome: 'Técnico', email: 'tecnico@lab.com', ativo: true });
    expect((await service.alterarSituacao(2, false, 1)).ativo).toBe(false);
    expect(auditoria.registrar.mock.calls[0][0].acao).toBe('INATIVACAO');
    expect((await service.alterarSituacao(2, true, 1)).ativo).toBe(true);
    expect(auditoria.registrar.mock.calls[1][0].acao).toBe('REATIVACAO');
  });

  it('não deixa o usuário inativar a si mesmo', async () => {
    await expect(service.alterarSituacao(1, false, 1)).rejects.toThrow(BadRequestException);
  });
});
