<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acciones de gestión de DuaLab Central que no son de la base de datos: superadmin y admin_central_dualab
 * (elegir empresa por petición, generar y publicar retos de solicitudes, papelera de solicitudes,
 * historial). Alias 'admin.central'. Base de datos y Papelera general siguen con 'superadmin'.
 */
class EnsureEsAdminCentral
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user->isSuperAdmin() || $user->isAdminCentralDualab())) {
            return response()->json(['success' => false, 'message' => 'Acceso restringido a Admin Central DuaLab y superadmin.'], 403);
        }

        return $next($request);
    }
}
