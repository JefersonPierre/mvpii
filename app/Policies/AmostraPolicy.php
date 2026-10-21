<?php

namespace App\Policies;

use App\Models\Amostra;
use App\Models\Usuario;

// RF36 / RN36: quem só tem o perfil Coletor consulta apenas as amostras das próprias coletas.
class AmostraPolicy
{
    public function view(Usuario $usuario, Amostra $amostra): bool
    {
        return $usuario->can('amostras.consultar')
            && ($usuario->veTodasAsColetas() || $amostra->coleta->coletor_id === $usuario->id);
    }
}
