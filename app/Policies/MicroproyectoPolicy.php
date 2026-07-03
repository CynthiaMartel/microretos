<?php

namespace App\Policies;

use App\Models\Microproyecto;
use App\Models\User;

class MicroproyectoPolicy
{
    // El superadmin ve y puede todo sin restricción de centro.
    // Docentes y admins solo pueden actuar sobre proyectos de su propio centro.

    public function viewAny(User $user): bool
    {
        return true; // El middleware 'docente' ya bloquea a empresa
    }

    public function view(User $user, Microproyecto $proyecto): bool
    {
        if ($user->isSuperAdmin()) return true;

        return $user->centro_educativo_id !== null
            && $proyecto->centro_id === $user->centro_educativo_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Microproyecto $proyecto): bool
    {
        return $this->view($user, $proyecto);
    }

    public function delete(User $user, Microproyecto $proyecto): bool
    {
        return $this->view($user, $proyecto);
    }
}
