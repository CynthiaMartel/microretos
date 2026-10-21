<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlumnoParticipacionResource;
use App\Models\User;
use App\Services\AlumnadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumnadoController extends Controller
{
    /**
     * GET /api/alumnado
     * Participaciones del alumnado en los encuentros visibles para el usuario (los suyos y
     * los compartidos; admin, su centro) — alimenta la vista "Listado de alumnado".
     */
    public function index(Request $request, AlumnadoService $alumnado): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();

        // response()->json(): array plano, sin el envoltorio {"data": [...]} (mismo
        // criterio que EncuentroController::index)
        return response()->json(AlumnoParticipacionResource::collection($alumnado->participaciones($user)));
    }
}
