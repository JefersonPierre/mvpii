// Cliente HTTP da API. O token de sessão fica no sessionStorage (some ao fechar o navegador).

const BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:3000';
const CHAVE_TOKEN = 'token';

export class ErroApi extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message);
  }
}

export const sessao = {
  token: () => sessionStorage.getItem(CHAVE_TOKEN),
  salvar: (token: string) => sessionStorage.setItem(CHAVE_TOKEN, token),
  encerrar: () => sessionStorage.removeItem(CHAVE_TOKEN),
};

export async function api<T>(caminho: string, opcoes: { metodo?: string; corpo?: unknown } = {}): Promise<T> {
  const token = sessao.token();
  const resp = await fetch(`${BASE}${caminho}`, {
    method: opcoes.metodo ?? 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: opcoes.corpo === undefined ? undefined : JSON.stringify(opcoes.corpo),
  });
  const dados = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const msg = Array.isArray(dados.message) ? dados.message.join(' ') : dados.message;
    throw new ErroApi(msg ?? 'Não foi possível concluir a operação.', resp.status);
  }
  return dados as T;
}
