<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncuentroLoteRequest;
use App\Http\Requests\StoreEncuentroRequest;
use App\Http\Requests\UpdateEncuentroRequest;
use App\Models\Equipo;
use App\Models\Microproyecto;
use App\Models\Encuentro;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EncuentroController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user  = $request->user();
        $query = Encuentro::with([
            'docente:id,name',
            'microproyecto:id,uuid,titulo,microreto_id,estado',
            'microproyecto.microreto:id,titulo,empresa_nombre',
        ])->orderBy('created_at', 'desc');

        if ($user?->isDocente()) {
            $query->where('user_id', $user->id);
        } elseif ($user?->isAdmin() && $user->centro_educativo_id) {
            $nombreCentro = $user->centroEducativo?->nombre;
            if ($nombreCentro) {
                $query->where('centro_educativo', $nombreCentro);
            }
        }
        // Superadmin: sin filtro

        return $query->get()->map(fn($encuentro) => $this->conTituloProyecto($encuentro));
    }

    public function store(StoreEncuentroRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();
        $validated['user_id'] = $user->id;

        if ($user->centro_educativo_id && $user->centroEducativo) {
            $validated['centro_educativo'] = $user->centroEducativo->nombre;
        }

        if (!empty($validated['microproyecto_id'])) {
            $proyecto = Microproyecto::find($validated['microproyecto_id']);
            $validated['fecha_fin'] = $this->fechaFinSugerida($validated['fecha'], $proyecto);
        }

        $encuentro = Encuentro::create($validated);
        $encuentro->load(['microproyecto:id,uuid,titulo,microreto_id,estado', 'microproyecto.microreto:id,titulo,empresa_nombre']);

        return response()->json($this->conTituloProyecto($encuentro), 201);
    }

    public function update(UpdateEncuentroRequest $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $encuentro->update($request->validated());
        $encuentro->load(['microproyecto:id,uuid,titulo,microreto_id,estado', 'microproyecto.microreto:id,titulo,empresa_nombre']);

        return response()->json($this->conTituloProyecto($encuentro));
    }

    // Sugiere fecha_fin a partir de las clases estimadas en las fases del proyecto
    // (heurística centralizada en Microproyecto::fechaFinSugerida). Es solo una
    // sugerencia editable, nunca un límite duro en creación.
    private function fechaFinSugerida(string $fecha, ?Microproyecto $proyecto): ?string
    {
        return $proyecto?->fechaFinSugerida(Carbon::parse($fecha))?->toDateString();
    }

    private function conTituloProyecto(Encuentro $encuentro): array
    {
        $data = $encuentro->toArray();
        $data['microproyecto_uuid'] = $encuentro->microproyecto?->uuid;
        $data['proyecto_titulo']    = $encuentro->microproyecto?->titulo;
        $data['microreto_id']       = $encuentro->microproyecto?->microreto_id;
        $data['microreto_titulo']   = $encuentro->microproyecto?->microreto?->titulo;
        unset($data['microproyecto']);
        return $data;
    }

    public function storeLote(StoreEncuentroLoteRequest $request)
    {
        $userId = $request->user()->id;
        foreach ($request->validated()['encuentros'] as $s) {
            $s['user_id'] = $userId;
            Encuentro::create($s);
        }

        return response()->noContent();
    }

    public function show(Request $request, $id)
    {
        return Encuentro::with(['microproyecto:id,uuid,titulo,estado'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function destroy(Request $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $encuentro->delete();
        return response()->noContent();
    }

    /**
     * POST /api/encuentros/{id}/crear-codigo
     * Crea equipos en el microproyecto del encuentro y genera un codigo_clase.
     */
    public function crearCodigo(Request $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $proyecto = $encuentro->microproyecto;

        if (!$proyecto || !in_array($proyecto->estado, ['propuesta', 'validado'])) {
            return response()->json([
                'error' => 'Este encuentro no tiene ningún proyecto publicado. Asocia un proyecto al encuentro y márcalo como Propuesta primero.',
            ], 422);
        }

        // Un microproyecto puede tener varios encuentros (distintas clases/grupos trabajando el
        // mismo reto) — solo se tocan los equipos de ESTE encuentro, nunca los de otro.
        if ($this->encuentroTieneProgreso($encuentro)) {
            return response()->json([
                'error' => 'Alguno de los equipos de este encuentro ya tiene progreso (fases completadas, tareas, reflexiones o prototipos). Generar un código nuevo borraría ese trabajo. Usa "Reestructurar equipo" si quieres cambiar el reparto sin perderlo.',
            ], 422);
        }

        $numEquipos = max(1, min(30, (int) ($encuentro->num_equipos ?? 3)));
        $alumnados  = $encuentro->alumnados ?? [];

        $encuentro->equipos()->delete();

        for ($n = 1; $n <= $numEquipos; $n++) {
            $equipo = Equipo::create([
                'microproyecto_id' => $proyecto->id,
                'encuentro_id'     => $encuentro->id,
                'nombre'           => "Equipo {$n}",
                'numero_equipo'    => $n,
            ]);

            foreach ($alumnados as $a) {
                if ((int) ($a['equipo_num'] ?? 0) === $n && !empty($a['nombre'])) {
                    $equipo->miembros()->create([
                        'nombre' => $a['nombre'],
                        'rol'    => $a['rol'] ?? null,
                    ]);
                }
            }
        }

        $codigo = $this->generarCodigoClase();
        $encuentro->update(['codigo_clase' => $codigo]);

        $equipos = $encuentro->equipos()->with('miembros')->get()->map(fn($e) => [
            'id'       => $e->id,
            'nombre'   => $e->nombre,
            'token'    => $e->token,
            'miembros' => $e->miembros->map(fn($m) => [
                'nombre' => $m->nombre,
                'rol'    => $m->rol,
            ]),
        ]);

        return response()->json(['codigo_clase' => $codigo, 'equipos' => $equipos], 201);
    }

    private function equipoTieneProgreso(Equipo $equipo): bool
    {
        return $equipo->fases()->where('completada', true)->exists()
            || $equipo->tareas()->exists()
            || $equipo->reflexiones()->exists()
            || $equipo->prototipos()->exists();
    }

    private function encuentroTieneProgreso(Encuentro $encuentro): bool
    {
        return $encuentro->equipos->contains(fn($e) => $this->equipoTieneProgreso($e));
    }

    /**
     * PATCH /api/encuentros/{id}/reestructurar-equipos
     * Actualiza el reparto de alumnado/equipos de un encuentro ya creado, SIN borrar y
     * recrear: hace upsert por nombre dentro de cada equipo, para no perder el progreso ya
     * hecho (tareas, reflexiones, fortalezas/puntos de mejora ya escritos, etc.). Si reducir
     * el número de equipos obligaría a eliminar uno con progreso real, bloquea la operación.
     */
    public function reestructurarEquipos(\App\Http\Requests\ReestructurarEquiposRequest $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $proyecto = $encuentro->microproyecto;
        if (!$proyecto) {
            return response()->json(['error' => 'Este encuentro no tiene ningún proyecto asociado.'], 422);
        }

        $numEquipos = (int) $request->validated('num_equipos');
        $alumnados  = $request->validated('alumnados');

        $equiposActuales = $encuentro->equipos()->get()->keyBy('numero_equipo');

        $aEliminar  = $equiposActuales->filter(fn($e) => (int) $e->numero_equipo > $numEquipos);
        $bloqueados = $aEliminar->filter(fn($e) => $this->equipoTieneProgreso($e));

        if ($bloqueados->isNotEmpty()) {
            return response()->json([
                'error' => 'No se puede reducir el número de equipos: '
                    . $bloqueados->pluck('nombre')->implode(', ')
                    . ' ya tiene progreso real (fases completadas, tareas, reflexiones o prototipos).',
            ], 422);
        }

        foreach ($aEliminar as $equipo) {
            $equipo->delete();
        }

        for ($n = 1; $n <= $numEquipos; $n++) {
            $equipo = $equiposActuales->get($n);
            if (!$equipo) {
                $equipo = Equipo::create([
                    'microproyecto_id' => $proyecto->id,
                    'encuentro_id'     => $encuentro->id,
                    'nombre'           => "Equipo {$n}",
                    'numero_equipo'    => $n,
                ]);
            }

            $alumnadosDelEquipo = collect($alumnados)->filter(fn($a) => (int) $a['equipo_num'] === $n);
            $this->sincronizarMiembrosPorNombre($equipo, $alumnadosDelEquipo);
        }

        $encuentro->update(['num_equipos' => $numEquipos, 'alumnados' => $alumnados]);

        $equipos = $encuentro->equipos()->with('miembros')->orderBy('numero_equipo')->get()->map(fn($e) => [
            'id'       => $e->id,
            'nombre'   => $e->nombre,
            'token'    => $e->token,
            'miembros' => $e->miembros->map(fn($m) => ['nombre' => $m->nombre, 'rol' => $m->rol]),
        ]);

        return response()->json(['equipos' => $equipos]);
    }

    // Upsert por nombre (el docente no ve ids de equipo_miembros, solo nombres): actualiza el
    // rol de los que ya existan, crea los nuevos, borra los que ya no aparezcan en el reparto.
    private function sincronizarMiembrosPorNombre(Equipo $equipo, \Illuminate\Support\Collection $alumnados): void
    {
        $existentes  = $equipo->miembros()->get()->keyBy(fn($m) => mb_strtolower(trim($m->nombre)));
        $conservados = [];

        foreach ($alumnados as $a) {
            $clave     = mb_strtolower(trim($a['nombre']));
            $existente = $existentes->get($clave);

            if ($existente) {
                $existente->update(['rol' => $a['rol'] ?? $existente->rol]);
                $conservados[] = $existente->id;
            } else {
                $conservados[] = $equipo->miembros()->create([
                    'nombre' => $a['nombre'],
                    'rol'    => $a['rol'] ?? null,
                ])->id;
            }
        }

        $equipo->miembros()->whereNotIn('id', $conservados)->delete();
    }

    /**
     * GET /api/encuentros/{id}/workspace
     * Dashboard docente: progreso de todos los equipos del encuentro.
     */
    public function workspace(Request $request, $id)
    {
        $user  = $request->user();
        $query = Encuentro::where('id', $id);

        if ($user->isDocente()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAdmin() && $user->centro_educativo_id) {
            $nombreCentro = $user->centroEducativo?->nombre;
            if ($nombreCentro) {
                $query->where('centro_educativo', $nombreCentro);
            }
        }
        // Superadmin: sin filtro

        $encuentro = $query->firstOrFail();

        $proyecto = $encuentro->microproyecto?->load([
            'equipos.miembros',
            'equipos.fases',
            'equipos.reflexiones',
        ]);

        return response()->json([
            'encuentro' => [
                'id'               => $encuentro->id,
                'centro_educativo' => $encuentro->centro_educativo,
                'ciclo_formativo'  => $encuentro->ciclo_formativo,
                'curso'            => $encuentro->curso,
                'grupo'            => $encuentro->grupo,
                'fecha'            => $encuentro->fecha,
                'num_alumnos'      => $encuentro->num_alumnos,
            ],
            'proyecto' => $proyecto ? [
                'uuid'               => $proyecto->uuid,
                'titulo'             => $proyecto->titulo,
                'estado'             => $proyecto->estado,
                'evaluacion_oficial' => $proyecto->evaluacion_oficial,
            ] : null,
            'equipos' => $proyecto ? $this->formatEquiposConProgreso($proyecto->equipos) : collect(),
        ]);
    }

    /**
     * GET /api/encuentros/mis-grupos
     * Vista agregada: todos los encuentros del docente (o del centro, para admin)
     * que ya tienen equipos, con el mismo detalle de progreso que /workspace
     * pero para todos a la vez — pensada para "Mis grupos" (seguimiento sin
     * entrar encuentro por encuentro).
     */
    public function misGrupos(Request $request)
    {
        $user  = $request->user();
        $query = Encuentro::with([
            'microproyecto.equipos.miembros',
            'microproyecto.equipos.fases',
            'microproyecto.equipos.reflexiones',
        ])->whereHas('microproyecto.equipos')->orderBy('created_at', 'desc');

        if ($user->isDocente()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAdmin() && $user->centro_educativo_id) {
            $nombreCentro = $user->centroEducativo?->nombre;
            if ($nombreCentro) {
                $query->where('centro_educativo', $nombreCentro);
            }
        }
        // Superadmin: sin filtro

        $grupos = $query->get()->map(function ($encuentro) {
            $proyecto = $encuentro->microproyecto;

            return [
                'encuentro' => [
                    'id'               => $encuentro->id,
                    'grupo'            => $encuentro->grupo,
                    'ciclo_formativo'  => $encuentro->ciclo_formativo,
                    'centro_educativo' => $encuentro->centro_educativo,
                    'fecha'            => $encuentro->fecha,
                ],
                'proyecto' => $proyecto ? [
                    'uuid'               => $proyecto->uuid,
                    'titulo'             => $proyecto->titulo,
                    'estado'             => $proyecto->estado,
                    'evaluacion_oficial' => $proyecto->evaluacion_oficial,
                ] : null,
                'equipos' => $proyecto ? $this->formatEquiposConProgreso($proyecto->equipos) : collect(),
            ];
        });

        return response()->json($grupos->values());
    }

    private function formatEquiposConProgreso($equipos)
    {
        return $equipos->map(function ($equipo) {
            $fasesCompletas = $equipo->fases->filter(fn($f) => $f->completada)->count();

            $fases = collect(range(0, 4))->map(function ($n) use ($equipo) {
                $fase = $equipo->fases->firstWhere('numero_fase', $n);
                return [
                    'numero_fase'           => $n,
                    'completada'            => $fase?->completada ?? false,
                    'validado_docente'      => $fase?->validado_docente ?? false,
                    'nota_docente'          => $fase?->nota_docente,
                    'observaciones_docente' => $fase?->observaciones_docente,
                    'datos'                 => $fase?->datos,
                    'fecha_completada'      => $fase?->fecha_completada,
                ];
            });

            return [
                'id'              => $equipo->id,
                'nombre'          => $equipo->nombre,
                'codigo_acceso'   => $equipo->codigo_acceso,
                'token'           => $equipo->token,
                'fase_actual'     => $equipo->fase_actual,
                'fases_completas' => $fasesCompletas,
                'miembros'        => $equipo->miembros->map(fn($m) => [
                    'id'         => $m->id,
                    'nombre'     => $m->nombre,
                    'rol'        => $m->rol,
                    'fortalezas' => $m->fortalezas,
                    'dafo'       => $m->dafo,
                ]),
                'fases'       => $fases,
                'reflexiones' => $equipo->reflexiones->map(fn($r) => [
                    'id'           => $r->id,
                    'tipo'         => $r->tipo,
                    'autor_nombre' => $r->autor_nombre,
                    'respuestas'   => $r->respuestas,
                    'created_at'   => $r->created_at,
                ]),
            ];
        });
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
        } while (Encuentro::where('codigo_clase', $codigo)->exists());
        return $codigo;
    }
}
