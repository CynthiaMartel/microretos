<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** DuaLab Central → «Seguimiento de prácticas» (alumnado destacado): gestión de prácticas y superadmin. */
class EnsurePuedeGestionarPracticas
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->trabajaEnPracticas() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Acceso restringido a gestión de prácticas.'], 403);
        }

        return $next($request);
    }
}
