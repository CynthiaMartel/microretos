<?php

namespace App\Policies;

use App\Models\DocenteNota;
use App\Models\User;

class DocenteNotaPolicy
{
    // Las notas son personales: solo su autor las edita o borra (ni admin ni superadmin)

    public function update(User $user, DocenteNota $nota): bool
    {
        return (int) $nota->user_id === (int) $user->id;
    }

    public function delete(User $user, DocenteNota $nota): bool
    {
        return (int) $nota->user_id === (int) $user->id;
    }
}
