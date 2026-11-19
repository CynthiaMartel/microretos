<?php

namespace App\Policies;

use App\Models\EmpresaCaptacion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * App comercial (/comercial/*): cada comercial solo ve las captaciones que ha hecho él; el
 * superadmin, todas. Lo que no es suyo responde como inexistente (404), no como prohibido.
 */
class EmpresaCaptacionPolicy
{
    public function ver(User $user, EmpresaCaptacion $captacion): Response
    {
        return $user->isSuperAdmin() || ($user->isComercial() && $captacion->creada_por_user_id === $user->id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    // Datos, diagnóstico, retos y firma: solo mientras la entrevista no está firmada. Lo
    // firmado no puede cambiar después (la firma guarda una copia y su huella).
    public function editar(User $user, EmpresaCaptacion $captacion): Response
    {
        $ver = $this->ver($user, $captacion);
        if ($ver->denied()) {
            return $ver;
        }

        return $captacion->esBorrador()
            ? Response::allow()
            : Response::deny('La entrevista ya está firmada: sus datos no se pueden cambiar. Ponte en contacto con DUALAB para cualquier corrección.');
    }

    // A la papelera: solo las entrevistas en curso. Lo firmado es evidencia legal y solo lo
    // gestiona DuaLab (Central → Captaciones).
    public function borrar(User $user, EmpresaCaptacion $captacion): Response
    {
        $ver = $this->ver($user, $captacion);
        if ($ver->denied()) {
            return $ver;
        }

        return $captacion->esBorrador()
            ? Response::allow()
            : Response::deny('Solo se pueden borrar las entrevistas en curso. Las firmadas las gestiona DuaLab.');
    }
}
