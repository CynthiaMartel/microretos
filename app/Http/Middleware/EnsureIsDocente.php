<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsDocente
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Lista blanca: un rol nuevo (p. ej. comercial) no entra por defecto en las rutas de docente
        if (!$user || !($user->isDocente() || $user->isAdmin() || $user->isSuperAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido a docentes y administradores.',
            ], 403);
        }

        return $next($request);
    }
}
