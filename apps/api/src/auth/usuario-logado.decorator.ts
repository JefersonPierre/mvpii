import { createParamDecorator, ExecutionContext } from '@nestjs/common';

export interface UsuarioLogado {
  id: number;
  nome: string;
  email: string;
}

export const UsuarioAtual = createParamDecorator(
  (_: unknown, ctx: ExecutionContext): UsuarioLogado => ctx.switchToHttp().getRequest().usuario,
);
