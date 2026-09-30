import { CanActivate, ExecutionContext, Injectable, UnauthorizedException } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { JwtService } from '@nestjs/jwt';
import { PrismaService } from '../prisma/prisma.service';
import { ROTA_PUBLICA } from './publico.decorator';

@Injectable()
export class JwtAuthGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly jwt: JwtService,
    private readonly prisma: PrismaService,
  ) {}

  async canActivate(ctx: ExecutionContext): Promise<boolean> {
    const publica = this.reflector.getAllAndOverride<boolean>(ROTA_PUBLICA, [ctx.getHandler(), ctx.getClass()]);
    if (publica) return true;

    const req = ctx.switchToHttp().getRequest();
    const [tipo, token] = (req.headers.authorization ?? '').split(' ');
    if (tipo !== 'Bearer' || !token) throw new UnauthorizedException('Sessão não iniciada.');

    let sub: number;
    try {
      ({ sub } = await this.jwt.verifyAsync<{ sub: number }>(token));
    } catch {
      throw new UnauthorizedException('Sessão expirada. Entre novamente.');
    }

    // RN10: um usuário inativado perde o acesso imediatamente, mesmo com sessão aberta.
    const usuario = await this.prisma.usuario.findUnique({ where: { id: sub } });
    if (!usuario || !usuario.ativo) throw new UnauthorizedException('Usuário inativo.');
    req.usuario = { id: usuario.id, nome: usuario.nome, email: usuario.email };
    return true;
  }
}
