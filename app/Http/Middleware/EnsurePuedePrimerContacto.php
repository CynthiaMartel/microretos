<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DuaLab Central → «Red DuaLab» (formularios y correo de info@, captaciones): roles red_dualab y
 * central_dualab, y superadmin. Alias 'primer.contacto' y 'red.dualab'. Base de datos, Usuarios y Papelera siguen con
 * 'superadmin'; Seguimiento de prácticas, con 'practicas'.
 */
class EnsurePuedePrimerContacto
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->trabajaEnRedDualab() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Acceso restringido a Red DuaLab.'], 403);
        }

        return $next($request);
    }
}
