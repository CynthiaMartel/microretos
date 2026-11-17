<?php

namespace App\Http\Controllers;

use App\Exceptions\FrontofficeNoDisponible;
use App\Http\Requests\ListarFormulariosWebRequest;
use App\Http\Requests\UpdateEstadoFormularioWebRequest;
use App\Models\CaptacionOrigen;
use App\Models\CentroCaptacion;
use App\Models\EmpresaCaptacion;
use App\Services\FrontofficeBandejaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DuaLab Central → «Primer contacto» (solo superadmin por el middleware de la ruta): la bandeja de
 * formularios de la web, leída por la API firmada del frontoffice (FrontofficeBandejaService).
 * Lo que llega de la web se filtra por lista blanca antes de devolverlo, y cada formulario se cruza
 * con las captaciones de aquí (origen 2, origen_ref = id del formulario) para ver si ya pasó.
 */
class PrimerContactoController extends Controller
{
    private const CAMPOS = ['id', 'formulario', 'formulario_nombre', 'accion', 'accion_nombre', 'nombre', 'apellidos',
        'email', 'telefono', 'cargo', 'pagina_origen', 'estado', 'leido_en', 'created_at'];

    public function __construct(private FrontofficeBandejaService $web) {}

    public function index(ListarFormulariosWebRequest $request): JsonResponse
    {
        return $this->conWeb(function () use ($request) {
            $r = $this->web->listar($request->validated() + ['per_page' => 25], $this->usuario($request));
            $items = collect($r['data'] ?? [])->map(fn ($f) => $this->limpiar($f));
            $captaciones = $this->captacionesDe($items->pluck('id')->all());

            return response()->json([
                'data'    => $items->map(fn ($f) => $f + ['captacion' => $captaciones[$f['id']] ?? null])->values(),
                'meta'    => array_intersect_key($r['meta'] ?? [], array_flip(['current_page', 'last_page', 'per_page', 'total'])),
                'resumen' => [
                    'por_estado'     => array_map('intval', array_intersect_key($r['resumen']['por_estado'] ?? [], array_flip(ListarFormulariosWebRequest::ESTADOS))),
                    'por_formulario' => collect($r['resumen']['por_formulario'] ?? [])
                        ->map(fn ($x) => ['clave' => (string) ($x['clave'] ?? ''), 'nombre' => (string) ($x['nombre'] ?? ''), 'total' => (int) ($x['total'] ?? 0)])
                        ->values(),
                ],
                'acciones' => array_map('strval', $r['acciones'] ?? []),
            ]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->conWeb(function () use ($request, $id) {
            $f = $this->web->ver($id, $this->usuario($request));

            return $f === null
                ? response()->json(['error' => 'Ese formulario ya no existe en la web.'], 404)
                : response()->json(['data' => $this->ficha($f)]);
        });
    }

    public function update(UpdateEstadoFormularioWebRequest $request, int $id): JsonResponse
    {
        return $this->conWeb(function () use ($request, $id) {
            $f = $this->web->cambiarEstado($id, $request->validated('estado'), $this->usuario($request));

            return $f === null
                ? response()->json(['error' => 'Ese formulario ya no existe en la web.'], 404)
                : response()->json(['data' => $this->ficha($f)]);
        });
    }

    private function conWeb(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (FrontofficeNoDisponible) {
            return response()->json(['error' => 'No se ha podido conectar con la web. Inténtalo de nuevo en un momento.'], 502);
        }
    }

    private function usuario(Request $request): string
    {
        return (string) $request->user()->name;
    }

    /** Lista blanca de campos (texto plano: el frontend los pinta escapados, nunca con v-html). */
    private function limpiar(array $f): array
    {
        $out = [];
        foreach (self::CAMPOS as $c) {
            $v = $f[$c] ?? null;
            $out[$c] = $c === 'id' ? (int) $v : (is_scalar($v) ? (string) $v : null);
        }

        return $out;
    }

    private function ficha(array $f): array
    {
        $base = $this->limpiar($f);
        // Datos del formulario: pares campo → texto (listas unidas con comas); nada anidado
        $datos = collect(is_array($f['datos'] ?? null) ? $f['datos'] : [])
            ->take(50)
            ->map(fn ($v) => is_array($v) ? implode(', ', array_filter($v, 'is_scalar')) : (is_scalar($v) ? (string) $v : null))
            ->filter(fn ($v) => $v !== null && $v !== '');

        return $base + [
            'datos'      => (object) $datos->all(),
            'respuestas' => collect($f['respuestas'] ?? [])->take(50)->map(fn ($e) => [
                'id'     => (int) ($e['id'] ?? 0),
                'asunto' => (string) ($e['asunto'] ?? ''),
                'estado' => (string) ($e['estado'] ?? ''),
                'fecha'  => (string) ($e['fecha'] ?? ''),
                'por'    => isset($e['por']) ? (string) $e['por'] : null,
            ])->values(),
            'captacion'  => $this->captacionesDe([$base['id']])[$base['id']] ?? null,
        ];
    }

    /** Captaciones creadas desde estos formularios (webhook de la web): [id formulario => {tipo, id, estado}]. */
    private function captacionesDe(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $refs = array_map('strval', $ids);
        $out = [];
        foreach (['empresa' => EmpresaCaptacion::class, 'centro' => CentroCaptacion::class] as $tipo => $modelo) {
            $modelo::where('origen_id', CaptacionOrigen::FORMULARIO)->whereIn('origen_ref', $refs)
                ->get(['id', 'origen_ref', 'estado'])
                ->each(function ($c) use (&$out, $tipo) {
                    $out[(int) $c->origen_ref] = ['tipo' => $tipo, 'id' => $c->id, 'estado' => $c->estado];
                });
        }

        return $out;
    }
}
