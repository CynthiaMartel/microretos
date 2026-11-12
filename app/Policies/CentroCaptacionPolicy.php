<?php

namespace App\Policies;

use App\Models\CentroCaptacion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * App comercial (/comercial/centros): cada comercial solo ve los centros que ha entrevistado
 * él; el superadmin, todos. Lo ajeno responde como inexistente (404).
 */
class CentroCaptacionPolicy
{
    public function ver(User $user, CentroCaptacion $captacion): Response
    {
        return $user->isSuperAdmin() || ($user->isComercial() && $captacion->creada_por_user_id === $user->id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
