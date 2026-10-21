<?php

namespace App\Policies;

use App\Models\Coleta;
use App\Models\Usuario;
use App\Support\Perfis;

// RF26 / RN36: o perfil Coletor vê e registra somente as coletas atribuídas a ele (matriz da seção 7.4: "Próprias").
class ColetaPolicy
{
    public function view(Usuario $usuario, Coleta $coleta): bool
    {
        return $usuario->can('coletas.agenda') && ($usuario->veTodasAsColetas() || $coleta->coletor_id === $usuario->id);
    }

    /** RN32: a coleta é registrada pelo coletor responsável ou por um Administrador. */
    public function registrar(Usuario $usuario, Coleta $coleta): bool
    {
        return $usuario->temPerfil(Perfis::ADMINISTRADOR)
            || ($usuario->temPerfil(Perfis::COLETOR) && $coleta->coletor_id === $usuario->id);
    }
}
