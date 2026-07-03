<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\EquipoMiembro;
use App\Models\EquipoTarea;
use App\Models\EquipoReflexion;
use App\Models\Sesion;

class EquipoPublicoController extends Controller
{
    // Preguntas de síntesis predefinidas para F0.
    // El docente podrá personalizarlas en una iteración futura.
    const PREGUNTAS_F0 = [
        '¿Cuál es el problema principal que necesita resolver la empresa?',
        '¿A quién afecta este problema y de qué manera?',
        '¿Qué limitaciones o restricciones tenéis en cuenta para la solución?',
        '¿Qué recursos ya tiene disponibles la empresa?',
        '¿Qué resultado o entregable espera obtener la empresa?',
    ];

    // ── Acceso por código de clase ───────────────────────────────────────────

    /**
     * GET /api/clase/{codigo}
     * Resuelve el código de clase → devuelve el proyecto + lista de equipos con miembros.
     * El alumno elige su equipo y recibe el token de ese equipo.
     */
    public function porCodigoClase($codigo)
    {
        $sesion = Sesion::where('codigo_clase', strtoupper($codigo))->first();

        if (!$sesion) {
            return response()->json(['error' => 'Código no válido.'], 404);
        }

        $proyecto = $sesion->microproyectos()
            ->whereIn('estado', ['propuesta', 'validado'])
            ->with('equipos.miembros')
            ->latest()
            ->first();

        if (!$proyecto) {
            return response()->json(['error' => 'El proyecto no está activo todavía.'], 403);
        }

        return response()->json([
            'tipo'            => 'clase',
            'proyecto_titulo' => $proyecto->titulo,
            'curso'           => $proyecto->curso,
            'equipos'         => $proyecto->equipos->map(fn($e) => [
                'id'       => $e->id,
                'nombre'   => $e->nombre,
                'token'    => $e->token,
                'miembros' => $e->miembros->pluck('nombre'),
            ]),
        ]);
    }

    // ── Acceso por código corto (Kahoot) ─────────────────────────────────────

    /**
     * GET /api/equipo/unirse/{codigo}
     * Resuelve el código corto (ej. "XKM-479") al token largo y devuelve info básica.
     */
    public function unirse($codigo)
    {
        $equipo = Equipo::with('microproyecto')
            ->where('codigo_acceso', strtoupper($codigo))
            ->first();

        if (!$equipo) {
            return response()->json(['error' => 'Código no válido. Comprueba que lo has escrito bien.'], 404);
        }

        if (!in_array($equipo->microproyecto->estado, ['propuesta', 'validado'])) {
            return response()->json(['error' => 'El proyecto aún no está activo. Espera a que el docente lo publique.'], 403);
        }

        return response()->json([
            'token'           => $equipo->token,
            'nombre_equipo'   => $equipo->nombre,
            'proyecto_titulo' => $equipo->microproyecto->titulo,
            'fase_actual'     => $equipo->fase_actual,
        ]);
    }

    // ── Workspace del equipo ─────────────────────────────────────────────────

    /**
     * GET /api/equipo/{token}
     * Carga todos los datos necesarios para el workspace del equipo.
     */
    public function show($token)
    {
        $equipo = Equipo::with([
            'microproyecto.microreto',
            'miembros',
            'fases',
            'tareas',
            'reflexiones',
        ])->where('token', $token)->first();

        if (!$equipo) {
            return response()->json(['error' => 'Enlace no válido.'], 404);
        }

        if (!in_array($equipo->microproyecto->estado, ['propuesta', 'validado'])) {
            return response()->json(['error' => 'El proyecto no está activo.'], 403);
        }

        return response()->json($this->formatWorkspace($equipo));
    }

    // ── Guardar datos de fase ────────────────────────────────────────────────

