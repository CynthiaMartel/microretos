<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarCentrosEmpresaRequest;
use App\Http\Requests\PublicarBorradorRequest;
use App\Http\Resources\EmpresaCaptacionResource;
use App\Http\Resources\MicroretoBorradorResource;
use App\Models\Empresa;
use App\Models\EmpresaFirma;
use App\Models\MicroretoBorrador;
use App\Models\User;
use App\Services\BorradorRetoService;
use App\Services\CaptacionEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Revisión de lo captado por los comerciales (solo superadmin, apartado «Captación» de
 * Notificaciones): colocar empresas en centros y publicar o descartar los retos en borrador.
 */
class CaptacionAdminController extends Controller
{
    private const MAX_LISTADO = 100;
    private const ESTADOS_EMPRESA = [Empresa::CAPTACION_BORRADOR, Empresa::CAPTACION_PENDIENTE_CENTRO, Empresa::CAPTACION_ASIGNADA];

    public function __construct(
        private CaptacionEmpresaService $captacion,
        private BorradorRetoService $borradores,
    ) {}

    /** GET /captacion/resumen — contadores para el aviso al iniciar sesión */
    public function resumen(): JsonResponse
    {
        return response()->json($this->captacion->resumenPendientes());
    }

    /** GET /captacion/empresas?estado=pendiente_centro (por defecto) */
    public function empresas(Request $request): AnonymousResourceCollection
    {
        $estado = in_array($request->query('estado'), self::ESTADOS_EMPRESA, true)
            ? $request->query('estado')
            : Empresa::CAPTACION_PENDIENTE_CENTRO;

        $empresas = Empresa::where('estado_captacion', $estado)
            ->with(['familias:id,nombre', 'creadaPor:id,name', 'centrosAsignados:centro_educativo.id,centro_educativo.nombre', 'firmas.consentimientos'])
            ->withCount(['borradores' => fn ($q) => $q->where('estado', MicroretoBorrador::ESTADO_PENDIENTE)])
            ->orderByDesc('firmada_at')
            ->latest()
            ->take(self::MAX_LISTADO)
            ->get();

        return EmpresaCaptacionResource::collection($empresas);
    }

    /** PUT /captacion/empresas/{id}/centros */
    public function asignarCentros(AsignarCentrosEmpresaRequest $request, int $id): EmpresaCaptacionResource
    {
        $empresa = Empresa::whereNotNull('estado_captacion')->findOrFail($id);
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $this->captacion->asignarCentros($empresa, $request->validated('centro_ids'), $user);

        return new EmpresaCaptacionResource($empresa->refresh()->load(['familias:id,nombre', 'creadaPor:id,name', 'centrosAsignados:centro_educativo.id,centro_educativo.nombre']));
    }

    /** GET /captacion/borradores — retos de comerciales pendientes de validar */
    public function borradores(): AnonymousResourceCollection
    {
        $borradores = MicroretoBorrador::where('estado', MicroretoBorrador::ESTADO_PENDIENTE)
            ->whereHas('empresa', fn ($q) => $q->whereNull('deleted_at'))
            ->with(['empresa:id,nombre_comercial,estado_captacion', 'creadoPor:id,name'])
            ->latest()
            ->take(self::MAX_LISTADO)
            ->get();

        return MicroretoBorradorResource::collection($borradores);
    }

    /** GET /captacion/borradores/{uuid} — con los centros donde se puede publicar */
    public function borrador(string $uuid): JsonResponse
    {
        $borrador = MicroretoBorrador::where('uuid', $uuid)->with(['empresa:id,nombre_comercial,estado_captacion', 'creadoPor:id,name'])->firstOrFail();

        return response()->json([
            'borrador'          => new MicroretoBorradorResource($borrador),
            'centros_empresa'   => $borrador->empresa?->centrosAsignados()->get(['centro_educativo.id', 'centro_educativo.nombre'])
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
