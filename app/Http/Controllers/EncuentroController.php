<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncuentroLoteRequest;
use App\Http\Requests\StoreEncuentroRequest;
use App\Http\Requests\UpdateEncuentroRequest;
use App\Http\Resources\EncuentroResource;
use App\Models\Equipo;
use App\Models\Microproyecto;
use App\Models\Encuentro;
use App\Support\CodigoLegible;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EncuentroController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user  = $request->user();
        $query = Encuentro::with([
            'docente:id,name',
            'colaboradores:id,name',
            'microproyecto:id,uuid,titulo,microreto_id,estado',
            'microproyecto.microreto:id,titulo,empresa_nombre',
            'equipos.miembros',
        ])->visiblesPara($user)->orderBy('created_at', 'desc');

        // response()->json() evita el envoltorio {"data": [...]} que Laravel aplica
        // cuando se devuelve una Resource/colección directamente desde el controller —
        // el frontend espera un array plano (mismo criterio que MicroretoFichaResource).
        return response()->json(EncuentroResource::collection($query->get()));
    }

    public function store(StoreEncuentroRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();
        $validated['user_id'] = $user->id;

        if ($user->centro_educativo_id && $user->centroEducativo) {
            $validated['centro_educativo']    = $user->centroEducativo->nombre;
            $validated['centro_educativo_id'] = $user->centro_educativo_id;
        }

        if (!empty($validated['microproyecto_id'])) {
            $proyecto = Microproyecto::find($validated['microproyecto_id']);
            $validated['fecha_fin'] = $this->fechaFinSugerida($validated['fecha'], $proyecto);
        }

        $encuentro = Encuentro::create($validated);
        $encuentro->load(['microproyecto:id,uuid,titulo,microreto_id,estado', 'microproyecto.microreto:id,titulo,empresa_nombre']);

        return response()->json(new EncuentroResource($encuentro), 201);
    }

    public function update(UpdateEncuentroRequest $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->editablesPara($request->user())
            ->firstOrFail();

        $encuentro->update($request->validated());
        $encuentro->load(['microproyecto:id,uuid,titulo,microreto_id,estado', 'microproyecto.microreto:id,titulo,empresa_nombre']);

        return response()->json(new EncuentroResource($encuentro));
    }

    // Sugiere fecha_fin a partir de las clases estimadas en las fases del proyecto
    // (heurística centralizada en Microproyecto::fechaFinSugerida). Es solo una
    // sugerencia editable, nunca un límite duro en creación.
    private function fechaFinSugerida(string $fecha, ?Microproyecto $proyecto): ?string
    {
        return $proyecto?->fechaFinSugerida(Carbon::parse($fecha))?->toDateString();
    }

    public function storeLote(StoreEncuentroLoteRequest $request)
    {
        $user = $request->user();

        foreach ($request->validated()['encuentros'] as $s) {
            $s['user_id'] = $user->id;
            // El centro se sella siempre desde el usuario autenticado — nunca se
            // confía en el string que pueda mandar el cliente (mismo criterio que store()).
            if ($user->centro_educativo_id && $user->centroEducativo) {
                $s['centro_educativo']    = $user->centroEducativo->nombre;
                $s['centro_educativo_id'] = $user->centro_educativo_id;
            }
            Encuentro::create($s);
        }

        return response()->noContent();
    }

    public function show(Request $request, $id)
    {
        $encuentro = Encuentro::with([
            'docente:id,name',
            'colaboradores:id,name',
            'microproyecto:id,uuid,titulo,microreto_id,estado',
            'microproyecto.microreto:id,titulo,empresa_nombre',
        ])
            ->where('id', $id)
            ->visiblesPara($request->user())
            ->firstOrFail();

        return response()->json(new EncuentroResource($encuentro));
    }

    public function destroy(Request $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->editablesPara($request->user())
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
            ->editablesPara($request->user())
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

    /**
     * POST /api/encuentros/{id}/codigo-ia
     * Genera (o regenera) el código que desbloquea "Sugerir con IA" en el workspace.
     * Regenerarlo no bloquea de nuevo a los equipos que ya lo introdujeron.
     */
    public function generarCodigoIa(Request $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->editablesPara($request->user())
            ->firstOrFail();

        $codigo = CodigoLegible::generar(fn($c) => Encuentro::where('codigo_ia', $c)->exists());
        $encuentro->update(['codigo_ia' => $codigo]);

        return response()->json(['codigo_ia' => $codigo]);
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
            ->editablesPara($request->user())
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
            'id'            => $e->id,
            'numero_equipo' => $e->numero_equipo,
            'nombre'        => $e->nombre,
            'token'         => $e->token,
            'fase_actual'   => $e->fase_actual,
            'nombres_confirmados' => $e->nombres_confirmados,
            'miembros' => $e->miembros->map(fn($m) => [
                'id'     => $m->id,
                'nombre' => $m->nombre,
                'alias'  => $m->alias,
                'rol'    => $m->rol,
            ]),
        ]);

        return response()->json(['equipos' => $equipos]);
    }

    // Upsert por id cuando el frontend lo manda (permite renombrar sin perder el progreso
    // ya asociado a ese equipo_miembro: fortalezas, puntos de mejora, alias). Si no viene id
    // (alta nueva desde el modal, o llamadas antiguas) cae a upsert por nombre como antes.
    //
    // Nombre bloqueado una vez el equipo confirma nombres en su F0 (paso explícito, no basta
    // con guardar/completar la fase): a partir de ahí el docente ya no puede cambiarlo desde
    // "Editar equipo" (solo mover de equipo o cambiar el rol).
    private function sincronizarMiembrosPorNombre(Equipo $equipo, \Illuminate\Support\Collection $alumnados): void
    {
        $nombreBloqueado     = $equipo->nombres_confirmados;
        $existentesPorId     = $equipo->miembros()->get()->keyBy('id');
        $existentesPorNombre = $equipo->miembros()->get()->keyBy(fn($m) => mb_strtolower(trim($m->nombre)));
        $conservados = [];

        foreach ($alumnados as $a) {
            $existente = !empty($a['id']) ? $existentesPorId->get((int) $a['id']) : null;
            if (!$existente) {
                $existente = $existentesPorNombre->get(mb_strtolower(trim($a['nombre'])));
            }

            if ($existente) {
                $existente->update([
                    'nombre' => $nombreBloqueado ? $existente->nombre : $a['nombre'],
                    'rol'    => $a['rol'] ?? $existente->rol,
                ]);
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
     * Dashboard docente: progreso de todos los equipos del encuentro. El nombre de este
     * endpoint es independiente de la ruta del SPA que lo consume — esa ruta se llama
     * /mis-grupos/:id (antes /workspace/:id); no renombrar este endpoint junto a aquella.
     *
     * Los equipos se obtienen vía encuentro->equipos (FK real equipos.encuentro_id), no vía
     * encuentro->microproyecto->equipos — varios encuentros pueden compartir microproyecto
     * (p.ej. distintos grupos/clases trabajando el mismo reto), y navegar por el proyecto
     * mezclaría equipos de encuentros distintos en esta misma pantalla (ver misGrupos()).
     */
    public function workspace(Request $request, $id)
    {
        $user      = $request->user();
        $encuentro = Encuentro::where('id', $id)->visiblesPara($user)
            ->with([
                'microproyecto.familia',
                'equipos.microproyecto.familia',
                'equipos.miembros',
                'equipos.fases',
                'equipos.reflexiones',
            ])->firstOrFail();

        return response()->json([
            'encuentro' => [
                'id'               => $encuentro->id,
                'centro_educativo' => $encuentro->centro_educativo,
                'ciclo_formativo'  => $encuentro->ciclo_formativo,
                'curso'            => $encuentro->curso,
                'grupo'            => $encuentro->grupo,
                'fecha'            => $encuentro->fecha,
                'num_alumnos'      => $encuentro->num_alumnos,
                'codigo_ia'        => $encuentro->codigo_ia,
            ],
            'proyecto' => $this->formatProyecto($encuentro->microproyecto),
            'equipos' => $this->formatEquiposConProgreso($encuentro->equipos),
        ]);
    }

    /**
     * GET /api/encuentros/mis-grupos
     * Vista agregada: todos los encuentros del docente (o del centro, para admin)
     * que ya tienen equipos, con el mismo detalle de progreso que el endpoint
     * /encuentros/{id}/workspace (ver workspace() arriba) pero para todos a la vez —
     * pensada para "Mis grupos" (seguimiento sin entrar encuentro por encuentro).
     *
     * Los equipos se obtienen vía encuentro->equipos (FK real equipos.encuentro_id),
     * no vía encuentro->microproyecto->equipos: cada equipo lleva su propio proyecto
     * (equipos.microproyecto_id), que no tiene por qué coincidir entre equipos del
     * mismo encuentro.
     */
    public function misGrupos(Request $request)
    {
        $user  = $request->user();
        $query = Encuentro::with([
            'equipos.microproyecto.familia',
            'equipos.miembros',
            'equipos.fases',
            'equipos.reflexiones',
        ])->whereHas('equipos')->visiblesPara($user)->orderBy('created_at', 'desc');

        $grupos = $query->get()->map(function ($encuentro) {
            return [
                'encuentro' => [
                    'id'               => $encuentro->id,
                    'grupo'            => $encuentro->grupo,
                    'ciclo_formativo'  => $encuentro->ciclo_formativo,
                    'curso'            => $encuentro->curso,
                    'centro_educativo' => $encuentro->centro_educativo,
                    'fecha'            => $encuentro->fecha,
                    'codigo_clase'     => $encuentro->codigo_clase,
                    'codigo_ia'        => $encuentro->codigo_ia,
                ],
                'equipos' => $this->formatEquiposConProgreso($encuentro->equipos),
            ];
        });

        return response()->json($grupos->values());
    }

    private function formatProyecto(?Microproyecto $proyecto)
    {
        return $proyecto ? [
            'uuid'               => $proyecto->uuid,
            'titulo'             => $proyecto->titulo,
            'estado'             => $proyecto->estado,
            'evaluacion_oficial' => $proyecto->evaluacion_oficial,
            'familia'            => $proyecto->familia?->nombre,
            'microreto_id'       => $proyecto->microreto_id,
        ] : null;
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
                'proyecto'        => $this->formatProyecto($equipo->microproyecto),
                'codigo_acceso'   => $equipo->codigo_acceso,
                'token'           => $equipo->token,
                'fase_actual'     => $equipo->fase_actual,
                'fases_completas' => $fasesCompletas,
                'diagnostico_final'       => $equipo->diagnostico_final,
                'diagnostico_generado_en' => $equipo->diagnostico_generado_en,
                'miembros'        => $equipo->miembros->map(fn($m) => [
                    'id'         => $m->id,
                    'nombre'     => $m->nombre,
                    'alias'      => $m->alias,
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
        return CodigoLegible::generar(fn($codigo) => Encuentro::where('codigo_clase', $codigo)->exists());
    }
}
