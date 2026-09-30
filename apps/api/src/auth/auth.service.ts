import { BadRequestException, ForbiddenException, Injectable, UnauthorizedException } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcryptjs';
import { createHash, randomBytes } from 'crypto';
import { MailService } from '../mail/mail.service';
import { PrismaService } from '../prisma/prisma.service';
import {
  MAX_TENTATIVAS,
  MINUTOS_BLOQUEIO,
  MINUTOS_VALIDADE_LINK,
  estaBloqueado,
  registrarFalha,
  senhaAtendeRegra,
} from './regras';

const CREDENCIAIS_INVALIDAS = 'E-mail ou senha incorretos.';

function hashToken(token: string): string {
  return createHash('sha256').update(token).digest('hex');
}

@Injectable()
export class AuthService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly jwt: JwtService,
    private readonly mail: MailService,
    private readonly config: ConfigService,
  ) {}

  /** UC01 / RF01: autentica por e-mail e senha, aplicando RN09 e RN10. */
  async login(email: string, senha: string, agora = new Date()) {
    const usuario = await this.prisma.usuario.findUnique({ where: { email: email.trim().toLowerCase() } });
    if (!usuario) throw new UnauthorizedException(CREDENCIAIS_INVALIDAS);

    if (estaBloqueado(usuario.bloqueadoAte, agora)) {
      throw new ForbiddenException(
        `Acesso bloqueado por excesso de tentativas. Tente novamente após ${formatarHora(usuario.bloqueadoAte!)}.`,
      );
    }

    if (!(await bcrypt.compare(senha, usuario.senhaHash))) {
      const r = registrarFalha(usuario.tentativasFalhas, agora);
      await this.prisma.usuario.update({
        where: { id: usuario.id },
        data: { tentativasFalhas: r.tentativasFalhas, bloqueadoAte: r.bloqueadoAte },
      });
      if (r.bloqueou) {
        throw new ForbiddenException(
          `Acesso bloqueado por ${MINUTOS_BLOQUEIO} minutos após ${MAX_TENTATIVAS} tentativas incorretas.`,
        );
      }
      throw new UnauthorizedException(
        `${CREDENCIAIS_INVALIDAS} Tentativa ${r.tentativasFalhas} de ${MAX_TENTATIVAS}; na quinta o acesso fica bloqueado por ${MINUTOS_BLOQUEIO} minutos.`,
      );
    }

    if (!usuario.ativo) {
      throw new ForbiddenException('Este usuário está inativo. Procure o responsável pelo sistema no laboratório.');
    }

    await this.prisma.usuario.update({
      where: { id: usuario.id },
      data: { tentativasFalhas: 0, bloqueadoAte: null },
    });
    const token = await this.jwt.signAsync({ sub: usuario.id });
    return { token, usuario: { id: usuario.id, nome: usuario.nome, email: usuario.email } };
  }

  /** RF02: envia o link de nova senha. A resposta é a mesma exista ou não o e-mail. */
  async solicitarRecuperacao(email: string, agora = new Date()): Promise<void> {
    const usuario = await this.prisma.usuario.findUnique({ where: { email: email.trim().toLowerCase() } });
    if (!usuario || !usuario.ativo) return;

    const token = randomBytes(32).toString('hex');
    await this.prisma.tokenRecuperacaoSenha.create({
      data: {
        usuarioId: usuario.id,
        tokenHash: hashToken(token),
        expiraEm: new Date(agora.getTime() + MINUTOS_VALIDADE_LINK * 60_000),
      },
    });

    const link = `${this.config.get('WEB_URL', 'http://localhost:5173')}/nova-senha?token=${token}`;
    await this.mail.enviar({
      para: usuario.email,
      assunto: 'Criar nova senha',
      texto:
        `Olá, ${usuario.nome}.\n\nPara criar uma nova senha, acesse o link abaixo. ` +
        `Ele vale por 1 hora.\n\n${link}\n\nSe você não pediu a troca, ignore este e-mail.`,
    });
  }

  /** RF02: troca a senha usando o link recebido por e-mail. */
  async redefinirSenha(token: string, novaSenha: string, agora = new Date()): Promise<void> {
    if (!senhaAtendeRegra(novaSenha)) {
      throw new BadRequestException('A senha deve ter no mínimo 8 caracteres, com letras e números.');
    }
    const registro = await this.prisma.tokenRecuperacaoSenha.findUnique({ where: { tokenHash: hashToken(token) } });
    if (!registro || registro.usadoEm || registro.expiraEm.getTime() <= agora.getTime()) {
      throw new BadRequestException('Link inválido ou expirado. Solicite um novo.');
    }
    await this.prisma.$transaction([
      this.prisma.usuario.update({
        where: { id: registro.usuarioId },
        data: { senhaHash: await bcrypt.hash(novaSenha, 10), tentativasFalhas: 0, bloqueadoAte: null },
      }),
      this.prisma.tokenRecuperacaoSenha.update({ where: { id: registro.id }, data: { usadoEm: agora } }),
    ]);
  }
}

function formatarHora(data: Date): string {
  return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' });
}
