<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCronogramaPlanRequest;
use App\Http\Requests\StorePlanProyectoRequest;
use App\Http\Resources\EncuentroResource;
use App\Models\Microproyecto;
use App\Services\CronogramaPlanService;
use App\Services\PlanProyectoService;
use Illuminate\Http\JsonResponse;

/**
 * Plan de proyecto (etapa 2, P4). El listado y la edición del plan reutilizan los de la propuesta
 * (/startup/proyectos?etapa=plan y el asistente); aquí solo lo propio: crearlo y su cronograma.
 * Lo usan el docente y DuaLab (superadmin) desde Central.
 */
class PlanProyectoController extends Controller
{
    public function store(StorePlanProyectoRequest $request, PlanProyectoService $servicio): JsonResponse
    {
        $plan = $servicio->crear($request->user(), $request->validated());

        return response()->json([
            'uuid'   => $plan->uuid,
            'titulo' => $plan->titulo,
            'etapa'  => $plan->etapa,
            'estado' => $plan->estado,
        ], 201);
    }

    public function storeCronograma(StoreCronogramaPlanRequest $request, string $uuid, CronogramaPlanService $servicio): JsonResponse
    {
        $plan = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $encuentro = $servicio->crear($plan, $request->user(), $request->validated());
        $encuentro->load(['microproyecto.microreto', 'microproyecto.familia', 'microproyecto.empresa', 'colaboradores']);

        return response()->json(new EncuentroResource($encuentro), 201);
    }
}
