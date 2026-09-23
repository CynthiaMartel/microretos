<?php

namespace App\Http\Controllers;

use App\Http\Resources\FamiliaPublicaResource;
use App\Http\Resources\MicroretoFichaResource;
use App\Http\Resources\MicroretoListadoPublicoResource;
use App\Models\Familia;
use App\Models\Microreto;
use App\Services\MicroretoFichaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Escaparate público de microretos (frontoffice — dualab.es / info.dualab.es).
 * Solo lectura, sin autenticación. Únicamente expone retos marcados a propósito
 * como visible_publico=true (opt-in manual por reto, ver migración
 * 2026_09_14_000001_add_visible_publico_to_microretos_table). No se filtra por
 * es_simulado/empresa.es_simulada a propósito: las muestras públicas se generan
 * precisamente con empresas ficticias (vía el generador), para no exponer nunca
 * datos reales de una empresa real en el escaparate — visible_publico es el único
 * gate que importa aquí.
 */
class PublicMicroretoCatalogoController extends Controller
{
    private const CACHE_TTL_MINUTOS = 5;

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $familiaId = (int) $request->query('familia_id', 0) ?: null;

        $cacheKey = "publico:microretos:index:{$perPage}:" . ($familiaId ?? 'todas');

        $data = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTOS), function () use ($perPage, $familiaId) {
            $query = Microreto::with([
                'empresa.centroEducativo',
                'empresa.familias',
                'microproyectos' => self::relacionProyectoCompletado(),
            ])->where('visible_publico', true);
            self::conProyectoCompletadoPublico($query);

            if ($familiaId) {
                $query->whereHas('empresa.familias', fn ($q) => $q->where('familias.id', $familiaId));
            }

            $retos = $query->orderByDesc('created_at')->limit($perPage)->get();

            return MicroretoFichaService::enriquecerLote($retos);
        });

        return MicroretoListadoPublicoResource::collection($data);
    }

    public function show(string $uuid)
    {
        $cacheKey = "publico:microretos:show:{$uuid}";

        $reto = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTOS), function () use ($uuid) {
            $query = Microreto::with([
                'empresa.centroEducativo',
                'empresa.familias',
                'microproyectos' => self::relacionProyectoCompletado(),
            ])->where('uuid', $uuid)
                ->where('visible_publico', true);
            self::conProyectoCompletadoPublico($query);

            $reto = $query->first();

            return $reto ? MicroretoFichaService::enriquecer($reto) : null;
        });

        abort_if(!$reto, 404);

        return new MicroretoFichaResource($reto);
    }

    public function familias()
    {
        $data = Cache::remember('publico:microretos:familias', now()->addMinutes(self::CACHE_TTL_MINUTOS), function () {
            return Familia::whereHas('empresas.microretos', function ($q) {
                $q->where('visible_publico', true);
                self::conProyectoCompletadoPublico($q);
            })->get();
        });

        return FamiliaPublicaResource::collection($data);
    }

    // Un microreto solo entra al escaparate si, además de visible_publico, tiene al
    // menos un proyecto ya completado y también marcado visible_publico — el escaparate
    // muestra el reto como puerta de entrada al proyecto real hecho con él, no como
    // ficha suelta.
    private static function conProyectoCompletadoPublico($query): void
    {
        $query->whereHas('microproyectos', function ($q) {
            $q->where('estado', 'completado')->where('visible_publico', true);
        });
    }

    // Eager load constreñido al mismo criterio (completado + visible_publico), el más
    // reciente primero — así el Resource puede enlazar "Ver proyecto asociado
    // completado" (microproyectos->first()->uuid) sin lanzar otra query.
    private static function relacionProyectoCompletado(): \Closure
    {
        return fn ($q) => $q->where('estado', 'completado')
            ->where('visible_publico', true)
            ->orderByDesc('updated_at');
    }
}
