<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Asignar los módulos que imparte un docente: el superadmin a cualquiera; el admin de
     * centro, solo a los docentes de su centro. El propio docente lo hace por /perfil/modulos.
     */
    public function gestionarModulos(User $auth, User $docente): bool
    {
        if (!$docente->isDocente()) {
            return false;
        }
        if ($auth->isSuperAdmin()) {
            return true;
        }

        return $auth->isAdmin()
            && $auth->centro_educativo_id !== null
            && $auth->centro_educativo_id === $docente->centro_educativo_id;
    }
}
