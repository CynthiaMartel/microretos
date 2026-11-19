<?php

namespace App\Http\Controllers;

use App\Http\Requests\DescartarCaptacionRequest;
use App\Http\Requests\ValidarCentroCaptacionRequest;
use App\Http\Requests\PublicarBorradorRequest;
use App\Http\Requests\ValidarEmpresaCaptacionRequest;
use App\Http\Resources\CentroCaptacionResource;
use App\Http\Resources\EmpresaCaptacionResource;
use App\Http\Resources\MicroretoBorradorResource;
use App\Models\CentroCaptacion;
use App\Models\EmpresaCaptacion;
use App\Models\EmpresaFirma;
use App\Models\MicroretoBorrador;
use App\Models\User;
use App\Services\BorradorRetoService;
use App\Services\CaptacionCentroService;
use App\Services\CaptacionEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Revisión de las captaciones (solo superadmin, apartado «Captación» de Notificaciones):
 * validar empresas captadas (se crean en Base de datos, o se fusionan con una que ya estaba, y
 * se colocan en centros) o descartarlas; validar centros captados (alta en DuaLab o enlace con
 * uno existente) o descartarlos; y publicar o descartar los retos en borrador.
 */
class CaptacionAdminController extends Controller
{
    private const MAX_LISTADO = 100;
    private const ESTADOS_EMPRESA = [
        EmpresaCaptacion::ESTADO_BORRADOR, EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION,
        EmpresaCaptacion::ESTADO_VALIDADA, EmpresaCaptacion::ESTADO_DESCARTADA,
    ];
    private const ESTADOS_CENTRO = [
        CentroCaptacion::ESTADO_PENDIENTE_VALIDACION, CentroCaptacion::ESTADO_VALIDADA, CentroCaptacion::ESTADO_DESCARTADA,
    ];
    private const CON_CENTRO = ['origen', 'creadaPor:id,name', 'consentimientos', 'centroEducativo:id,nombre'];
    private const CON = ['origen', 'creadaPor:id,name', 'empresa.centrosAsignados:centro_educativo.id,centro_educativo.nombre', 'firmas.consentimientos'];

    public function __construct(
        private CaptacionEmpresaService $captacion,
        private BorradorRetoService $borradores,
        private CaptacionCentroService $centros,
    ) {}

    /** GET /captacion/resumen — contadores para el aviso al iniciar sesión */
    public function resumen(): JsonResponse
    {
        return response()->json($this->captacion->resumenPendientes() + ['centros_pendientes' => $this->centros->pendientes()]);
    }

    /** GET /captacion/empresas?estado=pendiente_validacion (por defecto) */
    public function empresas(Request $request): AnonymousResourceCollection
    {
        $estado = in_array($request->query('estado'), self::ESTADOS_EMPRESA, true)
            ? $request->query('estado')
            : EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION;

        $captaciones = EmpresaCaptacion::where('estado', $estado)
            ->with(self::CON)
            ->withCount(['borradores' => fn ($q) => $q->where('estado', MicroretoBorrador::ESTADO_PENDIENTE)])
            ->orderByDesc('firmada_at')
            ->latest()
            ->take(self::MAX_LISTADO)
            ->get();

        return EmpresaCaptacionResource::collection($captaciones);
    }

    /** PUT /captacion/empresas/{id}/validar — crea la empresa en Base de datos y la coloca en centros */
    public function validar(ValidarEmpresaCaptacionRequest $request, int $id): EmpresaCaptacionResource
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->captacion->validar($captacion, $request->validated('centro_ids'), $user, $request->validated('empresa_existente_id'));
        app(\App\Services\ActividadCentralService::class)->registrar($user, 'captaciones', 'validar_empresa', "Validó la empresa captada «{$captacion->nombre_comercial}» y la llevó a la base de datos.", $captacion, true);

