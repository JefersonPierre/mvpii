import { createContext, ReactNode, useCallback, useContext, useEffect, useState } from 'react';
import { api, sessao } from './api';

export interface Usuario {
  id: number;
  nome: string;
  email: string;
}

interface AuthCtx {
  usuario: Usuario | null;
  carregando: boolean;
  entrar: (email: string, senha: string) => Promise<void>;
  sair: () => void;
}

const Contexto = createContext<AuthCtx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [carregando, setCarregando] = useState(Boolean(sessao.token()));

  useEffect(() => {
    if (!sessao.token()) return;
    api<Usuario>('/auth/me')
      .then(setUsuario)
      .catch(() => sessao.encerrar())
      .finally(() => setCarregando(false));
  }, []);

  const entrar = useCallback(async (email: string, senha: string) => {
    const r = await api<{ token: string; usuario: Usuario }>('/auth/login', {
      metodo: 'POST',
      corpo: { email, senha },
    });
    sessao.salvar(r.token);
    setUsuario(r.usuario);
  }, []);

  const sair = useCallback(() => {
    sessao.encerrar();
    setUsuario(null);
  }, []);

  return <Contexto.Provider value={{ usuario, carregando, entrar, sair }}>{children}</Contexto.Provider>;
}

export function useAuth(): AuthCtx {
  const ctx = useContext(Contexto);
  if (!ctx) throw new Error('useAuth fora do AuthProvider');
  return ctx;
}
