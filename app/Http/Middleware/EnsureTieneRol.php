<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lista blanca de roles escrita en la propia ruta, para lecturas que comparten varios roles:
 * ->middleware('rol:superadmin,docente,empresa'). Así routes/api.php muestra quién entra; el controlador
 * sigue acotando qué ve cada uno (su centro, su empresa…). Alias 'rol'.
 */
class EnsureTieneRol
{
    private const ROLES = [
        'superadmin'           => User::ROLE_SUPERADMIN,
        'docente'              => User::ROLE_DOCENTE,
        'empresa'              => User::ROLE_EMPRESA,
        'admin'                => User::ROLE_ADMIN,
        'comercial'            => User::ROLE_COMERCIAL,
        'red_dualab'           => User::ROLE_RED_DUALAB,
        'gestion_practicas'    => User::ROLE_GESTION_PRACTICAS,
        'admin_central_dualab' => User::ROLE_ADMIN_CENTRAL_DUALAB,
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $permitidos = array_map(fn (string $r) => self::ROLES[$r] ?? throw new \InvalidArgumentException("Rol desconocido en la ruta: {$r}"), $roles);
        $user = $request->user();

        if (!$user || !in_array((int) $user->role, $permitidos, true)) {
            return response()->json(['success' => false, 'message' => 'Tu cuenta no tiene acceso a este recurso.'], 403);
        }

        return $next($request);
    }
}
