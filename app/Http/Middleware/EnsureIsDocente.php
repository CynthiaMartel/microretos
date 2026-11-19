<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsDocente
{
    /**
     * Gestión de prácticas (DuaLab Central → Seguimiento de prácticas) no es docente: solo entra en
     * estas rutas de prácticas (agenda, horas, planes y su cronograma, y el listado de encuentros para
     * elegir alumnado). Lo que ve y edita dentro lo limitan Encuentro/Microproyecto (solo planes).
     */
    private const RUTAS_GESTION_PRACTICAS = [
        'GET api/agenda-practicas',
        'GET api/agenda-practicas/{id}/horas',
        'POST api/agenda-practicas/{id}/horas',
        'DELETE api/agenda-practicas/horas/{id}',
        'GET api/encuentros',
        'GET api/startup/proyectos',
        'GET api/startup/proyectos/{uuid}',
        'POST api/startup/proyectos/{uuid}/validar-docente',
        'POST api/planes',
        'POST api/planes/{uuid}/cronograma',
    ];

    /**
     * Admin Central DuaLab genera y guarda retos de las solicitudes de docentes desde Central (tarjeta de
     * cada petición); es lo único que usa de las rutas de docente. Guardar retos de una solicitud lo
     * acota MicroretoIAController::guardarLote.
     */
    private const RUTAS_ADMIN_CENTRAL = [
        'POST api/generar-microreto',
        'POST api/guardar-microretos-lote',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Lista blanca: un rol nuevo (p. ej. comercial) no entra por defecto en las rutas de docente
        if ($user && ($user->isDocente() || $user->isAdmin() || $user->isSuperAdmin())) {
            return $next($request);
        }
        if ($user?->trabajaEnPracticas() && $this->esRutaDe($request, self::RUTAS_GESTION_PRACTICAS)) {
            return $next($request);
        }
        if ($user?->isAdminCentralDualab() && $this->esRutaDe($request, self::RUTAS_ADMIN_CENTRAL)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Acceso restringido a docentes y administradores.',
        ], 403);
    }

    /** @param array<int, string> $rutas */
    private function esRutaDe(Request $request, array $rutas): bool
    {
        $metodo = $request->method() === 'HEAD' ? 'GET' : $request->method();

        return in_array($metodo.' '.$request->route()?->uri(), $rutas, true);
    }
}
