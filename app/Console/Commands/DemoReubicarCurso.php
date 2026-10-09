<?php

namespace App\Console\Commands;

use App\Models\Microproyecto;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reubica en el tiempo los datos de DEMO para que el panel docente cuente una historia
 * coherente por cursos académicos (septiembre → agosto):
 *
 *   · Curso 2025/26 «ya trabajado»: casi todo (todos los completados, la mayoría de validados),
 *     con actividad repartida de septiembre a junio como un curso real.
 *   · Curso 2026/27 «en arranque»: propuestas y borradores recientes, algún validado y unos
 *     pocos encuentros futuros (octubre–diciembre) para «Próximos encuentros».
 *
 * ÁMBITO — solo datos de demo, con el mismo criterio que demo:borrar-ficticios:
 *   · Empresas del centro DuaLab (id=10) con es_simulada=1, y las del catálogo DuaLab (es_catalogo=1).
 *   · Retos es_simulado=1 de esas empresas del centro DuaLab.
 *   · Proyectos es_demo=1, sus encuentros, equipos (y fases, tareas, reflexiones, prototipos,
 *     miembros), recursos y colaboradores de encuentro; y el propio centro DuaLab.
 * NUNCA toca datos de centros reales (p. ej. IES Ana Luisa de Benítez, id=1), usuarios,
 * caducidad de enlaces de retos (microreto_tokens) ni registros en la papelera.
 *
 * ORDEN (de arriba abajo, para que nada quede antes de aquello de lo que depende):
 *   proyecto (curso según su estado) → encuentros → equipos y su contenido → fechas del proyecto
 *   → retos (antes que sus proyectos) → empresas (antes que sus retos/proyectos/copias) → centro.
 *
 * Determinista: las fechas se calculan a partir del id de cada registro y de --semilla, nunca de
 * la fecha que tenga; ejecutarlo dos veces da el mismo resultado.
 *
 * Dry-run por defecto: sin --commit solo muestra el resumen y las comprobaciones, sin escribir.
 * Con --commit escribe todo en UNA transacción (si algo falla, no cambia nada). Las fechas
 * created_at/updated_at se escriben explícitamente con el query builder (sin eventos de modelo
 * ni timestamps automáticos). Tras aplicarlo conviene `php artisan cache:clear` (hay vistas
 * públicas con caché de equipos).
 */
class DemoReubicarCurso extends Command
{
    protected $signature = 'demo:reubicar-curso
                            {--commit : Escribe los cambios. Sin esta opción es un dry-run que solo muestra el resumen.}
                            {--semilla=2025 : Semilla del reparto (mismo valor = mismas fechas).}';

    protected $description = 'Reubica las fechas de los datos de demo: curso 2025/26 completo y arranque de 2026/27.';

    private const CENTRO_DEMO = 10; // DuaLab — ver ámbito en la cabecera
    private const ENCUENTROS_FUTUROS = 5;

    private CarbonImmutable $hoy;
    private string $semilla;

    /** Cambios calculados: tabla => [id => [columna => valor]] */
    private array $cambios = [];

    /** Curso asignado a cada proyecto (id => '2025/26' | '2026/27'), para las comprobaciones */
    private array $cursoAsignado = [];

    public function handle(): int
    {
        $this->hoy     = CarbonImmutable::now()->startOfDay();
        $this->semilla = (string) $this->option('semilla');
        $commit        = (bool) $this->option('commit');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se escribe nada. Relanza con --commit para aplicar los cambios.');
        }

