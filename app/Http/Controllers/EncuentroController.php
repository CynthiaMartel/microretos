<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncuentroLoteRequest;
use App\Http\Requests\StoreEncuentroRequest;
use App\Http\Requests\UpdateEncuentroRequest;
use App\Http\Resources\EncuentroSeguimientoResource;
use App\Http\Resources\EquipoProgresoResource;
use App\Http\Resources\EncuentroResource;
use App\Models\Equipo;
use App\Models\EquipoMiembro;
use App\Models\Microproyecto;
use App\Models\Encuentro;
use App\Support\AliasGenerator;
use App\Support\CodigoLegible;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EncuentroController extends Controller
{
    /**
     * Techo de seguridad del listado (sin paginar): cada encuentro trae sus equipos y miembros,
     * así que sin límite la respuesta crece sin control. Mismo criterio que MicroretoIAController::index.
     * Pendiente: filtrar por curso académico en servidor (ver TAREAS_PENDIENTES.md).
     */
    private const MAX_LISTADO = 500;

    /**
     * Techo de misGrupos(): cada encuentro arrastra equipos, miembros, fases, reflexiones y
     * prototipos, así que el límite es más bajo que el del listado simple. Alimenta Mis equipos
     * y la Biblioteca de diagnósticos (los más recientes primero).
     */
    private const MAX_MIS_GRUPOS = 150;

    public function index(\Illuminate\Http\Request $request)
    {
        $user  = $request->user();
        $query = Encuentro::with([
            'docente:id,name',
            'colaboradores:id,name',
            'microproyecto:id,uuid,titulo,microreto_id,estado,familia_id,modulos_seleccionados,evaluacion_oficial',
            'microproyecto.microreto:id,titulo,empresa_nombre', 'microproyecto.familia:id,nombre',
            'equipos.miembros',
        ])->visiblesPara($user)->orderBy('created_at', 'desc')->take(self::MAX_LISTADO);

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
        $encuentro->load(['microproyecto:id,uuid,titulo,microreto_id,estado,familia_id,modulos_seleccionados,evaluacion_oficial', 'microproyecto.microreto:id,titulo,empresa_nombre', 'microproyecto.familia:id,nombre']);

        return response()->json(new EncuentroResource($encuentro), 201);
    }

    public function update(UpdateEncuentroRequest $request, $id)
    {
        $encuentro = Encuentro::where('id', $id)
            ->editablesPara($request->user())
            ->firstOrFail();

        $encuentro->update($request->validated());
        $encuentro->load(['microproyecto:id,uuid,titulo,microreto_id,estado,familia_id,modulos_seleccionados,evaluacion_oficial', 'microproyecto.microreto:id,titulo,empresa_nombre', 'microproyecto.familia:id,nombre']);

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
            'microproyecto:id,uuid,titulo,microreto_id,estado,familia_id,modulos_seleccionados,evaluacion_oficial',
            'microproyecto.microreto:id,titulo,empresa_nombre', 'microproyecto.familia:id,nombre',
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
                'error' => 'Alguno de los grupos de este encuentro ya tiene progreso (fases completadas, tareas, reflexiones o prototipos). Generar un código nuevo borraría ese trabajo. Usa "Editar grupo" si quieres cambiar el reparto sin perderlo.',
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
                if ((int) ($a['equipo_num'] ?? 0) === $n) {
                    // Protección de datos: solo el alias que el docente generó al crear el
                    // encuentro (snapshot `alumnados`). Si no es válido o choca con otro de la
                    // clase, se genera otro conservando su nombre de pila.
                    $equipo->miembros()->create([
                        'alias' => AliasGenerator::libre($a['alias'] ?? '', EquipoMiembro::aliasDelEncuentro($equipo->id)),
                        'rol'   => $a['rol'] ?? null,
                    ]);
                }
            }
        }

        $codigo = $this->generarCodigoClase();
        // El snapshot queda con los alias reales de los miembros (los antiguos podían traer nombre)
        $encuentro->update(['codigo_clase' => $codigo, 'alumnados' => $this->snapshotAlumnados($encuentro)]);

        $equipos = $encuentro->equipos()->with('miembros')->get()->map(fn($e) => [
            'id'       => $e->id,
            'nombre'   => $e->nombre,
            'token'    => $e->token,
            'miembros' => $e->miembros->map(fn($m) => [
                'id'     => $m->id,
                'alias'  => $m->alias,
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
     * recrear: hace upsert por id/alias dentro de cada equipo, para no perder el progreso ya
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
                // Mensaje para el docente: "Equipo N" (nombre por defecto en BD) se muestra como "Grupo N"
                'error' => 'No se puede reducir el número de grupos: '
                    . $bloqueados->pluck('nombre')->map(fn ($n) => preg_replace('/^equipo\s+(\d+)$/i', 'Grupo $1', trim((string) $n)))->implode(', ')
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
            $this->sincronizarMiembros($equipo, $alumnadosDelEquipo);
        }

        $encuentro->update(['num_equipos' => $numEquipos, 'alumnados' => $this->snapshotAlumnados($encuentro)]);

        $equipos = $encuentro->equipos()->with('miembros')->orderBy('numero_equipo')->get()->map(fn($e) => [
            'id'            => $e->id,
            'numero_equipo' => $e->numero_equipo,
            'nombre'        => $e->nombre,
            'token'         => $e->token,
            'fase_actual'   => $e->fase_actual,
            'nombres_confirmados' => $e->nombres_confirmados,
            'miembros' => $e->miembros->map(fn($m) => [
                'id'     => $m->id,
                'alias'  => $m->alias,
                'rol'    => $m->rol,
            ]),
        ]);

        return response()->json(['equipos' => $equipos]);
    }

    /**
     * PATCH /api/encuentros/{id}/miembros/{miembroId}/alias
     * El docente puede fijar/regenerar el alias de un miembro desde "Editar equipo" —
     * endpoint separado y de escritura inmediata (no va dentro del payload de
     * reestructurar-equipos) para que nunca pise con un valor obsoleto el alias que el
     * propio alumnado haya cambiado mientras tanto desde su workspace: sea cual sea el
     * último en guardar (docente aquí o alumnado en su F0), esa es la única fuente de
     * verdad, sin copia en ningún otro sitio.
     */
    public function actualizarAliasMiembro(\App\Http\Requests\ActualizarAliasMiembroRequest $request, $id, $miembroId)
    {
        $encuentro = Encuentro::where('id', $id)->editablesPara($request->user())->firstOrFail();

        $miembro = EquipoMiembro::with('equipo')->findOrFail((int) $miembroId);
        if (!$miembro->equipo || $miembro->equipo->encuentro_id !== $encuentro->id) {
            abort(404);
        }

        if ($miembro->equipo->nombres_confirmados) {
            return response()->json(['error' => 'El grupo ya confirmó sus alias y no se pueden cambiar.'], 422);
        }

        $alias = $request->validated('alias');
        if ($miembro->aliasRepetidoEnEncuentro($alias)) {
            return response()->json(['error' => 'Ese alias ya lo tiene otra persona de la clase. Genera otro con el dado.'], 422);
        }

        $miembro->update(['alias' => $alias]);
        $encuentro->update(['alumnados' => $this->snapshotAlumnados($encuentro)]);

        return response()->json(['alias' => $miembro->alias]);
    }

    // Upsert por id cuando el frontend lo manda (conserva el progreso ya asociado a ese
    // equipo_miembro: fortalezas, puntos de mejora). Si no viene id (alta nueva desde el
    // modal, o un alumno movido desde otro grupo) cae a upsert por alias.
    //
    // Protección de datos: solo alias, nunca nombre real. El alias de un miembro existente no
    // se toca aquí (va por actualizarAliasMiembro, de escritura inmediata); uno nuevo recibe
    // el alias que generó el docente si es válido y está libre en la clase, o uno generado.
    /** @param \Illuminate\Support\Collection<int, array<string, mixed>> $alumnados */
    private function sincronizarMiembros(Equipo $equipo, \Illuminate\Support\Collection $alumnados): void
    {
        $existentes        = $equipo->miembros()->get();
        $existentesPorId   = $existentes->keyBy('id');
        $existentesPorAlias = $existentes->keyBy(fn($m) => AliasGenerator::normalizar($m->alias));
        $conservados = [];

        foreach ($alumnados as $a) {
            $existente = !empty($a['id']) ? $existentesPorId->get((int) $a['id']) : null;
            $existente ??= $existentesPorAlias->get(AliasGenerator::normalizar($a['alias'] ?? ''));

            if ($existente) {
                $existente->update(['rol' => $a['rol'] ?? $existente->rol]);
                $conservados[] = $existente->id;
                continue;
            }

            $alias = $a['alias'] ?? '';
            // Un alumno movido desde otro grupo aún figura allí: su alias se conserva tal cual
            $alias = !empty($a['id']) && AliasGenerator::esValido($alias)
                ? $alias
                : AliasGenerator::libre($alias, EquipoMiembro::aliasDelEncuentro($equipo->id));
            $conservados[] = $equipo->miembros()->create([
                'alias' => $alias,
                'rol'   => $a['rol'] ?? null,
            ])->id;
        }

        $equipo->miembros()->whereNotIn('id', $conservados)->delete();
    }

    /**
     * Snapshot plano del reparto (encuentros.alumnados) a partir de los miembros reales:
     * solo alias, grupo y rol — nunca nombres.
     *
     * @return array<int, array{alias: string, equipo_num: int, rol: ?string}>
     */
    private function snapshotAlumnados(Encuentro $encuentro): array
    {
        $snapshot = [];
        foreach ($encuentro->equipos()->with('miembros')->orderBy('numero_equipo')->get() as $e) {
            foreach ($e->miembros as $m) {
                $snapshot[] = ['alias' => (string) $m->alias, 'equipo_num' => (int) $e->numero_equipo, 'rol' => $m->rol];
            }
        }

        return $snapshot;
    }

    /**
     * GET /api/encuentros/{id}/workspace
     * Dashboard docente: progreso de todos los equipos del encuentro. El nombre de este
     * endpoint es independiente de la ruta del SPA que lo consume — esa ruta se llama
     * /mis-grupos/:id (antes /mis-equipos/:id y /workspace/:id); no renombrar este endpoint junto a aquella.
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
                'colaboradores',
                'microproyecto.familia',
                'equipos.microproyecto.familia',
                'equipos.miembros',
                'equipos.fases',
                'equipos.reflexiones',
            ])->firstOrFail();
        $puedeEditar = $encuentro->esEditablePor($user);
        $encuentro->equipos->each(fn ($e) => $e->setRelation('encuentro', $encuentro));

        return response()->json([
            'encuentro' => [
                'id'               => $encuentro->id,
                'centro_educativo' => $encuentro->centro_educativo,
                'ciclo_formativo'  => $encuentro->ciclo_formativo,
                'curso'            => $encuentro->curso,
                'grupo'            => $encuentro->grupo,
                'fecha'            => $encuentro->fecha,
                'num_alumnos'      => $encuentro->num_alumnos,
                // Solo para quien puede editar (no a colaboradores de solo lectura)
                'codigo_ia'        => $puedeEditar ? $encuentro->codigo_ia : null,
                'puede_editar'     => $puedeEditar,
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
            'colaboradores',
            'docente:id,name',
            'microproyecto.familia',
            'equipos.microproyecto.familia',
            'equipos.miembros',
            'equipos.fases',
            'equipos.reflexiones',
            'equipos.prototipos',
        ])->whereHas('equipos')->visiblesPara($user)->orderBy('created_at', 'desc')->take(self::MAX_MIS_GRUPOS);

        // response()->json() sobre la colección: mismo array plano de siempre, sin envoltorio "data"
        return response()->json(EncuentroSeguimientoResource::collection($query->get()));
    }

    /**
     * GET /api/startup/proyectos/{uuid}/equipos
     * Seguimiento de equipos para la ficha de un microproyecto (StartupDayDetalle.vue /
     * ProyectoFichaModal.vue): a diferencia de workspace()/misGrupos() (que agrupan por
     * encuentro), aquí se agregan equipos de TODOS los encuentros vinculados a este
     * proyecto (equipos.microproyecto_id), porque la ficha ya está a nivel de proyecto y
     * queremos ver "cómo lo ha resuelto el alumnado" en conjunto, no encuentro por encuentro.
     */
    public function equiposDeProyecto(Request $request, string $uuid)
    {
        $user = $request->user();
        $proyecto = Microproyecto::where('uuid', $uuid)
            ->whereHas('encuentros', fn ($q) => $q->visiblesPara($user))
            ->firstOrFail();

        // Solo los equipos de encuentros que el usuario puede ver: ver uno de los encuentros
        // del proyecto no da acceso a los grupos de los demás
        $equipos = $proyecto->equipos()
            ->whereHas('encuentro', fn ($q) => $q->visiblesPara($user))
            ->with(['microproyecto.familia', 'miembros', 'fases', 'reflexiones', 'prototipos',
                    'encuentro:id,grupo,curso,fecha,user_id,centro_educativo_id', 'encuentro.colaboradores'])
            ->get();

        return response()->json([
            'equipos' => $this->formatEquiposConProgreso($equipos),
        ]);
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

    // Delegado en EquipoProgresoResource (misma forma que consumen workspace() y la ficha del proyecto)
    private function formatEquiposConProgreso($equipos)
    {
        return EquipoProgresoResource::collection($equipos)->resolve();
    }

    private function generarCodigoClase(): string
    {
        return CodigoLegible::generar(fn($codigo) => Encuentro::where('codigo_clase', $codigo)->exists());
    }
}
