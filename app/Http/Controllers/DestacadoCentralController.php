<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarDestacadosRequest;
use App\Http\Requests\MarcarDestacadoRequest;
use App\Http\Resources\DestacadoEncuentroResource;
use App\Models\Encuentro;
use App\Models\EquipoMiembro;
use Illuminate\Http\JsonResponse;

/**
 * DuaLab Central → Alumnado destacado (P3b, solo superadmin por el middleware de la ruta).
 * DuaLab, como empresa intermediaria, elige al alumnado más destacado de los encuentros
 * creativos para las prácticas. Se marca por alias; el docente del encuentro lo ve.
 */
class DestacadoCentralController extends Controller
{
    private const MAX_LISTADO = 100;

    public function index(ListarDestacadosRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        $query = Encuentro::query()
            ->where('tipo', Encuentro::TIPO_CREATIVO)
            ->whereHas('equipos')
            ->with(['docente:id,name', 'valoracionEmpresa', 'microproyecto.empresa', 'equipos.miembros', 'equipos.fases']);

        if (!empty($filtros['buscar'])) {
            $query->whereHas('microproyecto', fn ($q) => $q->where('titulo', 'like', '%' . $filtros['buscar'] . '%'));
        }
        if (!empty($filtros['solo_valorados'])) {
            $query->whereHas('valoracionEmpresa', fn ($q) => $q->whereNotNull('respondido_en'));
        }
        if (!empty($filtros['solo_destacados'])) {
            $query->whereHas('equipos.miembros', fn ($q) => $q->whereNotNull('destacado_en'));
        }

        // Primero los que la empresa ya valoró (lo que DuaLab mira para elegir), luego los más recientes
        $encuentros = $query
            ->orderByRaw('EXISTS (SELECT 1 FROM encuentro_valoraciones_empresa v WHERE v.encuentro_id = encuentros.id AND v.respondido_en IS NOT NULL) DESC')
            ->orderByDesc('fecha')
            ->take(self::MAX_LISTADO)
            ->get();

        return response()->json(DestacadoEncuentroResource::collection($encuentros));
    }

    public function marcar(MarcarDestacadoRequest $request, int $id): JsonResponse
    {
        $miembro = EquipoMiembro::findOrFail($id);
        $destacado = $request->validated()['destacado'];

        $miembro->destacado_en     = $destacado ? ($miembro->destacado_en ?? now()) : null;
        $miembro->destacado_por_id = $destacado ? ($miembro->destacado_por_id ?? $request->user()->id) : null;
        $miembro->save();

        return response()->json(['id' => $miembro->id, 'destacado' => $miembro->esDestacado()]);
    }
}
