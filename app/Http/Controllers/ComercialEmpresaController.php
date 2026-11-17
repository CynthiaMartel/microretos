<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirmarEmpresaComercialRequest;
use App\Http\Requests\GenerarRetosComercialRequest;
use App\Http\Requests\StoreEmpresaComercialRequest;
use App\Http\Requests\EnviarFormularioComercialRequest;
use App\Http\Requests\SugerirRespuestasComercialRequest;
use App\Http\Requests\TranscribirAudioRequest;
use App\Http\Requests\UpdateDiagnosticoComercialRequest;
use App\Http\Requests\UpdateEmpresaComercialRequest;
use App\Http\Resources\EmpresaCaptacionResource;
use App\Http\Resources\MicroretoBorradorResource;
use App\Models\CaptacionOrigen;
use App\Models\EmpresaCaptacion;
use App\Models\User;
use App\Services\BorradorRetoService;
use App\Services\CaptacionEmpresaService;
use App\Services\EntrevistaIAService;
use App\Services\FormularioEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * App comercial (comercial.dualab.es), rutas /comercial/*: el comercial da de alta la
 * empresa, hace la entrevista diagnóstica, genera retos de demostración y recoge la firma.
 * Todo se guarda como captación (empresa_captaciones) hasta que DuaLab la valida; {id} es el
 * de la captación. Cada una solo la ve y edita quien la captó (EmpresaCaptacionPolicy).
 */
class ComercialEmpresaController extends Controller
{
    private const MAX_LISTADO = 50;

    public function __construct(
        private CaptacionEmpresaService $captacion,
        private BorradorRetoService $borradores,
        private FormularioEmpresaService $formulario,
        private EntrevistaIAService $ia,
    ) {}

    /** GET /comercial/empresas — las suyas, las más recientes primero */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user  = $request->user();
        $query = EmpresaCaptacion::where('origen_id', CaptacionOrigen::ENTREVISTA)
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
        $captacion = $this->captacion->crear($user, $request->validated());

        return (new EmpresaCaptacionResource($captacion))->response()->setStatusCode(201);
    }

    /** GET /comercial/empresas/{id} */
    public function show(Request $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('ver', $captacion, $request)) return $denegado;

        return new EmpresaCaptacionResource($captacion->load('firmas.consentimientos'));
    }

    /** PUT /comercial/empresas/{id} — datos de la empresa (antes de firmar) */
    public function update(UpdateEmpresaComercialRequest $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        $this->captacion->actualizar($captacion, $request->validated());

        return new EmpresaCaptacionResource($captacion->refresh());
    }

    /** PATCH /comercial/empresas/{id}/diagnostico — la entrevista (P1–P5) */
    public function diagnostico(UpdateDiagnosticoComercialRequest $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        $this->captacion->actualizarDiagnostico($captacion, $request->validated());

        return new EmpresaCaptacionResource($captacion);
    }

    /** POST /comercial/empresas/{id}/sugerencias — ideas de la IA para las preguntas de la entrevista */
    public function sugerencias(SugerirRespuestasComercialRequest $request, int $id): JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        $sugerencias = $this->ia->sugerencias($captacion, $request->validated());
        if ($sugerencias === null) {
            return response()->json(['error' => 'La IA no ha respondido. Inténtalo de nuevo en un momento.'], 502);
        }

        return response()->json(['data' => $sugerencias]);
    }

    /** POST /comercial/transcribir — dictado de pago: el audio se pasa a texto y se descarta */
    public function transcribir(TranscribirAudioRequest $request): JsonResponse
    {
        $texto = $this->ia->transcribir($request->file('audio'));
        if ($texto === null) {
            return response()->json(['error' => 'No se ha podido pasar a texto. Inténtalo de nuevo o escribe la respuesta.'], 502);
        }

        return response()->json(['data' => ['texto' => $texto]]);
    }

    /** POST /comercial/empresas/{id}/formulario — envía a la empresa el enlace del formulario */
    public function enviarFormulario(EnviarFormularioComercialRequest $request, int $id): JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        /** @var User $user  ruta bajo auth:sanctum */
        $user   = $request->user();
        $enlace = $this->formulario->enviar($captacion, $user, $request->validated('email'));

        return response()->json([
            'data'   => new EmpresaCaptacionResource($captacion->refresh()),
            // Solo ahora se puede ver el enlace en claro (de la BD no se puede recuperar)
            'enlace' => $enlace,
        ]);
    }

    /** POST /comercial/formularios — entrevista nueva por correo: solo con el email de la empresa */
    public function crearFormulario(EnviarFormularioComercialRequest $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        [$captacion, $enlace] = $this->formulario->crearYEnviar($user, $request->validated('email'));

        return response()->json([
            'data'   => new EmpresaCaptacionResource($captacion->refresh()),
            'enlace' => $enlace,
        ], 201);
    }

    /** DELETE /comercial/empresas/{id}/formulario — anula el enlace enviado */
    public function anularFormulario(Request $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        $this->formulario->anular($captacion);

        return new EmpresaCaptacionResource($captacion->refresh());
    }

    /** POST /comercial/empresas/{id}/generar-retos — demostración: se guardan como borradores */
    public function generarRetos(GenerarRetosComercialRequest $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        /** @var User $user  ruta bajo auth:sanctum */
        $user    = $request->user();
        $creados = $this->borradores->generar($captacion, $user, $request->validated());

        return MicroretoBorradorResource::collection($creados);
    }

    /** GET /comercial/empresas/{id}/borradores — retos generados en la entrevista */
    public function borradores(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('ver', $captacion, $request)) return $denegado;

        return MicroretoBorradorResource::collection($captacion->borradores()->latest()->take(self::MAX_LISTADO)->get());
    }

    /** GET /comercial/textos-legales — los que se muestran y se firman en la tablet */
    public function textosLegales(): JsonResponse
    {
        return response()->json($this->captacion->textosVigentes());
    }

    /** POST /comercial/empresas/{id}/firma */
    public function firmar(FirmarEmpresaComercialRequest $request, int $id): EmpresaCaptacionResource|JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        if ($denegado = $this->denegado('editar', $captacion, $request)) return $denegado;

        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->captacion->firmar($captacion, $user, $request->validated(), $request->ip(), $request->userAgent());

        return new EmpresaCaptacionResource($captacion->refresh()->load('firmas.consentimientos'));
    }

    /** Respuesta de error si la Policy lo deniega (y se registra el intento), o null si puede. */
    private function denegado(string $permiso, EmpresaCaptacion $captacion, Request $request): ?JsonResponse
    {
        $decision = Gate::inspect($permiso, $captacion);
        if ($decision->allowed()) return null;

        Log::warning('Acceso denegado a empresa captada.', [
            'empresa_captacion_id' => $captacion->id,
            'user_id'    => $request->user()?->id,
            'permiso'    => $permiso,
        ]);
        return response()->json(['error' => $decision->message() ?: 'Empresa no encontrada'], $decision->status() ?? 403);
    }
}
