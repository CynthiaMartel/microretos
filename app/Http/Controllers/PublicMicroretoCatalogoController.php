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
            $query = Microreto::with(['empresa.centroEducativo', 'empresa.familias'])
                ->where('visible_publico', true);

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
            $reto = Microreto::with(['empresa.centroEducativo', 'empresa.familias'])
                ->where('uuid', $uuid)
                ->where('visible_publico', true)
                ->first();

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
            })->get();
        });

        return FamiliaPublicaResource::collection($data);
    }
}
