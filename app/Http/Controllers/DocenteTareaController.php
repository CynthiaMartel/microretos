<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocenteTareaRequest;
use App\Http\Requests\UpdateDocenteTareaRequest;
use App\Http\Resources\DocenteTareaResource;
use App\Models\DocenteTarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DocenteTareaController extends Controller
{
    // Techo del listado sin paginar: es una lista corta de pendientes personales
    private const MAX_LISTADO = 200;

    /**
     * GET /api/tareas — solo las del usuario autenticado, en orden de creación
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $tareas = DocenteTarea::where('user_id', $user->id)
            ->latest()
            ->take(self::MAX_LISTADO)
            ->get()
            ->reverse()
            ->values();

        return DocenteTareaResource::collection($tareas);
    }

    /**
     * POST /api/tareas
     */
    public function store(StoreDocenteTareaRequest $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $tarea = new DocenteTarea($request->validated());
        $tarea->user_id = $user->id;
        $tarea->save();

        return (new DocenteTareaResource($tarea))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/tareas/{tarea}
     */
    public function update(UpdateDocenteTareaRequest $request, DocenteTarea $tarea): DocenteTareaResource
    {
        $this->authorize('update', $tarea);
        $tarea->update($request->validated());

        return new DocenteTareaResource($tarea);
    }

    /**
     * DELETE /api/tareas/{tarea}
     */
    public function destroy(Request $request, DocenteTarea $tarea): Response
    {
        $this->authorize('delete', $tarea);
        $tarea->delete();

        return response()->noContent();
    }
}
