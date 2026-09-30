export type Valor = string | number | boolean | Date | null | undefined;

export interface Diferenca {
  campo: string;
  valorAnterior: string | null;
  valorNovo: string | null;
}

function texto(v: Valor): string | null {
  if (v === null || v === undefined || v === '') return null;
  if (v instanceof Date) return v.toISOString();
  return String(v);
}

/** Compara os campos informados e devolve só os que mudaram (RN08: valor anterior e novo). */
export function calcularDiferencas(
  antes: Record<string, Valor>,
  depois: Record<string, Valor>,
  campos: string[],
): Diferenca[] {
  return campos
    .map((campo) => ({ campo, valorAnterior: texto(antes[campo]), valorNovo: texto(depois[campo]) }))
    .filter((d) => d.valorAnterior !== d.valorNovo);
}