        // ── Ámbito de demo ────────────────────────────────────────────────────────
        $empresas = DB::table('empresas')->whereNull('deleted_at')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('centro_id', self::CENTRO_DEMO)->where('es_simulada', true))
                                  ->orWhere('es_catalogo', true))
            ->get(['id', 'es_catalogo', 'copiada_de_id', 'fecha_cita', 'created_at'])->keyBy('id');

        $retos = DB::table('microretos')->whereNull('deleted_at')->where('es_simulado', true)
            ->whereIn('empresa_id', $empresas->where('es_catalogo', 0)->keys())
            ->get(['id', 'empresa_id'])->keyBy('id');

        $proyectos = DB::table('microproyectos')->whereNull('deleted_at')->where('es_demo', true)
            ->get(['id', 'estado', 'microreto_id', 'empresa_id'])->keyBy('id');

        $encuentros = DB::table('encuentros')->whereNull('deleted_at')
            ->whereIn('microproyecto_id', $proyectos->keys())
            ->orderBy('id')
            ->get(['id', 'microproyecto_id', 'num_alumnos'])->keyBy('id');

        $equipos = DB::table('equipos')->whereIn('encuentro_id', $encuentros->keys())
            ->get(['id', 'encuentro_id', 'diagnostico_generado_en', 'created_at'])->keyBy('id');

        $this->info(sprintf('Ámbito de demo: %d proyectos, %d encuentros, %d equipos, %d retos, %d empresas (%d del catálogo).',
            $proyectos->count(), $encuentros->count(), $equipos->count(), $retos->count(),
            $empresas->count(), $empresas->where('es_catalogo', 1)->count()));

        // ── 1. Curso y fecha ancla de cada proyecto según su estado ───────────────
        $anclaProyecto = [];   // id => CarbonImmutable (inicio de su actividad)
        $cursoProyecto = [];   // id => '2025/26' | '2026/27'
        $conEncuentros = $encuentros->pluck('microproyecto_id')->flip();
        foreach ($proyectos as $p) {
            if ($p->estado === 'completado') {
                // Completados: su actividad se reparte por todo el curso 2025/26 (oct–may) con el ritmo de un curso real
                $cursoProyecto[$p->id] = '2025/26';
                $anclaProyecto[$p->id] = $this->diaPonderado2526($this->r("ancla-p{$p->id}"), 1, 8);
                continue;
            }
            if ($p->estado === 'validado' && isset($conEncuentros[$p->id])) {
                // Validados con encuentro (equipos trabajando): actividad de este curso, septiembre–octubre
                $cursoProyecto[$p->id] = '2026/27';
                $anclaProyecto[$p->id] = $this->diaLaborable(CarbonImmutable::create(2026, 9, 14), $this->hoy->subDays(3), $this->r("ancla-p{$p->id}"));
                continue;
            }
            [$curso, $desde, $hasta] = $this->ventanaPorEstado($p->estado, $this->r("curso-p{$p->id}"));
            $cursoProyecto[$p->id] = $curso;
            $anclaProyecto[$p->id] = $this->diaLaborable($desde, $hasta, $this->r("ancla-p{$p->id}"));
        }

        // ── 2. Encuentros: secuencia por proyecto a partir de su ancla ────────────
        $encuentrosPorProyecto = $encuentros->groupBy('microproyecto_id');
        $conFasesCompletadas = DB::table('equipo_fases')->where('completada', true)
            ->whereIn('equipo_id', $equipos->keys())->distinct()->pluck('equipo_id')->flip();
        $equiposPorEncuentro = $equipos->groupBy('encuentro_id');

        // Encuentros futuros: de proyectos de 2026/27, sin fases completadas en sus equipos
        $candidatosFuturos = $encuentros->filter(function ($e) use ($cursoProyecto, $equiposPorEncuentro, $conFasesCompletadas) {
            if (($cursoProyecto[$e->microproyecto_id] ?? null) !== '2026/27') return false;
            return ($equiposPorEncuentro[$e->id] ?? collect())->every(fn ($eq) => !isset($conFasesCompletadas[$eq->id]));
        })->sortBy(fn ($e) => $this->r("fut-e{$e->id}"))->take(self::ENCUENTROS_FUTUROS)->keys()->flip();

        // Duración de cada encuentro con la misma regla que la app (Microproyecto::fechaFinSugerida,
        // según las clases estimadas del proyecto): los encuentros abarcan semanas de trabajo.
        $modelos = Microproyecto::whereIn('id', $encuentros->pluck('microproyecto_id')->unique())->get()->keyBy('id');
        $duracionProyecto = fn (int $proyectoId) => ($f = $modelos[$proyectoId]?->fechaFinSugerida(Carbon::create(2026, 1, 5)))
            ? (int) Carbon::create(2026, 1, 5)->diffInDays($f, true) : 0;

        $nuevaFecha = []; // encuentro id => [inicio, fin] (CarbonImmutable)
        foreach ($encuentrosPorProyecto as $proyectoId => $lista) {
            $curso  = $cursoProyecto[$proyectoId];
            $limite = $curso === '2025/26' ? CarbonImmutable::create(2026, 6, 26) : $this->hoy->subDay();
            $cursor = $anclaProyecto[$proyectoId];
            $duracion = $duracionProyecto((int) $proyectoId);
            foreach ($lista->values() as $i => $e) {
                if (isset($candidatosFuturos[$e->id])) {
                    $inicio = $this->diaLaborable($this->hoy->addDays(5), CarbonImmutable::create(2026, 12, 18), $this->r("futuro-e{$e->id}"));
                } else {
                    if ($i > 0) $cursor = $this->siguienteLaborable($cursor->addDays(7 + (int) floor($this->r("gap-e{$e->id}") * 12)));
                    $inicio = $cursor->greaterThan($limite) ? $this->diaLaborable($anclaProyecto[$proyectoId], $limite, $this->r("comp-e{$e->id}")) : $cursor;
                    // En 2025/26 el encuentro debe terminar antes de fin de curso
                    if ($curso === '2025/26' && $inicio->addDays($duracion)->greaterThan($limite)) {
                        $inicio = $this->siguienteLaborable($this->maxFecha($limite->subDays($duracion), CarbonImmutable::create(2025, 9, 15)));
                    }
                }
                $nuevaFecha[$e->id] = [$inicio, $inicio->addDays($duracion)];

                $this->cambiar('encuentros', $e->id, [
                    'fecha'      => $inicio->toDateString(),
                    'fecha_fin'  => $duracion ? $inicio->addDays($duracion)->toDateString() : null,
                    'created_at' => $this->conHora($this->minFecha($inicio->subDays(5 + (int) floor($this->r("crea-e{$e->id}") * 15)), $this->hoy->subDay()), "ce{$e->id}"),
                    'updated_at' => $this->conHora($this->minFecha($inicio->addDays($duracion), $this->hoy->subDay()), "ue{$e->id}"),
                ] + ($this->sinAlumnos($e) ? ['num_alumnos' => $this->alumnosEstimados($e->id)] : []));
            }
        }

        // ── 3. Equipos y su contenido: mismo orden e intervalos, dentro de la ventana del encuentro ──
        $hijos = ['equipo_miembros', 'equipo_tareas', 'equipo_reflexiones', 'equipo_prototipos'];
        $filasHijo = [];
        foreach ($hijos as $t) {
            $filasHijo[$t] = DB::table($t)->whereIn('equipo_id', $equipos->keys())->get(['id', 'equipo_id', 'created_at', 'updated_at'])->groupBy('equipo_id');
        }
        $fasesPorEquipo = DB::table('equipo_fases')->whereIn('equipo_id', $equipos->keys())
            ->get(['id', 'equipo_id', 'fecha_completada', 'fecha_validacion_docente', 'created_at', 'updated_at'])->groupBy('equipo_id');

        foreach ($equipos as $eq) {
            [$inicio, $fin] = $nuevaFecha[$eq->encuentro_id];
            $origen   = CarbonImmutable::parse($eq->created_at);
            $ahora    = CarbonImmutable::now()->subHour();
            // Encuentro futuro: el equipo ya existe (se creó al preparar el encuentro), sin actividad todavía
            $base     = $inicio->greaterThan($this->hoy)
                ? CarbonImmutable::parse($this->cambios['encuentros'][$eq->encuentro_id]['created_at'])->addHour()
                : $inicio->setTime(8, 30)->addMinutes((int) floor($this->r("eq{$eq->id}") * 60));
            $finVentana = $this->minFecha($fin->setTime(18, 0), $ahora);
            $ventana  = max(1, (int) $finVentana->diffInSeconds($base, true));
            $mover    = function (?string $f) use ($origen, $base, $ventana) {
                if (!$f) return null;
                $delta = (int) $origen->diffInSeconds(CarbonImmutable::parse($f), false); // segundos desde el origen (con signo)
                return $base->addSeconds(max(0, min($delta, $ventana)))->toDateTimeString();
            };
            $ultima = $base;

            foreach ($fasesPorEquipo[$eq->id] ?? [] as $f) {
                $comp = $mover($f->fecha_completada);
                $val  = $mover($f->fecha_validacion_docente);
                $upd  = $mover($f->updated_at) ?? $base->toDateTimeString();
                foreach ([$comp, $val, $upd] as $x) if ($x && $x > $ultima->toDateTimeString()) $ultima = CarbonImmutable::parse($x);
                $this->cambiar('equipo_fases', $f->id, [
                    'fecha_completada'         => $comp,
                    'fecha_validacion_docente' => $val,
                    'created_at'               => $base->toDateTimeString(),
                    'updated_at'               => $upd,
                ]);
            }
            foreach ($hijos as $t) {
                foreach ($filasHijo[$t][$eq->id] ?? [] as $h) {
                    $c = $mover($h->created_at) ?? $base->toDateTimeString();
                    $u = $mover($h->updated_at) ?? $c;
                    if ($u > $ultima->toDateTimeString()) $ultima = CarbonImmutable::parse($u);
                    $this->cambiar($t, $h->id, ['created_at' => $c, 'updated_at' => max($c, $u)]);
                }
            }
            $diag = $eq->diagnostico_generado_en ? $ultima->addMinutes(5)->toDateTimeString() : null;
            $this->cambiar('equipos', $eq->id, [
                'created_at'              => $base->toDateTimeString(),
                'updated_at'              => $diag ?? $ultima->toDateTimeString(),
                'diagnostico_generado_en' => $diag,
            ]);
        }

        // ── 4. Fechas del proyecto ────────────────────────────────────────────────
        $creadoProyecto = [];
        foreach ($proyectos as $p) {
            $lista       = $encuentrosPorProyecto[$p->id] ?? collect();
            $primerEnc   = $lista->map(fn ($e) => CarbonImmutable::parse($this->cambios['encuentros'][$e->id]['created_at']))->min();
            $ancla       = $this->minFecha($anclaProyecto[$p->id], $this->hoy->subDay());
            // nunca antes del inicio de su curso (agosto pertenece todavía al curso anterior)
            $inicioCurso = $cursoProyecto[$p->id] === '2026/27' ? CarbonImmutable::create(2026, 9, 1) : CarbonImmutable::create(2025, 9, 8);
            $creado      = $this->maxFecha($ancla->subDays(7 + (int) floor($this->r("crea-p{$p->id}") * 23)), $inicioCurso);
            // por día (no por hora): el proyecto, al menos un día antes que su primer encuentro
            if ($primerEnc && !$creado->lessThan($primerEnc->startOfDay())) $creado = $primerEnc->startOfDay()->subDays(2);
            $ultimoEnc   = $lista->map(fn ($e) => $nuevaFecha[$e->id][1])->filter(fn ($d) => $d->lessThanOrEqualTo($this->hoy))->max();
            $actualizado = match (true) {
                $p->estado === 'completado'                       => ($ultimoEnc ?? $ancla)->addDays(2 + (int) floor($this->r("upd-p{$p->id}") * 8)),
                $cursoProyecto[$p->id] === '2026/27' && $this->r("frescura-p{$p->id}") < 0.85
                                                                  => $this->diaLaborable($this->maxFecha($creado, $this->hoy->subDays(12)), $this->hoy->subDay(), $this->r("upd-p{$p->id}")),
                default                                           => ($ultimoEnc ?? $ancla)->addDays((int) floor($this->r("upd-p{$p->id}") * 20)),
            };
            $techo       = $cursoProyecto[$p->id] === '2025/26' ? CarbonImmutable::create(2026, 6, 30) : $this->hoy->subDay();
            $actualizado = $this->minFecha($this->maxFecha($actualizado, $creado), $techo);
            $creadoProyecto[$p->id] = $creado;
            $this->cambiar('microproyectos', $p->id, [
                'created_at' => $this->conHora($creado, "cp{$p->id}"),
                'updated_at' => $this->conHora($actualizado, "up{$p->id}"),
            ]);
        }
        foreach (DB::table('microproyecto_recursos')->whereIn('microproyecto_id', $proyectos->keys())->get(['id', 'microproyecto_id']) as $rec) {
            $c = $this->conHora($creadoProyecto[$rec->microproyecto_id]->addDays(1), "rec{$rec->id}");
            $this->cambiar('microproyecto_recursos', $rec->id, ['created_at' => $c, 'updated_at' => $c]);
        }
        foreach (DB::table('encuentro_colaboradores')->whereIn('encuentro_id', $encuentros->keys())->get(['id', 'encuentro_id']) as $col) {
            $c = $this->cambios['encuentros'][$col->encuentro_id]['created_at'];
            $this->cambiar('encuentro_colaboradores', $col->id, ['created_at' => $c, 'updated_at' => $c]);
        }

        // ── 5. Retos: antes que su primer proyecto ────────────────────────────────
        $proyectosPorReto = $proyectos->groupBy('microreto_id');
        $creadoReto = [];
        foreach ($retos as $r) {
            $primer = ($proyectosPorReto[$r->id] ?? collect())->map(fn ($p) => $creadoProyecto[$p->id])->min();
            $creado = $primer
                ? $this->minFecha($this->maxFecha($primer->subDays(3 + (int) floor($this->r("crea-r{$r->id}") * 22)), CarbonImmutable::create(2025, 9, 1)), $primer->subDay())
                : ($this->r("curso-r{$r->id}") < 0.9
                    ? $this->diaPonderado2526($this->r("crea-r{$r->id}"), 0, 8)   // sep 2025 – may 2026
                    : $this->diaLaborable(CarbonImmutable::create(2026, 9, 1), $this->hoy->subDay(), $this->r("crea-r{$r->id}")));
            $creadoReto[$r->id] = $creado;
            $this->cambiar('microretos', $r->id, [
                'created_at' => $this->conHora($creado, "cr{$r->id}"),
                'updated_at' => $this->conHora($this->minFecha($creado->addDays((int) floor($this->r("upd-r{$r->id}") * 10)), $primer ?? $this->hoy->subDay()), "ur{$r->id}"),
            ]);
        }

        // ── 6. Empresas: antes que sus retos, proyectos y copias ──────────────────
        $minDependiente = [];
        foreach ($retos as $r) $minDependiente[$r->empresa_id][] = $creadoReto[$r->id];
        foreach ($proyectos as $p) if ($p->empresa_id) $minDependiente[$p->empresa_id][] = $creadoProyecto[$p->id];
        $creadoEmpresa = [];
        // primero el catálogo (las copias deben ser posteriores a su plantilla)
        foreach ($empresas->sortByDesc('es_catalogo') as $e) {
            $deps = collect($minDependiente[$e->id] ?? [])->min();
            if ($e->es_catalogo) {
                $creado = $this->diaLaborable(CarbonImmutable::create(2025, 6, 2), CarbonImmutable::create(2025, 7, 31), $this->r("crea-emp{$e->id}"));
            } elseif ($deps) {
                $creado = $this->maxFecha($deps->subDays(5 + (int) floor($this->r("crea-emp{$e->id}") * 35)), CarbonImmutable::create(2025, 8, 1));
            } else {
                $creado = $this->r("curso-emp{$e->id}") < 0.9
                    ? $this->diaPonderado2526($this->r("crea-emp{$e->id}"), 0, 7)  // sep 2025 – abr 2026
                    : $this->diaLaborable(CarbonImmutable::create(2026, 9, 1), $this->hoy->subDay(), $this->r("crea-emp{$e->id}"));
            }
            if ($e->copiada_de_id && isset($creadoEmpresa[$e->copiada_de_id]) && $creado->lessThanOrEqualTo($creadoEmpresa[$e->copiada_de_id])) {
                $creado = $creadoEmpresa[$e->copiada_de_id]->addDays(1);
            }
            if ($deps && !$creado->lessThan($deps)) $creado = $deps->subDay();
            $creadoEmpresa[$e->id] = $creado;
            $techo = $deps ?? $this->hoy->subDay();
            $this->cambiar('empresas', $e->id, [
                'created_at' => $this->conHora($creado, "cemp{$e->id}"),
                'updated_at' => $this->conHora($this->minFecha($creado->addDays((int) floor($this->r("upd-emp{$e->id}") * 30)), $techo), "uemp{$e->id}"),
            ] + ($e->fecha_cita ? ['fecha_cita' => $this->minFecha($creado->addDays(3 + (int) floor($this->r("cita-emp{$e->id}") * 17)), $techo)->toDateString()] : []));
        }
        foreach (DB::table('empresa_familia')->whereIn('empresa_id', $empresas->keys())->get(['id', 'empresa_id']) as $ef) {
            $c = $this->cambios['empresas'][$ef->empresa_id]['created_at'];
            $this->cambiar('empresa_familia', $ef->id, ['created_at' => $c, 'updated_at' => $c]);
        }

        // ── 7. Centro DuaLab: antes que el catálogo y el curso 2025/26 ───────────
        $this->cambiar('centro_educativo', self::CENTRO_DEMO, ['created_at' => '2025-05-19 09:00:00', 'updated_at' => '2025-05-19 09:00:00']);

        // ── Resumen y comprobaciones ──────────────────────────────────────────────
        $this->cursoAsignado = $cursoProyecto;
        $this->resumen($proyectos, $cursoProyecto, $nuevaFecha);
        $errores = $this->comprobar($proyectos, $retos, $empresas, $encuentros, $nuevaFecha, $candidatosFuturos);
        if ($errores) {
            $this->error("Hay {$errores} incoherencias: no se aplica nada.");
            return self::FAILURE;
        }
        $this->info('Comprobaciones superadas: ninguna fecha queda antes de aquello de lo que depende.');

        if (!$commit) {
            $this->warn('DRY-RUN terminado. Nada se ha escrito.');
            return self::SUCCESS;
        }

        DB::transaction(function () {
            foreach ($this->cambios as $tabla => $filas) {
                foreach ($filas as $id => $valores) {
                    DB::table($tabla)->where('id', $id)->update($valores);
                }
            }
        });
        $total = array_sum(array_map('count', $this->cambios));
        $this->info("Aplicado: {$total} registros actualizados en " . count($this->cambios) . ' tablas. Recuerda: php artisan cache:clear');
        return self::SUCCESS;
    }

    // ── Reparto ──────────────────────────────────────────────────────────────────

    /** Curso y ventana de fechas según el estado del proyecto. */
    private function ventanaPorEstado(string $estado, float $r): array
    {
        $c25 = fn ($d, $h) => ['2025/26', CarbonImmutable::parse($d), CarbonImmutable::parse($h)];
        $c26 = fn ($d) => ['2026/27', CarbonImmutable::parse($d), $this->hoy->subDay()];
        return match ($estado) {
            'completado' => $c25('2026-01-12', '2026-05-15'),
            'validado'   => $r < 0.85 ? $c25('2026-02-02', '2026-06-05') : $c26('2026-09-14'),
            'propuesta'  => $r < 0.25 ? $c25('2026-04-06', '2026-06-12') : $c26('2026-09-07'),
            'en_edicion' => $r < 0.20 ? $c25('2026-05-04', '2026-06-19') : $c26('2026-09-14'),
            default      => $c25('2025-10-06', '2026-03-27'),
        };
    }

    /** Día laborable del curso 2025/26 con el ritmo de un curso real (meses con peso). */
    private function diaPonderado2526(float $r, int $desdeMes, int $hastaMes): CarbonImmutable
    {
        // sep … jun (índices 0–9) — menos actividad en septiembre, diciembre, abril y junio
        $meses = [[2025, 9, .6], [2025, 10, 1], [2025, 11, 1], [2025, 12, .6], [2026, 1, .8],
                  [2026, 2, 1], [2026, 3, 1], [2026, 4, .7], [2026, 5, 1], [2026, 6, .7]];
        $tramo = array_slice($meses, $desdeMes, $hastaMes - $desdeMes + 1);
        $total = array_sum(array_column($tramo, 2));
        $acum  = 0;
        foreach ($tramo as [$y, $m, $peso]) {
            $acum += $peso / $total;
            if ($r <= $acum || $m === end($tramo)[1]) {
                $inicio = CarbonImmutable::create($y, $m, 1);
                return $this->diaLaborable($inicio, $inicio->endOfMonth()->startOfDay(), fmod($r * 97.13, 1.0));
            }
        }
        return CarbonImmutable::create(2025, 10, 1);
    }

    // ── Utilidades de fechas ─────────────────────────────────────────────────────

    /** Número pseudoaleatorio estable en [0,1) a partir de una clave y la semilla. */
    private function r(string $clave): float
    {
        return (crc32($this->semilla . ':' . $clave) & 0xFFFFFFFF) / 4294967296;
    }

    private function diaLaborable(CarbonImmutable $desde, CarbonImmutable $hasta, float $r): CarbonImmutable
    {
        if ($hasta->lessThan($desde)) $hasta = $desde;
        $dias = (int) $desde->diffInDays($hasta, true);
        return $this->siguienteLaborable($desde->addDays((int) floor($r * ($dias + 1))), $hasta);
    }

    private function siguienteLaborable(CarbonImmutable $d, ?CarbonImmutable $tope = null): CarbonImmutable
    {
        if ($d->isSaturday()) $d = $tope && $d->addDays(2)->greaterThan($tope) ? $d->subDay() : $d->addDays(2);
        if ($d->isSunday())   $d = $tope && $d->addDay()->greaterThan($tope) ? $d->subDays(2) : $d->addDay();
        return $d;
    }

    private function conHora(CarbonImmutable $d, string $clave): string
    {
        return $d->startOfDay()->setTime(8 + (int) floor($this->r("h-$clave") * 9), (int) floor($this->r("m-$clave") * 60))->toDateTimeString();
    }

    private function minFecha(CarbonImmutable $a, CarbonImmutable $b): CarbonImmutable { return $a->lessThan($b) ? $a : $b; }
    private function maxFecha(CarbonImmutable $a, CarbonImmutable $b): CarbonImmutable { return $a->greaterThan($b) ? $a : $b; }

    private function sinAlumnos(object $e): bool { return !$e->num_alumnos; }

    private function alumnosEstimados(int $encuentroId): int
    {
        $miembros = DB::table('equipo_miembros')->join('equipos', 'equipos.id', '=', 'equipo_miembros.equipo_id')
            ->where('equipos.encuentro_id', $encuentroId)->count();
        return $miembros ?: 18 + (int) floor($this->r("alumnos-e{$encuentroId}") * 9);
    }

    private function cambiar(string $tabla, int $id, array $valores): void
    {
        $this->cambios[$tabla][$id] = $valores;
    }

    // ── Resumen y comprobaciones ─────────────────────────────────────────────────

    private function resumen(Collection $proyectos, array $cursoProyecto, array $nuevaFecha): void
    {
        $this->newLine();
        $this->line('<options=bold>Proyectos por curso y estado</>');
        $filas = [];
        foreach ($proyectos->groupBy(fn ($p) => $cursoProyecto[$p->id]) as $curso => $lista) {
            foreach ($lista->groupBy('estado') as $estado => $l) $filas[] = [$curso, $estado, $l->count()];
        }
        usort($filas, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $this->table(['Curso', 'Estado', 'Proyectos'], $filas);

        $this->line('<options=bold>Encuentros por mes</>');
        $porMes = collect($nuevaFecha)->map(fn ($f) => $f[0]->format('Y-m'))->countBy()->sortKeys();
        $this->table(['Mes', 'Encuentros'], $porMes->map(fn ($n, $m) => [$m, $n])->values()->all());
        $futuros = collect($nuevaFecha)->filter(fn ($f) => $f[0]->greaterThan($this->hoy))->count();
        $this->line("Encuentros futuros (próximos encuentros): {$futuros}" . ($futuros ? '' : ' — todos los encuentros de demo tienen fases completadas; para tener próximos encuentros hay que crear encuentros nuevos.'));

        $this->line('<options=bold>Registros que cambian</>');
        $this->table(['Tabla', 'Registros'], collect($this->cambios)->map(fn ($f, $t) => [$t, count($f)])->values()->all());
    }

    private function comprobar(Collection $proyectos, Collection $retos, Collection $empresas, Collection $encuentros, array $nuevaFecha, Collection $futuros): int
    {
        $c = $this->cambios;
        $err = 0;
        $falla = function (string $regla, int $n) use (&$err) { if ($n) { $err += $n; $this->error("✗ {$regla}: {$n}"); } };

        // proyecto ≥ reto ≥ empresa
        $falla('Proyecto creado antes que su reto', $proyectos->filter(fn ($p) => isset($c['microretos'][$p->microreto_id]) && $c['microproyectos'][$p->id]['created_at'] < $c['microretos'][$p->microreto_id]['created_at'])->count());
        $falla('Proyecto creado antes que su empresa', $proyectos->filter(fn ($p) => isset($c['empresas'][$p->empresa_id]) && $c['microproyectos'][$p->id]['created_at'] < $c['empresas'][$p->empresa_id]['created_at'])->count());
        $falla('Reto creado antes que su empresa', $retos->filter(fn ($r) => isset($c['empresas'][$r->empresa_id]) && $c['microretos'][$r->id]['created_at'] < $c['empresas'][$r->empresa_id]['created_at'])->count());
        $falla('Copia creada antes que su plantilla del catálogo', $empresas->filter(fn ($e) => $e->copiada_de_id && isset($c['empresas'][$e->copiada_de_id]) && $c['empresas'][$e->id]['created_at'] <= $c['empresas'][$e->copiada_de_id]['created_at'])->count());
        // los proyectos sin encuentro caen en su curso por fecha de creación (así los cuenta el panel)
        $conEnc = $encuentros->pluck('microproyecto_id')->flip();
        $falla('Proyecto sin encuentro creado fuera de su curso', $proyectos->filter(function ($p) use ($c, $conEnc) {
            if (isset($conEnc[$p->id])) return false;
            $enCurso26 = $c['microproyectos'][$p->id]['created_at'] >= '2026-09-01';
            return $enCurso26 !== ($this->cursoAsignado[$p->id] === '2026/27');
        })->count());
        // encuentro ≥ proyecto
        $mal = $encuentros->filter(fn ($e) => $c['encuentros'][$e->id]['created_at'] < $c['microproyectos'][$e->microproyecto_id]['created_at']);
        foreach ($mal as $e) $this->line("   encuentro {$e->id}: creado {$c['encuentros'][$e->id]['created_at']} · proyecto {$e->microproyecto_id} creado {$c['microproyectos'][$e->microproyecto_id]['created_at']}");
        $falla('Encuentro anterior a la creación de su proyecto', $mal->count());
        // nada en el futuro salvo los encuentros futuros elegidos
        $ahora = CarbonImmutable::now()->toDateTimeString();
        $futuro = 0;
        foreach ($c as $tabla => $filas) foreach ($filas as $id => $v) {
            if ($tabla === 'encuentros' && isset($futuros[$id])) continue;
            foreach (['created_at', 'updated_at', 'fecha_completada', 'fecha_validacion_docente', 'diagnostico_generado_en'] as $col) {
                if (!empty($v[$col]) && $v[$col] > $ahora) { $futuro++; break; }
            }
            if ($tabla === 'encuentros' && $v['fecha'] > $this->hoy->toDateString()) $futuro++;
        }
        $falla('Fechas en el futuro (fuera de los encuentros futuros elegidos)', $futuro);
        // fases dentro de la ventana de su encuentro
        $fueraVentana = 0;
        $equipoEncuentro = DB::table('equipos')->whereIn('id', array_keys($c['equipos'] ?? []))->pluck('encuentro_id', 'id');
        $equipoDeFase    = DB::table('equipo_fases')->whereIn('id', array_keys($c['equipo_fases'] ?? []))->pluck('equipo_id', 'id');
        foreach ($c['equipo_fases'] ?? [] as $id => $v) {
            if (!$v['fecha_completada']) continue;
            $eqId = $equipoDeFase[$id];
            [$ini, $fin] = $nuevaFecha[$equipoEncuentro[$eqId]];
            if ($v['fecha_completada'] < $ini->toDateString() || $v['fecha_completada'] > $fin->setTime(18, 0)->toDateTimeString()) $fueraVentana++;
        }
        $falla('Fases completadas fuera de la ventana de su encuentro', $fueraVentana);
        return $err;
    }
}
