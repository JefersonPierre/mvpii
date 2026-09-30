import { Center, Loader } from '@mantine/core';
import { Navigate, Route, Routes } from 'react-router-dom';
import { Layout } from './components/Layout';
import { useAuth } from './lib/auth';
import { EmConstrucao } from './pages/EmConstrucao';
import { Inicio } from './pages/Inicio';
import { Login } from './pages/Login';
import { NovaSenha } from './pages/NovaSenha';
import { RecuperarSenha } from './pages/RecuperarSenha';

export function App() {
  const { usuario, carregando } = useAuth();

  if (carregando) {
    return (
      <Center h="100vh">
        <Loader />
      </Center>
    );
  }

  if (!usuario) {
    return (
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/recuperar-senha" element={<RecuperarSenha />} />
        <Route path="/nova-senha" element={<NovaSenha />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    );
  }

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Inicio />} />
        <Route path="orcamentos" element={<EmConstrucao titulo="Orçamentos" />} />
        <Route path="clientes" element={<EmConstrucao titulo="Clientes" />} />
        <Route path="catalogo" element={<EmConstrucao titulo="Catálogo técnico" />} />
        <Route path="usuarios" element={<EmConstrucao titulo="Usuários" />} />
        <Route path="configuracoes" element={<EmConstrucao titulo="Configurações" />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
