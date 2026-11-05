<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Rutas /comercial/* (app comercial): el comercial y el superadmin, que la supervisa
class EnsureIsComercial
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->isComercial() || $user->isSuperAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido a comerciales.',
            ], 403);
        }

        return $next($request);
    }
}
