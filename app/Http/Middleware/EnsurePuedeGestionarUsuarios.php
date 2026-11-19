<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestión de usuarios (/admin/usuarios): superadmin y admin_central_dualab (todas las cuentas; superadmins
 * solo el superadmin) y admin de centro (las de su centro, AdminUserController). Alias 'usuarios'. No usa
 * 'admin' para no dar a admin_central_dualab las demás rutas de administración de centro.
 */
class EnsurePuedeGestionarUsuarios
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->gestionaTodosLosUsuarios() || $user->isAdmin())) {
            return response()->json(['success' => false, 'message' => 'Acceso restringido a administradores.'], 403);
        }

        return $next($request);
    }
}
