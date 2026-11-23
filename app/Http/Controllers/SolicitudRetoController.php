<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvisarSolicitudRetoRequest;
use App\Http\Requests\AutorizarRetosSolicitudRequest;
use App\Http\Requests\DatosGeneradorSolicitudRequest;
use App\Http\Requests\ListarSolicitudesRetoRequest;
use App\Http\Requests\PublicarRetosSolicitudRequest;
use App\Http\Requests\StoreSolicitudRetoRequest;
use App\Http\Requests\UpdateSolicitudRetoRequest;
use App\Http\Resources\SolicitudRetoResource;
use App\Models\CentroEducativo;
use App\Models\SolicitudReto;
use App\Services\ActividadCentralService;
use App\Services\SolicitudRetoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * DuaLab Central → Primer contacto → «Solicitudes de retos» (red_dualab, central_dualab y superadmin,
 * middleware 'primer.contacto'): enviar el formulario a un docente, leer sus respuestas, asignar
 * la empresa y avisarle de que sus retos están listos. Los datos para el generador
 * (datosGenerador) solo los pide el superadmin: es quien genera.
 */
class SolicitudRetoController extends Controller
{
    public function __construct(private SolicitudRetoService $solicitudes, private ActividadCentralService $actividad) {}

    /** GET /central/solicitudes-reto */
    public function index(ListarSolicitudesRetoRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();
        $query = SolicitudReto::with('empresa:id,nombre_comercial', 'creadaPor:id,name')
            ->withCount(['microretos', 'encuentros'])
            ->latest();

        if (!empty($filtros['estado'])) $query->where('estado', $filtros['estado']);
        if (!empty($filtros['buscar'])) {
            $texto = '%' . addcslashes($filtros['buscar'], '%_\\') . '%';
            $query->where(fn ($q) => $q->where('nombre_docente', 'like', $texto)
                ->orWhere('email_docente', 'like', $texto)
                ->orWhere('centro_nombre', 'like', $texto));
        }

        return SolicitudRetoResource::collection($query->paginate(min((int) ($filtros['per_page'] ?? 25), 50)))
            ->additional(['resumen' => [
                'por_estado'  => SolicitudReto::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado'),
                // Respondidas sin ningún reto todavía: lo que hay que trabajar (contador del panel)
                'por_trabajar' => SolicitudReto::where('estado', SolicitudReto::ESTADO_RESPONDIDA)->doesntHave('microretos')->count(),
            ]]);
    }

    /** POST /central/solicitudes-reto — crea la solicitud y envía el enlace al docente */
    public function store(StoreSolicitudRetoRequest $request): JsonResponse
    {
        [$solicitud, $enlace] = $this->solicitudes->crearYEnviar($request->user(), $request->validated());
        $this->actividad->registrar($request->user(), 'solicitudes', 'enviar', "Envió el formulario de solicitud de retos a {$solicitud->nombre_docente} ({$solicitud->centro_nombre}).", $solicitud);

        return response()->json(['data' => new SolicitudRetoResource($solicitud), 'enlace' => $enlace], 201);
    }

    /** GET /central/solicitudes-reto/{id} */
    public function show(int $id): SolicitudRetoResource
    {
        return new SolicitudRetoResource($this->ficha($id));
    }

    /** PATCH /central/solicitudes-reto/{id} — empresa, centro, notas, descartar / recuperar */
    public function update(UpdateSolicitudRetoRequest $request, int $id): SolicitudRetoResource
    {
        $solicitud = SolicitudReto::findOrFail($id);
        $datos = $request->validated();
        $this->solicitudes->actualizar($solicitud, $datos);
        if (array_key_exists('bloque', $datos) && array_key_exists('empresa_id', $datos)) {
            $empresa = $datos['empresa_id'] ? \App\Models\Empresa::whereKey($datos['empresa_id'])->value('nombre_comercial') : null;
            $this->actividad->registrar($request->user(), 'solicitudes', 'empresa_peticion', $empresa
                ? "Eligió la empresa «{$empresa}» para la petición " . ($datos['bloque'] + 1) . " de la solicitud de {$solicitud->nombre_docente}."
                : "Quitó la empresa de la petición " . ($datos['bloque'] + 1) . " de la solicitud de {$solicitud->nombre_docente}.", $solicitud);
        }
        if (array_key_exists('descartada', $datos)) {
            $this->actividad->registrar($request->user(), 'solicitudes', $datos['descartada'] ? 'descartar' : 'recuperar',
                ($datos['descartada'] ? 'Descartó' : 'Recuperó') . " la solicitud de retos de {$solicitud->nombre_docente}.", $solicitud);
        }

        return new SolicitudRetoResource($this->ficha($id));
    }

    /** POST /central/solicitudes-reto/{id}/reenviar — enlace nuevo (el anterior deja de valer) */
    public function reenviar(Request $request, int $id): JsonResponse
    {
        $enlace = $this->solicitudes->enviar(SolicitudReto::findOrFail($id), $request->user());

        return response()->json(['data' => new SolicitudRetoResource($this->ficha($id)), 'enlace' => $enlace]);
    }

    /** POST /central/solicitudes-reto/{id}/avisar — «tus retos están listos», con los retos elegidos */
    public function avisar(AvisarSolicitudRetoRequest $request, int $id): SolicitudRetoResource
    {
        $datos = $request->validated();
        $solicitud = SolicitudReto::findOrFail($id);
        $this->solicitudes->avisar($solicitud, $datos['retos'], $datos['mensaje'] ?? null, $request->user());
        $this->actividad->registrar($request->user(), 'solicitudes', 'avisar_docente', 'Avisó a ' . $solicitud->nombre_docente . ' de que sus retos están listos (' . count($datos['retos']) . ' retos).', $solicitud);

        return new SolicitudRetoResource($this->ficha($id));
    }

