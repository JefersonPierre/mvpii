import { Injectable } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import { calcularDiferencas, Valor } from './diferencas';

export type Acao = 'INCLUSAO' | 'ALTERACAO' | 'INATIVACAO' | 'REATIVACAO';

interface Registro {
  usuarioId: number;
  entidade: string;
  registroId: number;
  acao: Acao;
  antes?: Record<string, Valor>;
  depois: Record<string, Valor>;
  campos: string[];
}

// RN08: toda inclusão, alteração e inativação registra usuário, data/hora e valores anterior e novo.
@Injectable()
export class AuditoriaService {
  constructor(private readonly prisma: PrismaService) {}

  /** Grava o histórico. Use o `tx` da transação da operação para que tudo seja salvo junto. */
  async registrar(r: Registro, tx: Prisma.TransactionClient = this.prisma): Promise<void> {
    const diferencas = calcularDiferencas(r.antes ?? {}, r.depois, r.campos);
    if (diferencas.length === 0) return;
    await tx.registroAuditoria.createMany({
      data: diferencas.map((d) => ({
        usuarioId: r.usuarioId,
        entidade: r.entidade,
        registroId: r.registroId,
        acao: r.acao,
        ...d,
      })),
    });
  }

  listar(entidade: string, registroId: number) {
    return this.prisma.registroAuditoria.findMany({
      where: { entidade, registroId },
      orderBy: [{ dataHora: 'desc' }, { id: 'desc' }],
      include: { usuario: { select: { nome: true } } },
    });
  }
}
