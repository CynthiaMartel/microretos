<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InvocaControladoresReales;
use App\Http\Controllers\MicroproyectoController;
use App\Http\Requests\UpdateMicroproyectoRequest;
use App\Models\CentroEducativo;
use App\Models\Microproyecto;
use App\Models\Microreto;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Genera microproyectos (wizard StartUp Day) para cada microreto simulado, reutilizando
 * el wizard real de MicroproyectoController: store() para el alta, y las llamadas IA
 * encadenadas sugerirFundamentacion()/sugerirMetodologia()/sugerirObjetivos()/sugerirKpis()
 * para el contenido narrativo, persistidas con update() (que además deriva `ra_ce` desde
 * `evaluacion_oficial` — ver RaCeCatalogoService::serializarATexto).
 *
 * El RA/CE del proyecto NO se vuelve a pedir a la IA (ahorra una llamada por proyecto):
 * se copia tal cual del microreto de origen, que ya trae una selección curada y
 * verificada contra el catálogo real (ver DemoGenerarRetos) — un proyecto nacido de un
 * reto trabaja, por definición, el mismo currículo que ese reto.
 *
 * Propietario único: user_id=48 ("DuaLab", admin de su centro) para todos los proyectos,
 * igual que en el resto de comandos demo — ver decisiones del informe.
 *
 * Idempotente: un microreto que ya tenga proyectos simulados se salta salvo --force
 * (que AÑADE más proyectos, nunca borra los existentes).
 *
 * Dry-run por defecto: sin --commit no se llama a la IA ni se escribe nada.
 */
class DemoGenerarProyectos extends Command
{
    use InvocaControladoresReales;

    protected $signature = 'demo:generar-proyectos
                            {--min=3 : Mínimo de proyectos a generar por reto.}
                            {--max=4 : Máximo de proyectos a generar por reto.}
                            {--limit=0 : Tope de RETOS a procesar en esta ejecución (0 = todos los pendientes).}
                            {--force : Vuelve a procesar retos que ya tienen proyectos simulados (añade más, no borra los existentes).}
                            {--commit : Llama a la IA real y persiste en BD. Sin esta opción es un dry-run sin coste.}';

    protected $description = 'Genera microproyectos (wizard StartUp Day) para los microretos simulados, con estados variados (nunca completado).';

    private const USUARIO_ID = 48; // DuaLab — único propietario de todo lo generado en este comando
    private const CENTRO_ID  = 10; // DuaLab

