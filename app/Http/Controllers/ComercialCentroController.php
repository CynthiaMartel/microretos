<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCentroComercialRequest;
use App\Http\Resources\CentroCaptacionResource;
use App\Models\CaptacionOrigen;
use App\Models\CentroCaptacion;
use App\Models\User;
use App\Services\CaptacionCentroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * App comercial, rutas /comercial/centros: entrevista a centros educativos. Mismos datos que
 * el formulario de centros de la web; queda como captación pendiente de que DuaLab la valide.
 */
class ComercialCentroController extends Controller
{
    private const MAX_LISTADO = 50;

    public function __construct(private CaptacionCentroService $captacion) {}

    /** GET /comercial/centros — los suyos, los más recientes primero */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user  = $request->user();
        $query = CentroCaptacion::where('origen_id', CaptacionOrigen::ENTREVISTA)->with('centroEducativo:id,nombre')->latest()->take(self::MAX_LISTADO);
        if (!$user->isSuperAdmin()) {
            $query->where('creada_por_user_id', $user->id);
        }

        return CentroCaptacionResource::collection($query->get());
    }

    /** POST /comercial/centros */
    public function store(StoreCentroComercialRequest $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user      = $request->user();
        $captacion = $this->captacion->crearDesdeEntrevista($user, $request->validated());

        return (new CentroCaptacionResource($captacion->load('consentimientos')))->response()->setStatusCode(201);
    }

    /** GET /comercial/centros/{id} */
    public function show(Request $request, int $id): CentroCaptacionResource|JsonResponse
    {
        $captacion = CentroCaptacion::findOrFail($id);
        $decision  = Gate::inspect('ver', $captacion);
        if ($decision->denied()) {
            Log::warning('Acceso denegado a centro captado.', ['centro_captacion_id' => $captacion->id, 'user_id' => $request->user()?->id]);
            return response()->json(['error' => 'Centro no encontrado'], 404);
        }

        return new CentroCaptacionResource($captacion->load(['consentimientos', 'centroEducativo:id,nombre']));
    }

    /** GET /comercial/centros/textos-legales — los que acepta el centro en la tablet */
    public function textosLegales(): JsonResponse
    {
        return response()->json($this->captacion->textosVigentes());
    }
}
