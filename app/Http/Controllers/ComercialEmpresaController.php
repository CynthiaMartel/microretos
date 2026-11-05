<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirmarEmpresaComercialRequest;
use App\Http\Requests\GenerarRetosComercialRequest;
use App\Http\Requests\StoreEmpresaComercialRequest;
use App\Http\Requests\UpdateDiagnosticoEmpresaRequest;
use App\Http\Requests\UpdateEmpresaComercialRequest;
use App\Http\Resources\EmpresaCaptacionResource;
use App\Http\Resources\MicroretoBorradorResource;
use App\Models\Empresa;
use App\Models\User;
use App\Services\BorradorRetoService;
use App\Services\CaptacionEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * App comercial (comercial.dualab.es), rutas /comercial/*: el comercial da de alta la
 * empresa, hace la entrevista diagnóstica, genera retos de demostración y recoge la firma.
 * Cada empresa solo la ve y edita quien la captó (EmpresaPolicy::verCaptacion/editarCaptacion).
 */
class ComercialEmpresaController extends Controller
{
    private const MAX_LISTADO = 50;

    public function __construct(
        private CaptacionEmpresaService $captacion,
        private BorradorRetoService $borradores,
    ) {}

    /** GET /comercial/empresas — las suyas, las más recientes primero */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user  = $request->user();
        $query = Empresa::whereNotNull('estado_captacion')
            ->with('familias:id,nombre')
            ->latest()
            ->take(self::MAX_LISTADO);
        if (!$user->isSuperAdmin()) {
            $query->where('creada_por_user_id', $user->id);
        }

        return EmpresaCaptacionResource::collection($query->get());
    }

    /** POST /comercial/empresas */
    public function store(StoreEmpresaComercialRequest $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user    = $request->user();
        $empresa = $this->captacion->crear($user, $request->validated());

        return (new EmpresaCaptacionResource($empresa->load('familias:id,nombre')))
            ->response()->setStatusCode(201);
    }

    /** GET /comercial/empresas/{id} */
    public function show(Request $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        if ($denegado = $this->denegado('verCaptacion', $empresa, $request)) return $denegado;

        return new EmpresaCaptacionResource($empresa->load(['familias:id,nombre', 'firmas.consentimientos']));
    }

    /** PUT /comercial/empresas/{id} — datos de la empresa (antes de firmar) */
    public function update(UpdateEmpresaComercialRequest $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        if ($denegado = $this->denegado('editarCaptacion', $empresa, $request)) return $denegado;

        $this->captacion->actualizar($empresa, $request->validated());

        return new EmpresaCaptacionResource($empresa->refresh()->load('familias:id,nombre'));
    }

    /** PATCH /comercial/empresas/{id}/diagnostico — la entrevista (P1–P5) */
    public function diagnostico(UpdateDiagnosticoEmpresaRequest $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        if ($denegado = $this->denegado('editarCaptacion', $empresa, $request)) return $denegado;

        $this->captacion->actualizarDiagnostico($empresa, $request->validated());

        return new EmpresaCaptacionResource($empresa->load('familias:id,nombre'));
    }

    /** POST /comercial/empresas/{id}/generar-retos — demostración: se guardan como borradores */
    public function generarRetos(GenerarRetosComercialRequest $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        if ($denegado = $this->denegado('editarCaptacion', $empresa, $request)) return $denegado;

        /** @var User $user  ruta bajo auth:sanctum */
        $user    = $request->user();
        $creados = $this->borradores->generar($empresa, $user, $request->validated());

        return MicroretoBorradorResource::collection($creados);
    }

    /** GET /comercial/empresas/{id}/borradores — retos generados en la entrevista */
    public function borradores(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        if ($denegado = $this->denegado('verCaptacion', $empresa, $request)) return $denegado;

        return MicroretoBorradorResource::collection($empresa->borradores()->latest()->take(self::MAX_LISTADO)->get());
    }

    /** GET /comercial/textos-legales — los que se muestran y se firman en la tablet */
    public function textosLegales(): JsonResponse
    {
        return response()->json($this->captacion->textosVigentes());
    }

    /** POST /comercial/empresas/{id}/firma */
    public function firmar(FirmarEmpresaComercialRequest $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        if ($denegado = $this->denegado('editarCaptacion', $empresa, $request)) return $denegado;

        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->captacion->firmar($empresa, $user, $request->validated(), $request->ip(), $request->userAgent());

        return new EmpresaCaptacionResource($empresa->refresh()->load(['familias:id,nombre', 'firmas.consentimientos']));
    }

    /** Respuesta de error si la Policy lo deniega (y se registra el intento), o null si puede. */
    private function denegado(string $permiso, Empresa $empresa, Request $request): ?JsonResponse
    {
        $decision = Gate::inspect($permiso, $empresa);
        if ($decision->allowed()) return null;

        Log::warning('Acceso denegado a empresa captada.', [
            'empresa_id' => $empresa->id,
            'user_id'    => $request->user()?->id,
            'permiso'    => $permiso,
        ]);
        return response()->json(['error' => $decision->message() ?: 'Empresa no encontrada'], $decision->status() ?? 403);
    }
}
