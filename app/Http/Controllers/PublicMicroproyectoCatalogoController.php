<?php

namespace App\Http\Controllers;

use App\Http\Resources\EquipoResolucionPublicaResource;
use App\Http\Resources\MicroproyectoFichaPublicaResource;
use App\Http\Resources\MicroproyectoListadoPublicoResource;
use App\Models\Microproyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Escaparate público de proyectos completados (frontoffice — dualab.es / info.dualab.es).
 * Solo lectura, sin autenticación. Únicamente expone proyectos con estado=completado y
 * visible_publico=true (opt-in manual por proyecto, ver migración
 * 2026_09_23_000001_add_visible_publico_to_microproyectos_table). Ver
 * MicroproyectoFichaPublicaResource para la whitelist de campos — nunca token_empresa,
 * datos de contacto de empresa/centro ni nombres reales de alumnado.
 */
class PublicMicroproyectoCatalogoController extends Controller
{
    private const CACHE_TTL_MINUTOS = 5;

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $familiaId = (int) $request->query('familia_id', 0) ?: null;

        $cacheKey = "publico:microproyectos:index:{$perPage}:" . ($familiaId ?? 'todas');

        $data = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTOS), function () use ($perPage, $familiaId) {
            $query = Microproyecto::with(['empresa', 'familia', 'imagenPortada'])
                ->where('estado', 'completado')
                ->where('visible_publico', true);

            if ($familiaId) {
                $query->where('familia_id', $familiaId);
            }

            return $query->orderByDesc('updated_at')->limit($perPage)->get();
        });

        return MicroproyectoListadoPublicoResource::collection($data);
    }

    public function show(string $uuid)
    {
        $cacheKey = "publico:microproyectos:show:{$uuid}";

        $proyecto = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTOS), function () use ($uuid) {
            return Microproyecto::with(['empresa', 'centroEducativo', 'cicloFormativo', 'familia', 'imagenPortada', 'microreto', 'recursos'])
                ->where('uuid', $uuid)
                ->where('estado', 'completado')
                ->where('visible_publico', true)
                ->first();
        });

        abort_if(!$proyecto, 404);

        return new MicroproyectoFichaPublicaResource($proyecto);
    }

    // "Resolución del alumnado" — ver EquipoResolucionPublicaResource para el porqué de
    // la whitelist: nunca nombre real ni autor de reflexión, solo alias.
    public function equipos(string $uuid)
    {
        $cacheKey = "publico:microproyectos:equipos:{$uuid}";

        $equipos = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTOS), function () use ($uuid) {
            $proyecto = Microproyecto::where('uuid', $uuid)
                ->where('estado', 'completado')
                ->where('visible_publico', true)
                ->first();

            if (!$proyecto) {
                return null;
            }

            return $proyecto->equipos()->with(['miembros', 'reflexiones'])->get();
        });

        abort_if(is_null($equipos), 404);

        return EquipoResolucionPublicaResource::collection($equipos);
    }
}