    /**
     * PUT /api/equipo/{token}/fase/{fase}
     * Guarda el JSON de datos de una fase (auto-crea el registro si no existe).
     */
    public function guardarFase(Request $request, $token, int $numeroFase)
    {
        if (!in_array($numeroFase, [0, 1, 2, 3, 4])) {
            return response()->json(['error' => 'Fase no válida.'], 422);
        }

        $equipo = Equipo::where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'datos' => 'required|array',
        ]);

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            ['datos' => $validated['datos']]
        );

        // Sincronizar miembros si vienen en F0
        if ($numeroFase === 0 && isset($validated['datos']['miembros'])) {
            $this->sincronizarMiembros($equipo, $validated['datos']['miembros']);
        }

        return response()->json(['ok' => true, 'fase' => $this->formatFase($fase)]);
    }

    /**
     * POST /api/equipo/{token}/fase/{fase}/completar
     * Marca la fase como completada y avanza fase_actual del equipo.
     */
    public function completarFase(Request $request, $token, int $numeroFase)
    {
        if (!in_array($numeroFase, [0, 1, 2, 3, 4])) {
            return response()->json(['error' => 'Fase no válida.'], 422);
        }

        $equipo = Equipo::where('token', $token)->firstOrFail();

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            ['completada' => true, 'fecha_completada' => now()]
        );

        if ($equipo->fase_actual <= $numeroFase) {
            $equipo->update(['fase_actual' => min($numeroFase + 1, 4)]);
        }

        return response()->json(['ok' => true, 'fase_actual' => $equipo->fresh()->fase_actual]);
    }

    // ── Tareas F2 ────────────────────────────────────────────────────────────

    public function storeTarea(Request $request, $token)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'descripcion' => 'required|string|max:500',
            'responsable' => 'nullable|string|max:100',
            'estado'      => 'nullable|in:pendiente,en_progreso,realizado',
        ]);

        $data['equipo_id'] = $equipo->id;
        $data['orden']     = EquipoTarea::where('equipo_id', $equipo->id)->count();

        $tarea = EquipoTarea::create($data);
        return response()->json($tarea, 201);
    }

    public function updateTarea(Request $request, $token, int $tareaId)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();
        $tarea  = EquipoTarea::where('id', $tareaId)->where('equipo_id', $equipo->id)->firstOrFail();

        $data = $request->validate([
            'descripcion' => 'sometimes|string|max:500',
            'responsable' => 'nullable|string|max:100',
            'estado'      => 'sometimes|in:pendiente,en_progreso,realizado',
        ]);

        $tarea->update($data);
        return response()->json($tarea);
    }

    public function destroyTarea($token, int $tareaId)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();
        $tarea  = EquipoTarea::where('id', $tareaId)->where('equipo_id', $equipo->id)->firstOrFail();
        $tarea->delete();
        return response()->json(['ok' => true]);
    }

    // ── Reflexiones F4 ───────────────────────────────────────────────────────

    public function storeReflexion(Request $request, $token)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'tipo'         => 'required|in:individual,grupal',
            'autor_nombre' => 'required_if:tipo,individual|nullable|string|max:100',
            'respuestas'   => 'required|array|min:1',
            'respuestas.*.pregunta'  => 'required|string',
            'respuestas.*.respuesta' => 'required|string|max:2000',
        ]);

        // Solo una reflexión grupal por equipo
        if ($data['tipo'] === 'grupal') {
            EquipoReflexion::where('equipo_id', $equipo->id)->where('tipo', 'grupal')->delete();
        }

        $reflexion = EquipoReflexion::create([
            'equipo_id'    => $equipo->id,
            'tipo'         => $data['tipo'],
            'autor_nombre' => $data['autor_nombre'] ?? null,
            'respuestas'   => $data['respuestas'],
        ]);

        return response()->json($reflexion, 201);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    private function formatWorkspace(Equipo $equipo): array
    {
        $mp = $equipo->microproyecto;
        $mr = $mp->microreto;

        return [
            'equipo' => [
                'id'            => $equipo->id,
                'nombre'        => $equipo->nombre,
                'codigo_acceso' => $equipo->codigo_acceso,
                'fase_actual'   => $equipo->fase_actual,
                'miembros'      => $equipo->miembros->map(fn($m) => [
                    'id'     => $m->id,
                    'nombre' => $m->nombre,
                    'rol'    => $m->rol,
                ]),
            ],
            'proyecto' => [
                'titulo'        => $mp->titulo,
                'curso'         => $mp->curso,
                'empresa_nombre' => $mp->datos_empresa['nombre'] ?? null,
                'centro_nombre'  => $mp->datos_centro['nombre'] ?? null,
                'docente_nombre' => $mp->datos_centro['docente_nombre'] ?? null,
                'objetivos'     => $mp->objetivos['lista'] ?? [],
                'kpis'          => $mp->kpis['lista'] ?? [],
            ],
            // Diagnóstico de empresa del microreto origen (solo lectura en F0)
            'diagnostico' => $mr ? [
                'quien_es'      => $mr->quien_es,
                'dia_a_dia'     => $mr->dia_a_dia,
                'pregunta_reto' => $mr->pregunta_reto,
                'que_necesitan' => $mr->que_necesitan,
                'dificultades'  => $mr->dificultades,
                'limitaciones'  => $mr->limitaciones,
                'prototipos'    => $mr->prototipos,
            ] : null,
            // Preguntas de síntesis para que el equipo responda en F0
            'preguntas_f0' => self::PREGUNTAS_F0,
            // Estado de cada fase
            'fases' => collect(range(0, 4))->map(function ($n) use ($equipo) {
                $fase = $equipo->fases->firstWhere('numero_fase', $n);
                return $this->formatFase($fase, $n);
            }),
            // Tareas F2
            'tareas' => $equipo->tareas->map(fn($t) => [
                'id'          => $t->id,
                'descripcion' => $t->descripcion,
                'responsable' => $t->responsable,
                'estado'      => $t->estado,
            ]),
            // Reflexiones F4
            'reflexiones' => $equipo->reflexiones->map(fn($r) => [
                'id'           => $r->id,
                'tipo'         => $r->tipo,
                'autor_nombre' => $r->autor_nombre,
                'respuestas'   => $r->respuestas,
                'created_at'   => $r->created_at,
            ]),
        ];
    }

    private function formatFase(?EquipoFase $fase, int $numero = 0): array
    {
        if (!$fase) {
            return [
                'numero_fase'              => $numero,
                'datos'                    => null,
                'completada'               => false,
                'fecha_completada'         => null,
                'validado_docente'         => false,
                'fecha_validacion_docente' => null,
                'nota_docente'             => null,
                'observaciones_docente'    => null,
            ];
        }

        return [
            'numero_fase'              => $fase->numero_fase,
            'datos'                    => $fase->datos,
            'completada'               => $fase->completada,
            'fecha_completada'         => $fase->fecha_completada,
            'validado_docente'         => $fase->validado_docente,
            'fecha_validacion_docente' => $fase->fecha_validacion_docente,
            'nota_docente'             => $fase->nota_docente,
            'observaciones_docente'    => $fase->observaciones_docente,
        ];
    }

    private function sincronizarMiembros(Equipo $equipo, array $miembros): void
    {
        $equipo->miembros()->delete();
        foreach ($miembros as $m) {
            if (!empty($m['nombre'])) {
                $equipo->miembros()->create([
                    'nombre' => $m['nombre'],
                    'rol'    => $m['rol'] ?? null,
                ]);
            }
        }
    }
}
