<?php

namespace App\Policies;

use App\Models\Microproyecto;
use App\Models\User;

class MicroproyectoPolicy
{
    // El superadmin ve y puede todo sin restricción de centro. Admin gestiona todo su
    // centro. Un docente solo accede a los proyectos que creó o cuyos encuentros
    // comparte (ver Microproyecto::esVisiblePara/esEditablePara).

    public function viewAny(User $user): bool
    {
        return true; // El middleware 'docente' ya bloquea a empresa
    }

    public function view(User $user, Microproyecto $proyecto): bool
    {
        return $proyecto->esVisiblePara($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Microproyecto $proyecto): bool
    {
        return $proyecto->esEditablePara($user);
    }

    public function delete(User $user, Microproyecto $proyecto): bool
    {
        return $proyecto->esEditablePara($user);
    }
}
