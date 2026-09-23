<?php

namespace App\Policies;

use App\Models\Microreto;
use App\Models\User;

class MicroretoPolicy
{
    // Superadmin sin restricción. Docente/admin solo sobre microretos de su propio
    // centro (vía la empresa asociada). Otros roles (p.ej. empresa) sin restricción
    // aquí — el middleware de ruta ya limita qué acciones les llegan.
    public function update(User $user, Microreto $microreto): bool
    {
        return $this->perteneceAlCentro($user, $microreto);
    }

    public function delete(User $user, Microreto $microreto): bool
    {
        return $this->perteneceAlCentro($user, $microreto);
    }

    private function perteneceAlCentro(User $user, Microreto $microreto): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isDocente() || $user->isAdmin()) {
            return (bool) ($microreto->empresa && $microreto->empresa->perteneceAlCentroDe($user));
        }

        return true;
    }
}
