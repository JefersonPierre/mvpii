import { BadRequestException, ConflictException, Injectable, NotFoundException } from '@nestjs/common';
import * as bcrypt from 'bcryptjs';
import { randomBytes } from 'crypto';
import { AuditoriaService } from '../auditoria/auditoria.service';
import { AuthService } from '../auth/auth.service';
import { PrismaService } from '../prisma/prisma.service';
import { CriarUsuarioDto, EditarUsuarioDto, FiltroUsuariosDto } from './usuarios.dto';

const ENTIDADE = 'USUARIO';
const CAMPOS_AUDITADOS = ['nome', 'email', 'ativo'];
const SELECAO = { id: true, nome: true, email: true, ativo: true, criadoEm: true } as const;

// UC02 – Cadastrar usuários (RF03, RN08, RN10, RN11)
@Injectable()
export class UsuariosService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly auditoria: AuditoriaService,
    private readonly auth: AuthService,
  ) {}

  listar(filtro: FiltroUsuariosDto) {
    const situacao = filtro.situacao ?? 'ativos';
    const busca = filtro.busca?.trim();
    return this.prisma.usuario.findMany({
      where: {
        ...(situacao === 'todos' ? {} : { ativo: situacao === 'ativos' }),
        ...(busca
          ? {
              OR: [
                { nome: { contains: busca, mode: 'insensitive' } },
                { email: { contains: busca, mode: 'insensitive' } },
              ],
            }
          : {}),
      },
      select: SELECAO,
      orderBy: { nome: 'asc' },
    });
  }

  async buscar(id: number) {
    const usuario = await this.prisma.usuario.findUnique({ where: { id }, select: SELECAO });
    if (!usuario) throw new NotFoundException('Usuário não encontrado.');
    return usuario;
  }

  /** Cadastra o usuário e envia o e-mail para ele criar a própria senha. */
  async criar(dto: CriarUsuarioDto, responsavelId: number) {
    const email = dto.email.trim().toLowerCase();
    await this.garantirEmailUnico(email);

    const usuario = await this.prisma.$transaction(async (tx) => {
      const criado = await tx.usuario.create({
        // Senha aleatória que ninguém conhece: o acesso só começa após o usuário criar a dele.
        data: {
          nome: dto.nome.trim(),
          email,
          senhaHash: await bcrypt.hash(randomBytes(24).toString('hex'), 10),
        },
        select: SELECAO,
      });
      await this.auditoria.registrar(
        {
          usuarioId: responsavelId,
          entidade: ENTIDADE,
          registroId: criado.id,
          acao: 'INCLUSAO',
          depois: criado,
          campos: CAMPOS_AUDITADOS,
        },
        tx,
      );
      return criado;
    });

    await this.auth.enviarLinkSenha(usuario, 'convite');
    return usuario;
  }

  async editar(id: number, dto: EditarUsuarioDto, responsavelId: number) {
    const atual = await this.buscar(id);
    const dados = {
      ...(dto.nome !== undefined ? { nome: dto.nome.trim() } : {}),
      ...(dto.email !== undefined ? { email: dto.email.trim().toLowerCase() } : {}),
    };
    if (dados.email && dados.email !== atual.email) await this.garantirEmailUnico(dados.email);

    return this.prisma.$transaction(async (tx) => {
      const atualizado = await tx.usuario.update({ where: { id }, data: dados, select: SELECAO });
      await this.auditoria.registrar(
        {
          usuarioId: responsavelId,
          entidade: ENTIDADE,
          registroId: id,
          acao: 'ALTERACAO',
          antes: atual,
          depois: atualizado,
          campos: CAMPOS_AUDITADOS,
        },
        tx,
      );
      return atualizado;
    });
  }

  /** RN10: usuário inativo não acessa o sistema. Ninguém pode inativar a si mesmo. */
  async alterarSituacao(id: number, ativo: boolean, responsavelId: number) {
    if (id === responsavelId && !ativo) {
      throw new BadRequestException('Você não pode inativar o seu próprio usuário.');
    }
    const atual = await this.buscar(id);
    if (atual.ativo === ativo) return atual;

    return this.prisma.$transaction(async (tx) => {
      const atualizado = await tx.usuario.update({ where: { id }, data: { ativo }, select: SELECAO });
      await this.auditoria.registrar(
        {
          usuarioId: responsavelId,
          entidade: ENTIDADE,
          registroId: id,
          acao: ativo ? 'REATIVACAO' : 'INATIVACAO',
          antes: atual,
          depois: atualizado,
          campos: CAMPOS_AUDITADOS,
        },
        tx,
      );
      return atualizado;
    });
  }

  async historico(id: number) {
    await this.buscar(id);
    return this.auditoria.listar(ENTIDADE, id);
  }

  /** RN11: o e-mail do usuário é único. */
  private async garantirEmailUnico(email: string) {
    const existente = await this.prisma.usuario.findUnique({ where: { email } });
    if (existente) throw new ConflictException('E-mail já cadastrado.');
  }
}
