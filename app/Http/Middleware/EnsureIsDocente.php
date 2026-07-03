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

        if (!$user || $user->isEmpresa()) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido a docentes y administradores.',
            ], 403);
        }

        return $next($request);
    }
}