        return new EmpresaCaptacionResource($captacion->refresh()->load(self::CON));
    }

    /** POST /captacion/empresas/{id}/descartar */
    public function descartarEmpresa(DescartarCaptacionRequest $request, int $id): EmpresaCaptacionResource
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->captacion->descartar($captacion, $user, $request->validated('motivo'));
        app(\App\Services\ActividadCentralService::class)->registrar($user, 'captaciones', 'descartar_empresa', "Descartó la empresa captada «{$captacion->nombre_comercial}».", $captacion, true);

        return new EmpresaCaptacionResource($captacion->refresh()->load(self::CON));
    }

    /** GET /captacion/empresas/{id}/coincidencias?q= — posibles duplicados en Base de datos */
    public function coincidenciasEmpresa(Request $request, int $id): JsonResponse
    {
        $captacion = EmpresaCaptacion::findOrFail($id);
        $busqueda  = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return response()->json(['data' => $this->captacion->coincidencias($captacion, $busqueda ?: null)->map(fn ($e) => [
            'id'               => $e->id,
            'nombre_comercial' => $e->nombre_comercial,
            'razon_social'     => $e->razon_social,
            'cif'              => $e->cif,
            'municipio'        => $e->municipio,
            'centro'           => $e->centroEducativo?->nombre,
        ])->values()]);
    }

    /** GET /captacion/centros?estado=pendiente_validacion (por defecto) */
    public function centros(Request $request): AnonymousResourceCollection
    {
        $estado = in_array($request->query('estado'), self::ESTADOS_CENTRO, true)
            ? $request->query('estado')
            : CentroCaptacion::ESTADO_PENDIENTE_VALIDACION;

        return CentroCaptacionResource::collection(
            CentroCaptacion::where('estado', $estado)->with(self::CON_CENTRO)->latest()->take(self::MAX_LISTADO)->get()
        );
    }

    /** PUT /captacion/centros/{id}/validar — alta en DuaLab o enlace con un centro existente */
    public function validarCentro(ValidarCentroCaptacionRequest $request, int $id): CentroCaptacionResource
    {
        $captacion = CentroCaptacion::findOrFail($id);
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->centros->validar($captacion, $user, $request->validated('centro_existente_id'));
        app(\App\Services\ActividadCentralService::class)->registrar($user, 'captaciones', 'validar_centro', "Validó el centro captado «{$captacion->centro_nombre}».", $captacion, true);

        return new CentroCaptacionResource($captacion->refresh()->load(self::CON_CENTRO));
    }

    /** POST /captacion/centros/{id}/descartar */
    public function descartarCentro(DescartarCaptacionRequest $request, int $id): CentroCaptacionResource
    {
        $captacion = CentroCaptacion::findOrFail($id);
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->centros->descartar($captacion, $user, $request->validated('motivo'));
        app(\App\Services\ActividadCentralService::class)->registrar($user, 'captaciones', 'descartar_centro', "Descartó el centro captado «{$captacion->centro_nombre}».", $captacion, true);

        return new CentroCaptacionResource($captacion->refresh()->load(self::CON_CENTRO));
    }

    /** GET /captacion/centros/{id}/coincidencias?q= — centros de DuaLab que podrían ser el mismo */
    public function coincidenciasCentro(Request $request, int $id): JsonResponse
    {
        $captacion = CentroCaptacion::findOrFail($id);
        $busqueda  = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return response()->json(['data' => $this->centros->coincidencias($captacion, $busqueda ?: null)
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'municipio' => $c->municipio])->values()]);
    }

    /** GET /captacion/borradores — retos de comerciales pendientes de validar */
    public function borradores(): AnonymousResourceCollection
    {
        $borradores = MicroretoBorrador::where('estado', MicroretoBorrador::ESTADO_PENDIENTE)
            ->whereHas('captacion', fn ($q) => $q->whereIn('estado', [EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION, EmpresaCaptacion::ESTADO_VALIDADA]))
            ->with(['captacion:id,nombre_comercial,estado,empresa_id', 'creadoPor:id,name'])
            ->latest()
            ->take(self::MAX_LISTADO)
            ->get();

        return MicroretoBorradorResource::collection($borradores);
    }

    /** GET /captacion/borradores/{uuid} — con los centros donde se puede publicar */
    public function borrador(string $uuid): JsonResponse
    {
        $borrador = MicroretoBorrador::where('uuid', $uuid)->with(['captacion:id,nombre_comercial,estado,empresa_id', 'creadoPor:id,name'])->firstOrFail();

        return response()->json([
            'borrador'          => new MicroretoBorradorResource($borrador),
            'centros_empresa'   => $borrador->captacion?->empresa?->centrosAsignados()->get(['centro_educativo.id', 'centro_educativo.nombre'])
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values() ?? [],
            // Aviso, no bloqueo: el ciclo del reto no se imparte en estos centros
            'centros_sin_ciclo' => $this->borradores->centrosSinElCiclo($borrador)->values(),
        ]);
    }

    /** POST /captacion/borradores/{uuid}/publicar */
    public function publicar(PublicarBorradorRequest $request, string $uuid): JsonResponse
    {
        $borrador = MicroretoBorrador::where('uuid', $uuid)->firstOrFail();
        /** @var User $user  ruta bajo auth:sanctum */
        $user    = $request->user();
        $creados = $this->borradores->publicar($borrador, $request->validated('centro_ids'), $user);
        app(\App\Services\ActividadCentralService::class)->registrar($user, 'captaciones', 'publicar_reto', "Publicó el reto en borrador «" . data_get($borrador->datos, 'titulo', 'sin título') . "» en {$creados->count()} centro(s).", $borrador, true);

        return response()->json([
            'message'    => $creados->count() === 1 ? 'Reto publicado.' : "Reto publicado en {$creados->count()} centros.",
            'publicados' => $creados->count(),
        ]);
    }

    /** DELETE /captacion/borradores/{uuid} — descartar (queda registrado, no se borra) */
    public function descartar(Request $request, string $uuid): JsonResponse
    {
        $borrador = MicroretoBorrador::where('uuid', $uuid)->firstOrFail();
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->borradores->descartar($borrador, $user);
        app(\App\Services\ActividadCentralService::class)->registrar($user, 'captaciones', 'descartar_reto', "Descartó el reto en borrador «" . data_get($borrador->datos, 'titulo', 'sin título') . "».", $borrador, true);

        return response()->json(['message' => 'Reto descartado.']);
    }

    /** GET /captacion/firmas/{uuid}/imagen — PNG de la firma (disco privado, nunca público) */
    public function imagenFirma(string $uuid): StreamedResponse
    {
        $firma = EmpresaFirma::where('uuid', $uuid)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($firma->firma_path), 404);

        return Storage::disk('local')->response($firma->firma_path, null, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