    /**
     * POST /central/solicitudes-reto/{id}/autorizar — el docente, si tiene cuenta, puede trabajar los
     * retos elegidos: pasan a la biblioteca de su centro y le llega un correo con el enlace a cada uno.
     */
    public function autorizar(AutorizarRetosSolicitudRequest $request, int $id): SolicitudRetoResource
    {
        $datos = $request->validated();
        $solicitud = SolicitudReto::findOrFail($id);
        $cuenta = $this->solicitudes->autorizar($solicitud, $datos['retos'], $datos['mensaje'] ?? null, $request->user());
        $this->actividad->registrar($request->user(), 'retos', 'autorizar_retos',
            'Autorizó a ' . $solicitud->nombre_docente . ' a trabajar ' . count($datos['retos']) . " reto(s): pasan a la biblioteca de «{$cuenta->centroEducativo?->nombre}».", $solicitud, true);

        return new SolicitudRetoResource($this->ficha($id));
    }

    /**
     * DELETE /central/solicitudes-reto/{id} — a la papelera (solo superadmin, middleware de la ruta).
     * Se restaura o se borra del todo en Red DuaLab → Papelera.
     */
    public function destroy(int $id): JsonResponse
    {
        $solicitud = SolicitudReto::findOrFail($id);
        $solicitud->delete();
        $this->actividad->registrar(request()->user(), 'solicitudes', 'papelera',
            "Envió a la papelera la solicitud de retos de {$solicitud->nombre_docente} ({$solicitud->centro_nombre}).", $solicitud, true);

        return response()->json(['data' => ['id' => $solicitud->id, 'en_papelera' => true]]);
    }

    /**
     * GET /central/solicitudes-reto/{id}/generador?bloque=N — un bloque con los nombres del estado del
     * generador. Solo superadmin (middleware de la ruta).
     */
    public function generador(DatosGeneradorSolicitudRequest $request, int $id): JsonResponse
    {
        $indice = (int) ($request->validated()['bloque'] ?? 0);
        $solicitud = SolicitudReto::findOrFail($id);
        if ($solicitud->estado !== SolicitudReto::ESTADO_RESPONDIDA) {
            return response()->json(['error' => 'El docente aún no ha enviado esta solicitud, o está descartada.'], 422);
        }
        $datos = $this->solicitudes->datosGenerador($solicitud, $indice);

        return $datos
            ? response()->json(['data' => $datos])
            : response()->json(['error' => 'Esa petición no existe en la solicitud.'], 404);
    }

    /**
     * GET /central/solicitudes-reto/{id}/generacion?bloque=N — lo que necesita la «generación rápida» de
     * Central para esa petición (SolicitudRetoService::datosGeneracion).
     */
    public function generacion(DatosGeneradorSolicitudRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudReto::findOrFail($id);
        if ($solicitud->estado !== SolicitudReto::ESTADO_RESPONDIDA) {
            return response()->json(['error' => 'El docente aún no ha enviado esta solicitud, o está descartada.'], 422);
        }
        $datos = $this->solicitudes->datosGeneracion($solicitud, (int) ($request->validated()['bloque'] ?? 0));

        return $datos
            ? response()->json(['data' => $datos])
            : response()->json(['error' => 'Esa petición no existe en la solicitud.'], 404);
    }

    /** POST /central/solicitudes-reto/{id}/publicar — retos de la recámara a la biblioteca de un centro */
    public function publicar(PublicarRetosSolicitudRequest $request, int $id): SolicitudRetoResource
    {
        $solicitud = SolicitudReto::findOrFail($id);
        $datos = $request->validated();
        $n = $this->solicitudes->publicarRetos($solicitud, $datos['retos'], (int) $datos['centro_id']);
        $centro = CentroEducativo::whereKey($datos['centro_id'])->value('nombre');
        $this->actividad->registrar($request->user(), 'retos', 'publicar_retos',
            "Publicó {$n} reto(s) de la solicitud de {$solicitud->nombre_docente} en la biblioteca de «{$centro}».", $solicitud, true);

        return new SolicitudRetoResource($this->ficha($id));
    }

    /**
     * GET /central/solicitudes-reto/{id}/empresas?bloque=N — empresas con la familia de esa petición,
     * para elegir la de cada petición en Central. Solo superadmin (middleware de la ruta).
     */
    public function empresas(DatosGeneradorSolicitudRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudReto::findOrFail($id);

        $datos = $request->validated();

        return response()->json(['data' => $this->solicitudes->empresasParaPeticion(
            $solicitud, (int) ($datos['bloque'] ?? 0), (bool) ($datos['todas'] ?? false))]);
    }

    /**
     * GET /central/solicitudes-reto/para-generador — solicitudes respondidas con sus bloques, para el
     * desplegable «Solicitudes del profesorado» del generador. Solo superadmin (middleware de la ruta).
     */
    public function paraGenerador(): JsonResponse
    {
        return response()->json(['data' => $this->solicitudes->paraGenerador()]);
    }

    private function ficha(int $id): SolicitudReto
    {
        return SolicitudReto::with([
            'empresa:id,nombre_comercial', 'centro:id,nombre', 'creadaPor:id,name',
            'microretos' => fn ($q) => $q->with('empresa:id,nombre_comercial', 'centro:id,nombre')->latest()->limit(50),
        ])->withCount(['microretos', 'encuentros'])->findOrFail($id);
    }
}
