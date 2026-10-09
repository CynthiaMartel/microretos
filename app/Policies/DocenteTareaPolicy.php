<?php

namespace App\Policies;

use App\Models\DocenteTarea;
use App\Models\User;

class DocenteTareaPolicy
{
    // Las tareas son personales: solo su autor las edita o borra (ni admin ni superadmin)

    public function update(User $user, DocenteTarea $tarea): bool
    {
        return (int) $tarea->user_id === (int) $user->id;
    }

    public function delete(User $user, DocenteTarea $tarea): bool
    {
        return (int) $tarea->user_id === (int) $user->id;
    }
}
