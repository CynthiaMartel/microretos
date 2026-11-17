<?php

namespace App\Http\Controllers;

use App\Exceptions\FrontofficeNoDisponible;
use App\Http\Requests\EnviarCorreoWebRequest;
use App\Http\Requests\ListarCorreosWebRequest;
use App\Http\Requests\UpdateEstadoCorreoWebRequest;
use App\Services\FrontofficeBandejaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * DuaLab Central → «Primer contacto» → Correo de info@ (y respuestas a formularios). Los correos y el
 * envío siguen en la web; esto los trae y los pide por la API firmada (FrontofficeBandejaService).
 * Lo que llega de la web se filtra por lista blanca. El HTML de los correos ya viene limpio
 * (HTMLPurifier en la web) y Central lo vuelve a limpiar (DOMPurify) y lo pinta en un iframe aislado.
 */
class PrimerContactoCorreoController extends Controller
{
    private const CAMPOS = ['id', 'de_email', 'de_nombre', 'asunto', 'extracto', 'fecha', 'estado', 'leido_en', 'carpeta'];

    public function __construct(private FrontofficeBandejaService $web) {}

    public function index(ListarCorreosWebRequest $request): JsonResponse
    {
        return $this->conWeb(function () use ($request) {
            $f = $request->validated();
            if (array_key_exists('adjuntos', $f)) {
                $f['adjuntos'] = $f['adjuntos'] ? 1 : null;
            }
            $r = $this->web->listarCorreos($f + ['per_page' => 25], $this->usuario($request));

            return response()->json([
                'data'       => collect($r['data'] ?? [])->map(fn ($c) => $this->limpiar($c))->values(),
                'meta'       => array_intersect_key($r['meta'] ?? [], array_flip(['current_page', 'last_page', 'per_page', 'total'])),
                'resumen'    => [
                    'por_estado'   => array_map('intval', array_intersect_key($r['resumen']['por_estado'] ?? [], array_flip(ListarCorreosWebRequest::ESTADOS))),
                    'buzon'        => ['email' => (string) ($r['resumen']['buzon']['email'] ?? ''), 'configurado' => (bool) ($r['resumen']['buzon']['configurado'] ?? false)],
                    'sincronizado' => [
                        'en'       => $this->texto($r['resumen']['sincronizado']['en'] ?? null),
                        'error'    => $this->texto($r['resumen']['sincronizado']['error'] ?? null),
                        'omitidos' => (int) ($r['resumen']['sincronizado']['omitidos'] ?? 0),
                    ],
                    'retencion'    => [
                        'meses'   => (int) ($r['resumen']['retencion']['meses'] ?? 0),
                        'desde'   => $this->texto($r['resumen']['retencion']['desde'] ?? null),
                        'webmail' => $this->url($r['resumen']['retencion']['webmail'] ?? null),
                    ],
                ],
                'salida'     => collect($r['salida'] ?? [])->take(10)->map(fn ($e) => [
                    'id'         => (int) ($e['id'] ?? 0),
                    'asunto'     => (string) ($e['asunto'] ?? ''),
                    'para'       => array_values(array_filter((array) ($e['para'] ?? []), 'is_string')),
                    'estado'     => (string) ($e['estado'] ?? ''),
                    'error'      => $this->texto($e['error'] ?? null),
                    'created_at' => $this->texto($e['created_at'] ?? null),
                ])->values(),
                'remitentes' => collect($r['remitentes'] ?? [])->map(fn ($x) => ['tipo' => (string) ($x['tipo'] ?? ''), 'tipo_nombre' => (string) ($x['tipo_nombre'] ?? '')]),
            ]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->conWeb(fn () => $this->ficha($this->web->verCorreo($id, $this->usuario($request))));
    }

    public function update(UpdateEstadoCorreoWebRequest $request, int $id): JsonResponse
    {
        return $this->conWeb(fn () => $this->ficha($this->web->cambiarEstadoCorreo($id, $request->validated('estado'), $this->usuario($request))));
    }

    /** Descarga de un adjunto: siempre como archivo (nunca se abre en el navegador). */
    public function adjunto(Request $request, int $correo, int $adjunto): Response|JsonResponse
    {
        return $this->conWeb(function () use ($request, $correo, $adjunto) {
            $r = $this->web->descargarAdjunto($correo, $adjunto, $this->usuario($request));
            if ($r->status() === 404) {
                return response()->json(['error' => 'Ese adjunto ya no está disponible.'], 404);
            }
            // Nombre del archivo tal como lo manda la web, sin caracteres que rompan la cabecera
            $disposicion = (string) $r->header('Content-Disposition');
            if (!preg_match('/^attachment;[^\r\n]{1,600}$/', $disposicion)) {
                $disposicion = 'attachment; filename="adjunto"';
            }

            return response($r->body(), 200, [
                'Content-Type'           => 'application/octet-stream',
                'Content-Disposition'    => $disposicion,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control'          => 'private, no-store',
            ]);
        });
    }

    /** Correo nuevo, respuesta o reenvío: la web lo deja en su bandeja de salida (202). */
    public function enviar(EnviarCorreoWebRequest $request): JsonResponse
    {
        return $this->conWeb(function () use ($request) {
            $datos = collect($request->validated())->except('adjuntos')->all();
            $datos['adjuntos_b64'] = collect($request->file('adjuntos', []))->map(fn ($f) => [
                'nombre'    => $f->getClientOriginalName(),
                'contenido' => base64_encode((string) file_get_contents($f->getRealPath())),
            ])->values()->all();

            return $this->reenviar($this->web->enviarCorreo($datos, $this->usuario($request)));
        });
    }

    public function reintentar(Request $request, int $id): JsonResponse
    {
        return $this->conWeb(fn () => $this->reenviar($this->web->reintentarEnvio($id, $this->usuario($request))));
    }

    public function descartar(Request $request, int $id): JsonResponse
    {
        return $this->conWeb(fn () => $this->reenviar($this->web->descartarEnvio($id, $this->usuario($request))));
    }

    // ── Auxiliares ──────────────────────────────────────────────────────────

    /** Respuesta de un envío: estado y, si la web lo rechaza, sus mensajes de validación. */
    private function reenviar($r): JsonResponse
    {
        return match (true) {
            $r->status() === 404 => response()->json(['error' => 'Ese envío ya no existe.'], 404),
            $r->status() === 422 => response()->json([
                'message' => (string) ($r->json('message') ?? 'Revisa los datos del correo.'),
                'errors'  => collect((array) $r->json('errors', []))->map(fn ($m) => array_values(array_filter((array) $m, 'is_string')))->all(),
            ], 422),
            $r->status() === 204 => response()->json(null, 204),
            default => response()->json(['id' => (int) $r->json('id'), 'estado' => (string) $r->json('estado')], $r->status()),
        };
    }

    private function ficha(?array $c): JsonResponse
    {
        if ($c === null) {
            return response()->json(['error' => 'Ese correo ya no está en la bandeja.'], 404);
        }
        $direcciones = fn ($lista) => collect((array) $lista)->take(50)->map(fn ($d) => [
            'email'  => (string) ($d['email'] ?? ''),
            'nombre' => isset($d['nombre']) ? (string) $d['nombre'] : null,
        ])->values();

        return response()->json(['data' => $this->limpiar($c) + [
            'cc'            => $direcciones($c['cc'] ?? []),
            'responder_a'   => $direcciones($c['responder_a'] ?? []),
            'texto'         => $this->texto($c['texto'] ?? null),
            'texto_citable' => $this->texto($c['texto_citable'] ?? null),
            'html'          => $this->texto($c['html'] ?? null),
            'contacto'      => isset($c['contacto']['id']) ? [
                'id'          => (int) $c['contacto']['id'],
                'tipo_nombre' => (string) ($c['contacto']['tipo_nombre'] ?? ''),
                'fecha'       => $this->texto($c['contacto']['fecha'] ?? null),
            ] : null,
            'adjuntos'      => collect($c['adjuntos'] ?? [])->take(50)->map(fn ($a) => [
                'id'         => (int) ($a['id'] ?? 0),
                'nombre'     => (string) ($a['nombre'] ?? 'adjunto'),
                'tamano'     => (int) ($a['tamano'] ?? 0),
                'en_linea'   => (bool) ($a['en_linea'] ?? false),
                'disponible' => (bool) ($a['disponible'] ?? false),
            ])->values(),
        ]]);
    }

    private function limpiar(array $c): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo) {
            $out[$campo] = $campo === 'id' ? (int) ($c['id'] ?? 0) : $this->texto($c[$campo] ?? null);
        }
        $out['tiene_adjuntos'] = (bool) ($c['tiene_adjuntos'] ?? false);
        $out['para'] = collect((array) ($c['para'] ?? []))->take(50)->map(fn ($d) => [
            'email' => (string) ($d['email'] ?? ''), 'nombre' => isset($d['nombre']) ? (string) $d['nombre'] : null,
        ])->values();

        return $out;
    }

    private function texto($v): ?string
    {
        return is_scalar($v) ? (string) $v : null;
    }

    private function url($v): ?string
    {
        return is_string($v) && preg_match('#^https?://#i', $v) ? $v : null;
    }

    private function usuario(Request $request): string
    {
        return (string) $request->user()->name;
    }

    private function conWeb(callable $fn)
    {
        try {
            return $fn();
        } catch (FrontofficeNoDisponible) {
            return response()->json(['error' => 'No se ha podido conectar con la web. Inténtalo de nuevo en un momento.'], 502);
        }
    }
}
