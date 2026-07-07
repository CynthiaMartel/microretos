<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\Microproyecto;
use App\Models\Sesion;
use Illuminate\Support\Str;

class EquipoGestionController extends Controller
{
    // ── CRUD equipos (docente) ────────────────────────────────────────────────

    /**
     * GET /api/startup/proyectos/{uuid}/equipos
     * Lista todos los grupos del proyecto con su progreso por fase.
     */
    public function index($uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $equipos = $proyecto->equipos()
            ->with(['miembros', 'fases', 'reflexiones'])
            ->get()
            ->map(fn($e) => $this->formatEquipoDocente($e));

        return response()->json($equipos);
    }

    /**
     * POST /api/startup/proyectos/{uuid}/equipos
     * Crea uno o varios equipos en bloque.
     * Body: { "equipos": [{"nombre": "Grupo A"}, {"nombre": "Grupo B"}] }
     */
    public function store(Request $request, $uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'equipos'          => 'required|array|min:1|max:30',
            'equipos.*.nombre' => 'required|string|max:50',
        ]);

        $sesionId = Sesion::where('microproyecto_id', $proyecto->id)->value('id');

        $created = collect($data['equipos'])->map(fn($e) => Equipo::create([
            'microproyecto_id' => $proyecto->id,
            'sesion_id'        => $sesionId,
            'nombre'           => $e['nombre'],
        ]));

        return response()->json($created->map(fn($e) => $this->formatEquipoDocente($e->fresh(['miembros', 'fases']))), 201);
    }

    /**
     * DELETE /api/startup/equipos/{id}
     */
    public function destroy(int $id)
    {
        Equipo::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Validación docente de fases ───────────────────────────────────────────

    /**
     * PATCH /api/startup/equipos/{id}/fase/{fase}/validar
     * El docente valida la fase de un equipo, opcionalmente con nota y observaciones.
     */
    public function validarFase(Request $request, int $equipoId, int $numeroFase)
    {
        if (!in_array($numeroFase, [0, 1, 2, 3, 4])) {
            return response()->json(['error' => 'Fase no válida.'], 422);
        }

        $equipo = Equipo::findOrFail($equipoId);

        $data = $request->validate([
            'nota_docente'          => 'nullable|numeric|min:0|max:10',
            'observaciones_docente' => 'nullable|string|max:2000',
        ]);

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            array_merge($data, [
                'validado_docente'         => true,
                'fecha_validacion_docente' => now(),
                'completada'               => true,
                'fecha_completada'         => now(),
            ])
        );

        return response()->json(['ok' => true, 'fase' => $fase]);
    }

    /**
     * PATCH /api/startup/equipos/{id}/fase/{fase}/rechazar
     * El docente devuelve la fase para que el equipo la revise.
     */
    public function rechazarFase(Request $request, int $equipoId, int $numeroFase)
    {
        $equipo = Equipo::findOrFail($equipoId);

        $data = $request->validate([
            'observaciones_docente' => 'required|string|max:2000',
        ]);

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            array_merge($data, [
                'validado_docente' => false,
                'completada'       => false,
            ])
        );

        // Retroceder fase_actual del equipo si estaba más adelante
        if ($equipo->fase_actual > $numeroFase) {
            $equipo->update(['fase_actual' => $numeroFase]);
        }

        return response()->json(['ok' => true, 'fase' => $fase]);
    }

    /**
     * PATCH /api/startup/equipos/{id}/evaluar
     * Evaluación final del docente en F4: RA + niveles + nota global.
     * Guarda en equipo_fases.datos de la fase 4.
     */
    public function evaluar(Request $request, int $equipoId)
    {
        $equipo = Equipo::findOrFail($equipoId);

        $data = $request->validate([
            'evaluacion'                   => 'required|array',
            'evaluacion.ras'               => 'required|array',
            'evaluacion.ras.*.ra'          => 'required|string',
            'evaluacion.ras.*.nivel'       => 'required|in:no_alcanzado,en_proceso,alcanzado,superado',
            'evaluacion.ras.*.observaciones' => 'nullable|string|max:1000',
            'evaluacion.nota_opcional'     => 'nullable|numeric|min:0|max:10',
            'nota_docente'                 => 'nullable|numeric|min:0|max:10',
            'observaciones_docente'        => 'nullable|string|max:2000',
        ]);

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => 4],
            [
                'datos'                    => ['evaluacion_docente' => $data['evaluacion']],
                'validado_docente'         => true,
                'fecha_validacion_docente' => now(),
                'nota_docente'             => $data['nota_docente'] ?? null,
                'observaciones_docente'    => $data['observaciones_docente'] ?? null,
                'completada'               => true,
                'fecha_completada'         => now(),
            ]
        );

        return response()->json(['ok' => true, 'fase' => $fase]);
    }

    // ── Crear equipos desde datos del wizard ─────────────────────────────────

    /**
     * POST /api/startup/proyectos/{uuid}/crear-equipos
     * Lee el JSON equipo.alumnos del proyecto (con equipo_num en cada alumno),
     * crea los registros en la tabla equipos y genera el codigo_clase del proyecto.
     */
    public function crearEquipos($uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $equipoData = $proyecto->equipo ?? [];
        $alumnos    = $equipoData['alumnos'] ?? [];
        $numEquipos = (int) ($equipoData['num_equipos'] ?? 0);
        $nombres    = $equipoData['nombres_equipos'] ?? [];

        // Fuente canónica: sesion.alumnados (la sesión es donde vive el alumnado real)
        $sesion = Sesion::where('microproyecto_id', $proyecto->id)->first();
        if (!$alumnos && $sesion) {
            $alumnos = $sesion->alumnados ?? [];
            if (!$numEquipos) {
                $numEquipos = (int) ($sesion->num_equipos ?? 1);
            }
        }

        if (!$alumnos && !$numEquipos) {
            return response()->json(['error' => 'El proyecto no tiene alumnado definido. Configúralo en la sesión asociada.'], 422);
        }

        // Eliminar equipos existentes (regeneración)
        $proyecto->equipos()->delete();

        // Asegurar al menos tantos equipos como el máximo equipo_num de los alumnos
        $maxNum = collect($alumnos)->max('equipo_num') ?? 1;
        $total  = max($numEquipos, $maxNum, 1);

        for ($n = 1; $n <= $total; $n++) {
            $nombre = $nombres[$n - 1] ?? "Equipo {$n}";

            $equipo = Equipo::create([
                'microproyecto_id' => $proyecto->id,
                'sesion_id'        => $sesion?->id,
                'nombre'           => $nombre,
            ]);

            // Asignar miembros con equipo_num === $n
            foreach ($alumnos as $a) {
                if ((int) ($a['equipo_num'] ?? 0) === $n && !empty($a['nombre'])) {
                    $equipo->miembros()->create(['nombre' => $a['nombre'], 'rol' => $a['rol'] ?? null]);
                }
            }
        }

        // Generar código de clase único y guardarlo en la sesión
        $codigoClase = $this->generarCodigoClase();
        if ($sesion) {
            $sesion->update(['codigo_clase' => $codigoClase]);
        }

        $equipos = $proyecto->equipos()->with('miembros')->get()
            ->map(fn($e) => $this->formatEquipoDocente($e->load(['fases', 'reflexiones'])));

        return response()->json([
            'codigo_clase' => $codigoClase,
            'equipos'      => $equipos,
        ], 201);
    }

    private function generarCodigoClase(): string
    {
        $charset = 'ABCDEFGHJKLMNPQRTUVWXY';
        do {
            $letras  = $charset[random_int(0, strlen($charset) - 1)]
                     . $charset[random_int(0, strlen($charset) - 1)]
                     . $charset[random_int(0, strlen($charset) - 1)];
            $numeros = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $codigo  = $letras . '-' . $numeros;
        } while (Sesion::where('codigo_clase', $codigo)->exists());
        return $codigo;
    }

    // ── Proyectar pantalla de acceso ─────────────────────────────────────────

    /**
     * GET /api/startup/proyectos/{uuid}/pantalla-acceso
     * Devuelve la lista de equipos con códigos y tokens para generar QRs en el docente.
     */
    public function pantallaAcceso($uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $equipos = $proyecto->equipos()->get()->map(fn($e) => [
            'id'            => $e->id,
            'nombre'        => $e->nombre,
            'codigo_acceso' => $e->codigo_acceso,
            'token'         => $e->token,
        ]);

        return response()->json([
            'proyecto_titulo' => $proyecto->titulo,
            'equipos'         => $equipos,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatEquipoDocente(Equipo $equipo): array
    {
        $progreso = $equipo->fases->filter(fn($f) => $f->completada)->count();

        return [
            'id'             => $equipo->id,
            'nombre'         => $equipo->nombre,
            'codigo_acceso'  => $equipo->codigo_acceso,
            'token'          => $equipo->token,
            'fase_actual'    => $equipo->fase_actual,
            'fases_completas'=> $progreso,
            'miembros'       => $equipo->miembros->map(fn($m) => [
                'id'     => $m->id,
                'nombre' => $m->nombre,
                'rol'    => $m->rol,
            ]),
            'fases' => collect(range(0, 4))->map(function ($n) use ($equipo) {
                $fase = $equipo->fases->firstWhere('numero_fase', $n);
                return [
                    'numero_fase'           => $n,
                    'completada'            => $fase?->completada ?? false,
                    'validado_docente'      => $fase?->validado_docente ?? false,
                    'nota_docente'          => $fase?->nota_docente,
                    'observaciones_docente' => $fase?->observaciones_docente,
                    'fecha_completada'      => $fase?->fecha_completada,
                ];
            }),
            'reflexiones_count' => $equipo->reflexiones->count(),
        ];
    }
}
