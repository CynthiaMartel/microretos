<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistroHorasRequest;
use App\Models\Encuentro;
use App\Models\RegistroHoras;
use App\Services\RegistroHorasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Horas de prácticas de un cronograma (A3). Ver: quien ve el cronograma; anotar y borrar: quien
 * puede editarlo (docente propietario o colaborador con edición, admin del centro, superadmin).
 */
class RegistroHorasController extends Controller
{
    public function __construct(private RegistroHorasService $servicio) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $cronograma = Encuentro::visiblesPara($request->user())->where('tipo', Encuentro::TIPO_PLAN)->findOrFail($id);

        $registros = RegistroHoras::with(['miembro:id,alias', 'registradoPor:id,name'])
            ->where('encuentro_id', $cronograma->id)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->take(500)
            ->get();

        return response()->json($registros->map(fn (RegistroHoras $r) => $this->formato($r)));
    }

    public function store(StoreRegistroHorasRequest $request, int $id): JsonResponse
    {
        $cronograma = $this->servicio->cronogramaEditable($request->user(), $id);
        $registro = $this->servicio->anotar($cronograma, $request->user(), $request->validated());

        return response()->json($this->formato($registro->load(['miembro:id,alias', 'registradoPor:id,name'])), 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $registro = RegistroHoras::findOrFail($id);
        $this->servicio->cronogramaEditable($request->user(), $registro->encuentro_id);
        $registro->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function formato(RegistroHoras $r): array
    {
        return [
            'id'            => $r->id,
            'miembro_id'    => $r->equipo_miembro_id,
            'alias'         => $r->miembro?->alias,
            'fecha'         => $r->fecha?->format('Y-m-d'),
            'horas'         => (float) $r->horas,
            'descripcion'   => $r->descripcion,
            'registrado_por' => $r->registradoPor?->name,
        ];
    }
}