    // Reparto de estado por posición global (índice % 10): nunca 'completado' aquí —
    // ese cierre lo hace demo:generar-encuentros-equipos vía el flujo real de equipo.
    private const POS_PROPUESTA_DESDE = 2; // 0,1 → en_edicion (20%)
    private const POS_VALIDADO_DESDE  = 5; // 2,3,4 → propuesta (30%); 5-9 → validado (50%)

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $min    = max(1, (int) $this->option('min'));
        $max    = max($min, (int) $this->option('max'));
        $limite = (int) $this->option('limit');
        $forzar = (bool) $this->option('force');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se llama a la IA ni se escribe nada. Relanza con --commit para generar de verdad.');
        }

        $usuario = User::find(self::USUARIO_ID);
        if (!$usuario) {
            $this->error('No existe el usuario id=' . self::USUARIO_ID . ' (DuaLab). Abortando.');
            return self::FAILURE;
        }
        $centro = CentroEducativo::find(self::CENTRO_ID);
        if (!$centro) {
            $this->error('No existe el centro id=' . self::CENTRO_ID . ' (DuaLab). Abortando.');
            return self::FAILURE;
        }
        $this->actuarComo($usuario);

        // Scoped a empresa.centro_id=10: 'es_simulado' es un flag genérico que ya usan
        // otros microretos de prueba ajenos a este flujo (de otros centros, o sin empresa
        // asociada) — sin este filtro se les generarían proyectos que no corresponden a
        // este comando.
        $retosQuery = Microreto::where('es_simulado', true)
            ->whereHas('empresa', fn ($q) => $q->where('centro_id', self::CENTRO_ID))
            ->with('empresa.familias')->orderBy('id');
        if (!$forzar) {
            // Microreto no tiene relación microproyectos() definida (fuera de mi alcance
            // añadirla al modelo) — se filtra por subquery directa sobre microproyectos.
            $retosQuery->whereNotIn('id', function ($q) {
                $q->select('microreto_id')->from('microproyectos')->whereNotNull('microreto_id');
            });
        }
        if ($limite > 0) {
            $retosQuery->limit($limite);
        }
        $retos = $retosQuery->get();

        if ($retos->isEmpty()) {
            $this->info('No hay microretos simulados pendientes de generar proyectos (usa --force para reprocesar todos).');
            return self::SUCCESS;
        }

        $indiceGlobal      = Microproyecto::where('es_demo', true)->count();
        $totalGenerados    = 0;

        foreach ($retos as $reto) {
            $empresa = $reto->empresa;
            $familia = $empresa?->familias->first();
            if (!$empresa || !$familia) {
                $this->warn("Reto #{$reto->id} ({$reto->titulo}) sin empresa/familia asociada — omitido.");
                continue;
            }

            $cantidad = random_int($min, $max);
            $this->line("Reto #{$reto->id} {$reto->titulo} | empresa {$empresa->nombre_comercial} | {$cantidad} proyectos a generar");

            if (!$commit) {
                $indiceGlobal += $cantidad;
                continue;
            }

            $modulosUnicos = collect($reto->evaluacion_oficial ?? [])->pluck('modulo')->filter()->unique()->values();
            $modulosSeleccionados = Modulo::where('idcicloformativo', $reto->ciclo_id)
                ->whereIn('nombre', $modulosUnicos)
                ->get(['id', 'nombre'])
                ->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre])
                ->values()->all();

            $retoOrigen = trim(($reto->quien_es ?? '') . ' ' . ($reto->pregunta_reto ?? ''));

            for ($n = 1; $n <= $cantidad; $n++) {
                $estadoObjetivo = $this->elegirEstado($indiceGlobal);
                $indiceGlobal++;
                $proyecto = null;

                try {
                    $titulo = $n === 1 ? $reto->titulo : "{$reto->titulo} ({$n})";

                    $reqStore = $this->peticion(Request::class, [
                        'titulo'       => $titulo,
                        'microreto_id' => $reto->id,
                        'empresa_id'   => $empresa->id,
                        'centro_id'    => self::CENTRO_ID,
                        'familia_id'   => $familia->id,
                        'ciclo_id'     => $reto->ciclo_id,
                        'curso'        => (string) $reto->curso,
                    ], $usuario);
                    $respuestaStore = app(MicroproyectoController::class)->store($reqStore);
                    $proyectoDatos  = json_decode($respuestaStore->getContent(), true);
                    $proyecto       = Microproyecto::findOrFail($proyectoDatos['id']);
                    // store() no acepta 'es_demo' en su whitelist de validate() (no forma
                    // parte del wizard real) — se marca aparte, es solo trazabilidad interna
                    // para el scoping de demo:generar-encuentros-equipos/demo:borrar-ficticios.
                    $proyecto->update(['es_demo' => true]);

                    $contextoComun = [
                        'titulo'        => $titulo,
                        'pregunta_reto' => $reto->pregunta_reto,
                        'descripcion'   => trim(($reto->quien_es ?? '') . ' ' . ($reto->dia_a_dia ?? '')),
                        'reto_origen'   => $retoOrigen,
                    ];

                    // 1/4 llamadas IA: fundamentación (justificación pedagógica + innovación)
                    $fundamentacion = $this->llamarSugerencia('sugerirFundamentacion', array_merge($contextoComun, [
                        'contexto' => $contextoComun['descripcion'],
                    ]));

                    // 2/4 llamadas IA: metodología docente + resumen ejecutivo
                    $metodologiaResumen = $this->llamarSugerencia('sugerirMetodologia', array_merge($contextoComun, [
                        'ciclo'   => $reto->ciclo,
                        'curso'   => (string) $reto->curso,
                        'empresa' => $empresa->nombre_comercial,
                        'modulos' => $modulosUnicos->implode(', '),
                        'fases'   => 'Inicio del equipo, Análisis del reto, Diseño de solución y desarrollo, Entrega de la solución, Presentación',
                    ]));

                    // 3/4 llamadas IA: objetivos de aprendizaje
                    $objetivos = $this->llamarSugerencia('sugerirObjetivos', $contextoComun);

                    // 4/4 llamadas IA: KPIs para que la empresa evalúe el resultado
                    $kpis = $this->llamarSugerencia('sugerirKpis', array_merge($contextoComun, [
                        'objetivos' => $objetivos['objetivos'] ?? [],
                    ]));

                    $datosActualizacion = [
                        'datos_empresa' => [
                            'nombre'           => $empresa->nombre_comercial,
                            'cif'              => $empresa->cif,
                            'sector'           => $empresa->sector,
                            'actividad'        => $empresa->actividad,
                            'persona_contacto' => $empresa->persona_contacto,
                            'email'            => $empresa->email_general,
                            'telefono'         => $empresa->telefono,
                            'web'              => $empresa->web,
                            'descripcion'      => $empresa->dia_a_normal,
                        ],
                        'datos_centro' => [
                            'nombre'         => $centro->nombre,
                            // Municipio del CENTRO EDUCATIVO (columna real desde la migración
                            // add_municipio_to_centro_educativo_table), no el de la empresa —
                            // antes se usaba $empresa->municipio por error, mostrando
                            // "DuaLab · Madrid"/"DuaLab · Barcelona" como si el centro tuviera
                            // sede distinta en cada proyecto.
                            'municipio'      => $centro->municipio,
                            'docente_nombre' => $usuario->name,
                            'docente_email'  => $usuario->email,
                        ],
                        'equipo'                 => ['docente_responsable' => $usuario->name],
                        'modulos_seleccionados'  => $modulosSeleccionados,
                        'evaluacion_oficial'     => $reto->evaluacion_oficial ?? [], // heredado del reto, no se vuelve a pedir a la IA
                        'fundamentacion' => [
                            'contexto'      => $contextoComun['descripcion'],
                            'justificacion' => $fundamentacion['justificacion'] ?? '',
                            'innovacion'    => $fundamentacion['innovacion'] ?? '',
                        ],
                        'diseno_reto' => [
                            'descripcion'   => $contextoComun['descripcion'],
                            'pregunta_reto' => $reto->pregunta_reto,
                            'restricciones' => collect($reto->limitaciones ?? [])->implode('; '),
                            'entregables'   => collect($reto->prototipos ?? [])->implode('; '),
                        ],
                        'diseno_microproyecto' => [
                            'fases' => [
                                ['numero' => 0, 'nombre' => 'Inicio del equipo', 'duracion_clases' => 1],
                                ['numero' => 1, 'nombre' => 'Análisis del reto', 'duracion_clases' => 2],
                                ['numero' => 2, 'nombre' => 'Diseño de solución y desarrollo', 'duracion_clases' => 3],
                                ['numero' => 3, 'nombre' => 'Entrega de la solución', 'duracion_clases' => 2],
                                ['numero' => 4, 'nombre' => 'Presentación', 'duracion_clases' => 1],
                            ],
                            'clases'      => $this->generarClasesConFases(),
                            'metodologia' => $metodologiaResumen['metodologia'] ?? '',
                            'cronograma'  => '',
                        ],
                        'resumen'   => ['texto' => $metodologiaResumen['resumen'] ?? ''],
                        'objetivos' => ['lista' => $objetivos['objetivos'] ?? []],
                        'kpis'      => ['lista' => $kpis['kpis'] ?? []],
                    ];

                    if ($estadoObjetivo !== 'en_edicion') {
                        // 'validado' también pasa por 'propuesta' primero: es requisito de
                        // StoreEncuentroRequest/validarEmpresa/validarDocente en el flujo real.
                        $datosActualizacion['estado'] = 'propuesta';
                    }

                    $reqUpdate = $this->peticion(UpdateMicroproyectoRequest::class, $datosActualizacion, $usuario);
                    app(MicroproyectoController::class)->update($reqUpdate, $proyecto->uuid);

                    if ($estadoObjetivo === 'validado') {
                        $this->avanzarAValidado($proyecto->fresh(), $usuario);
                    }

                    $totalGenerados++;
                    $this->info("  ✓ Proyecto #{$proyecto->id} \"{$titulo}\" → estado final: {$estadoObjetivo}");
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->error("  ✗ Validación falló para el reto #{$reto->id} (proyecto {$n}): " . json_encode($e->errors()));
                    $this->borrarProyectoHuerfano($proyecto);
                } catch (\Throwable $e) {
                    $this->error("  ✗ Error generando proyecto {$n}/{$cantidad} para el reto #{$reto->id}: " . $e->getMessage());
                    $this->borrarProyectoHuerfano($proyecto);
                }
            }
        }

        $this->newLine();
        if ($commit) {
            $this->info("Total de proyectos generados en esta ejecución: {$totalGenerados}.");
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para generar de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * store() crea el Microproyecto ANTES de las 4 llamadas IA que rellenan su contenido —
     * si cualquiera de ellas falla (validación, timeout, lo que sea), sin esto quedaría un
     * proyecto a medias en BD (titulo puesto, fundamentacion/objetivos/kpis vacíos) que
     * además el filtro de idempotencia de handle() daría por "ya procesado", saltándoselo
     * para siempre en relanzamientos futuros. Se borra de verdad (no soft-delete): es un
     * dato a medio generar de este mismo comando, no algo que valga la pena recuperar.
     */
    private function borrarProyectoHuerfano(?Microproyecto $proyecto): void
    {
        if ($proyecto) {
            $proyecto->forceDelete();
        }
    }

    /**
     * Genera el calendario de "clases" (sesiones) del proyecto con sus fases asignadas.
     * Cada elemento necesita `fases: [índices]` apuntando al array `diseno_microproyecto.fases`
     * (0=Inicio del equipo … 4=Presentación) — el frontend (ProyectoFichaModal.vue,
     * StartupDayDetalle.vue) hace `fases?.[n]?.nombre` para cada índice; sin la clave
     * `fases` (antes se guardaba solo `{numero: n}`) cae siempre en "Sin fase asignada".
     *
     * Nº de sesiones y reparto de fases variados por proyecto (7-10 sesiones, pesos
     * aleatorios por fase) para que el calendario no salga idéntico en todos los proyectos.
     */
    private function generarClasesConFases(): array
    {
        $total = random_int(7, 10);

        // Al menos 1 clase por cada una de las 5 fases; el resto se reparte al azar.
        $pesos = [1, 1, 1, 1, 1];
        for ($i = 0; $i < $total - 5; $i++) {
            $pesos[random_int(0, 4)]++;
        }

        $clases = [];
        foreach ($pesos as $fase => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                $clases[] = ['fases' => [$fase]];
            }
        }

        return $clases;
    }

    /**
     * validarEmpresa() es un endpoint público (acceso por token, sin usuario) — se reutiliza
     * tal cual pasando el token_empresa del proyecto. validarDocente() sí exige
     * $this->authorize('update', $proyecto) vía MicroproyectoPolicy — ya cubierto por
     * Auth::setUser() en actuarComo() al principio de handle().
     */
    private function avanzarAValidado(Microproyecto $proyecto, User $usuario): void
    {
        // Mismas claves y valores que envía el formulario real (StartupDayLanding.vue):
        // 'diagnostico_correcto'=>true (booleano inventado) se mostraba tal cual en la
        // ficha del proyecto porque el frontend solo sabe formatear estas 4 claves con
        // valor 'Sí'/'No'/'Parcialmente' — cualquier otra clave se imprime cruda.
        $reqEmpresa = $this->peticion(Request::class, [
            'decision'   => 'validar',
            'respuestas' => [
                'reto_comprensible'   => 'Sí',
                'objetivos_alineados' => 'Sí',
                'equipo_adecuado'     => 'Sí',
                'viabilidad'          => 'Sí',
            ],
            'comentarios' => null,
        ]);
        app(MicroproyectoController::class)->validarEmpresa($reqEmpresa, $proyecto->token_empresa);

        $reqDocente = $this->peticion(Request::class, ['decision' => 'validar'], $usuario);
        app(MicroproyectoController::class)->validarDocente($reqDocente, $proyecto->uuid);
    }

    /**
     * 20% en_edicion / 30% propuesta / 50% validado — reparto determinista por posición
     * global (para que el dry-run sea estable entre relanzamientos), nunca 'completado'
     * (eso lo hace demo:generar-encuentros-equipos a través del flujo real de equipo).
     */
    private function elegirEstado(int $indiceGlobal): string
    {
        $posicion = $indiceGlobal % 10;
        if ($posicion < self::POS_PROPUESTA_DESDE) return 'en_edicion';
        if ($posicion < self::POS_VALIDADO_DESDE)  return 'propuesta';
        return 'validado';
    }

    /**
     * Invoca uno de los métodos "sugerirX" de MicroproyectoController (todos con la misma
     * forma: Request plano, validate() inline, sin FormRequest ni $request->user()).
     *
     * OJO — sin reintento a propósito: estos métodos cachean su resultado con
     * Cache::remember(..., 6h) por md5(contexto), INCLUSO cuando la IA falla (el closure
     * devuelve null y Laravel lo cachea igual) — es un bug ya existente en el controller
     * (reportado, no corregido aquí, fuera del scope de este encargo). Reintentar de
     * inmediato con el mismo contexto solo devolvería el mismo null cacheado durante 6h,
     * así que aquí nos limitamos a detectar el fallo y avisar, dejando el campo vacío
     * (mismo comportamiento que sin este aviso, pero visible en vez de silencioso).
     */
    private function llamarSugerencia(string $metodo, array $payload): array
    {
        $request   = $this->peticion(Request::class, $payload);
        $respuesta = app(MicroproyectoController::class)->{$metodo}($request);
        $data      = json_decode($respuesta->getContent(), true);

        if (!is_array($data)) {
            return [];
        }

        if (isset($data['error'])) {
            $this->warn("  … {$metodo}() falló: {$data['error']} (el campo queda vacío; reintentar no ayuda, ver comentario de llamarSugerencia)");
            return [];
        }

        return $data;
    }
}
