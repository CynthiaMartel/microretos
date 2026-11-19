<?php

namespace App\Http\Controllers;

use App\Exceptions\FrontofficeNoDisponible;
use App\Http\Requests\ListarRespuestasSolicitudesRequest;
use App\Models\SolicitudReto;
use App\Services\FrontofficeBandejaService;
use App\Services\SolicitudRetoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * DuaLab Central → Primer contacto → «Respuestas de solicitudes»: los correos de info@ de las
 * solicitudes de retos, aparte del resto del buzón (el Correo de info@ los excluye). Los correos
 * siguen en la web (misma API firmada, FrontofficeBandejaService); son de una solicitud si llevan su
 * referencia en el asunto («Ref. SR-12», SolicitudReto::referencia) o llegan de un docente con una
 * solicitud abierta (SolicitudRetoService::origenCorreos). Abrir uno y contestarlo es el Correo de
 * info@ de siempre (PrimerContactoCorreoController).
 */
class SolicitudRetoCorreoController extends Controller
{
    private const CAMPOS = ['id', 'de_email', 'de_nombre', 'asunto', 'extracto', 'fecha', 'estado'];

    public function __construct(private FrontofficeBandejaService $web, private SolicitudRetoService $solicitudes) {}

    /** GET /central/solicitudes-reto/correos */
    public function index(ListarRespuestasSolicitudesRequest $request): JsonResponse
    {
        return $this->conWeb(function () use ($request) {
            $origen = $this->solicitudes->origenCorreos();
            $r = $this->web->listarCorreos(
                ['incluir_asunto' => $origen['asunto'], 'incluir_de' => $origen['de'], 'per_page' => 25] + array_filter($request->validated()),
                $request->user()->name,
            );
            $correos = $this->conSolicitud(collect($r['data'] ?? [])->map(fn ($c) => $this->limpiar($c, 'recibidos')));

            return response()->json([
                'data' => $correos->values(),
                'meta' => array_intersect_key($r['meta'] ?? [], array_flip(['current_page', 'last_page', 'per_page', 'total'])),
            ]);
        });
    }

    /**
     * GET /central/solicitudes-reto/{id}/correos — lo recibido de esta solicitud (su referencia o el
     * email del docente) y lo enviado con su referencia (lo que se escribe desde Central y, si salen por
     * la web, la invitación y el aviso).
     */
    public function deSolicitud(Request $request, int $id): JsonResponse
    {
        $solicitud = SolicitudReto::findOrFail($id);

        return $this->conWeb(function () use ($request, $solicitud) {
            $usuario = $request->user()->name;
            $recibidos = $this->web->listarCorreos([
                'incluir_asunto' => $solicitud->referencia(), 'incluir_de' => [$solicitud->email_docente], 'per_page' => 50,
            ], $usuario)['data'] ?? [];
            $enviados = $this->web->listarCorreos([
                'carpeta' => 'enviados', 'incluir_asunto' => $solicitud->referencia(), 'per_page' => 50,
            ], $usuario)['data'] ?? [];

            $correos = collect($recibidos)->map(fn ($c) => $this->limpiar($c, 'recibidos'))
                ->concat(collect($enviados)->map(fn ($c) => $this->limpiar($c, 'enviados')))
                // «Ref. SR-1» también encuentra «Ref. SR-12»: solo la referencia exacta o el email del docente
                ->filter(fn ($c) => SolicitudReto::idDeAsunto($c['asunto']) === $solicitud->id
                    || ($c['carpeta'] === 'recibidos' && strcasecmp((string) $c['de_email'], $solicitud->email_docente) === 0))
                ->unique('id')
                ->sortByDesc('fecha')
                ->take(50);

            return response()->json(['data' => $correos->values()]);
        });
    }

    /**
     * Añade a cada correo su solicitud: por la referencia del asunto o, sin ella, por el email del
     * docente (la más reciente de ese email).
     *
     * @param Collection<int, array<string, mixed>> $correos
     * @return Collection<int, array<string, mixed>>
     */
    private function conSolicitud(Collection $correos): Collection
    {
        $ids = $correos->map(fn ($c) => SolicitudReto::idDeAsunto($c['asunto']))->filter()->unique()->values();
        $emails = $correos->pluck('de_email')->filter()->map(fn ($e) => strtolower($e))->unique()->values();
        $campos = ['id', 'nombre_docente', 'centro_nombre', 'email_docente', 'estado', 'created_at'];

        $porId = SolicitudReto::whereIn('id', $ids)->get($campos)->keyBy('id');
        $porEmail = SolicitudReto::whereIn('email_docente', $emails)->where('estado', '!=', SolicitudReto::ESTADO_DESCARTADA)
            ->latest()->get($campos)->unique(fn ($s) => strtolower($s->email_docente))->keyBy(fn ($s) => strtolower($s->email_docente));

        return $correos->map(function ($c) use ($porId, $porEmail) {
            $s = ($id = SolicitudReto::idDeAsunto($c['asunto'])) ? $porId->get($id) : $porEmail->get(strtolower((string) $c['de_email']));

            return $c + ['solicitud' => $s ? ['id' => $s->id, 'nombre_docente' => $s->nombre_docente, 'centro_nombre' => $s->centro_nombre] : null];
        });
    }

    /** Lista blanca de lo que llega de la web */
    private function limpiar(array $c, string $carpeta): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo) {
            $valor = $c[$campo] ?? null;
            $out[$campo] = $campo === 'id' ? (int) $valor : (is_scalar($valor) ? (string) $valor : null);
        }
        $out['tiene_adjuntos'] = (bool) ($c['tiene_adjuntos'] ?? false);
        $out['carpeta'] = $carpeta;

        return $out;
    }

    private function conWeb(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (FrontofficeNoDisponible) {
            return response()->json(['error' => 'No se ha podido conectar con la web. Inténtalo de nuevo en un momento.'], 502);
        }
    }
}
