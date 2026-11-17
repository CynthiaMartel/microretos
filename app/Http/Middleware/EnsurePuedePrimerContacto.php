<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DuaLab Central → «Primer contacto» (formularios y correo de info@): atención al cliente y superadmin.
 * Los otros espacios de Central (Red DuaLab, Seguimiento de prácticas) siguen con 'superadmin'.
 */
class EnsurePuedePrimerContacto
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->isAtencionCliente() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Acceso restringido a atención al cliente.'], 403);
        }

        return $next($request);
    }
}
