<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AlumnadoFicticio;
use App\Support\AliasGenerator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Hace creíble el alumnado de la demo SIN regenerarla (conserva proyectos, fases, tareas,
 * reflexiones y diagnósticos): el script antiguo inventaba alumnado nuevo en cada encuentro
 * con ~40 nombres, así que el mismo nombre salía decenas de veces en clases distintas.
 *
 * Ahora cada clase ficticia (centro + ciclo + letra + promoción, ver AlumnadoFicticio) tiene
 * una lista fija de 20-28 alumnos sin nombres repetidos, y cada encuentro toma alumnos de la
 * lista de su clase (solo clases A y B: las C/D antiguas se juntan con A/B, así el mismo
 * alumno sale en varios proyectos de su clase). Se reescriben con los alias nuevos: equipo_miembros.alias, responsables
 * de tarea, autores de reflexión, miembros de F0, el reparto del encuentro y los alias que
 * aparezcan en textos (diagnósticos, datos de fases, reflexiones). Además los encuentros
 * pasan al formato del formulario real: curso "1º"/"2º", clase "A"-"D" y ciclo relleno.
 *
 * Solo encuentros de demo: proyecto es_demo del centro DuaLab (10). Incluye la papelera.
 * Dry-run por defecto; --commit aplica en una transacción. Comando de un solo uso sobre los
 * datos de demo (~2.000 filas): carga todo en memoria y actualiza fila a fila a propósito.
 */
class DemoReasignarAlumnado extends Command
{
    use AlumnadoFicticio;

    protected $signature = 'demo:reasignar-alumnado
                            {--commit : Aplica los cambios en BD. Sin esta opción es un dry-run.}';

    protected $description = 'Da al alumnado de la demo listas fijas por clase con nombres variados (sin regenerar la demo).';

    private const CENTRO_DEMO = 10;

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $encuentros = DB::table('encuentros as n')
            ->join('microproyectos as p', 'p.id', '=', 'n.microproyecto_id')
            ->leftJoin('ciclos_formativos as c', 'c.id', '=', 'p.ciclo_id')
            ->where('p.centro_id', self::CENTRO_DEMO)
            ->where('p.es_demo', true)
            ->select('n.id', 'n.fecha', 'n.curso', 'n.grupo', 'n.alumnados', 'n.ciclo_formativo', 'p.centro_id',
                     'p.ciclo_id', 'p.curso as curso_proyecto', 'c.nombre as ciclo_nombre')
            ->orderBy('n.fecha')->orderBy('n.id')
            ->get();

        if ($encuentros->isEmpty()) {
            $this->info('No hay encuentros de demo. Nada que hacer.');
            return self::SUCCESS;
        }

        $equipos = DB::table('equipos')->whereIn('encuentro_id', $encuentros->pluck('id'))
            ->select('id', 'encuentro_id', 'numero_equipo', 'diagnostico_final')->orderBy('numero_equipo')->orderBy('id')->get();
        $miembros = DB::table('equipo_miembros')->whereIn('equipo_id', $equipos->pluck('id'))
            ->select('id', 'equipo_id', 'alias', 'rol')->orderBy('id')->get()->groupBy('equipo_id');
        $equiposPorEncuentro = $equipos->groupBy('encuentro_id');

        // ── Clase de cada encuentro y nº de alumnos que necesita ──────────────
        $clases = [];
        $infoEncuentro = [];
        foreach ($encuentros as $e) {
            [$letra, $curso] = $this->letraYCurso($e->grupo, $e->curso, $e->curso_proyecto);
            $clave = $this->claveClase($e->centro_id, $e->ciclo_id, $letra, $curso, $e->fecha);
            $n = $equiposPorEncuentro->get($e->id, collect())->sum(fn ($q) => $miembros->get($q->id, collect())->count());
            $infoEncuentro[$e->id] = compact('letra', 'curso', 'clave', 'n');
            $clases[$clave] = max($clases[$clave] ?? 0, $n);
        }

        $listas = [];
        foreach ($clases as $clave => $maxEncuentro) {
            $listas[$clave] = $this->listaDeClase($clave, [], $this->tamanoClase($clave, $maxEncuentro));
        }

        // ── Alias nuevo de cada miembro ─────────────────────────────────────────
        $aliasNuevo = [];      // miembro_id → alias
        $traduccion = [];      // equipo_id → [alias antiguo normalizado → alias nuevo]
        $reemplazos = [];      // equipo_id → [alias antiguo literal → alias nuevo] (textos)
        foreach ($encuentros as $e) {
            $info = $infoEncuentro[$e->id];
            $asignados = $this->alumnosParaEncuentro($listas[$info['clave']], "{$info['clave']}|e{$e->id}", $info['n']);
            $i = 0;
            foreach ($equiposPorEncuentro->get($e->id, collect()) as $q) {
                foreach ($miembros->get($q->id, collect()) as $m) {
                    $nuevo = $asignados[$i++];
                    $aliasNuevo[$m->id] = $nuevo;
                    $traduccion[$q->id][AliasGenerator::normalizar($m->alias)] = $nuevo;
                    if (trim((string) $m->alias) !== '') {
                        $reemplazos[$q->id][trim($m->alias)] = $nuevo;
                    }
                }
            }
        }

        // ── Responsables, autores y textos ──────────────────────────────────────
        $equipoIds = $equipos->pluck('id');
        $updTareas = $this->planCifrado('equipo_tareas', 'responsable', $equipoIds, $traduccion);
        $updReflexiones = $this->planCifrado('equipo_reflexiones', 'autor_nombre', $equipoIds, $traduccion);

        $updRespuestas = [];
        foreach (DB::table('equipo_reflexiones')->whereIn('equipo_id', $equipoIds)->select('id', 'equipo_id', 'respuestas')->get() as $r) {
            $nuevo = $this->reemplazarEnJson($r->respuestas, $reemplazos[$r->equipo_id] ?? []);
            if ($nuevo !== null) {
                $updRespuestas[$r->id] = $nuevo;
            }
        }

        $updFases = [];
        foreach (DB::table('equipo_fases')->whereIn('equipo_id', $equipoIds)->whereNotNull('datos')->select('id', 'equipo_id', 'numero_fase', 'datos')->get() as $f) {
            $json = (string) $f->datos;
            if ((int) $f->numero_fase === 0) {
                // Objetos, no arrays asociativos: así un {} vacío sigue siendo {} al recodificar
                $datos = json_decode($json);
                if ($datos instanceof \stdClass && is_array($datos->miembros ?? null)) {
                    $cambio = false;
                    foreach ($datos->miembros as $mf) {
                        if ($mf instanceof \stdClass && isset($mf->id, $aliasNuevo[(int) $mf->id])) {
                            $mf->alias = $aliasNuevo[(int) $mf->id];
                            $cambio = true;
                        }
                    }
                    if ($cambio) {
                        $json = (string) json_encode($datos, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
            $texto = $this->reemplazarEnJson($json, $reemplazos[$f->equipo_id] ?? []) ?? $json;
            if ($texto !== (string) $f->datos) {
                $updFases[$f->id] = $texto;
            }
        }

        $updDiagnosticos = [];
        foreach ($equipos as $q) {
            $nuevo = $this->reemplazarEnJson($q->diagnostico_final, $reemplazos[$q->id] ?? []);
            if ($nuevo !== null) {
                $updDiagnosticos[$q->id] = $nuevo;
            }
        }

        // ── Encuentros: reparto solo con alias y formato del formulario real ────
        $updEncuentros = [];
        foreach ($encuentros as $e) {
            $info = $infoEncuentro[$e->id];
            $snapshot = [];
            foreach ($equiposPorEncuentro->get($e->id, collect()) as $q) {
                foreach ($miembros->get($q->id, collect()) as $m) {
                    $snapshot[] = ['alias' => $aliasNuevo[$m->id], 'equipo_num' => (int) $q->numero_equipo, 'rol' => $m->rol];
                }
            }
            $updEncuentros[$e->id] = [
                'curso'           => "{$info['curso']}º",
                'grupo'           => $info['letra'],
                'ciclo_formativo' => $e->ciclo_nombre ?: $e->ciclo_formativo,
                'alumnados'       => $snapshot ? Crypt::encryptString((string) json_encode($snapshot, JSON_UNESCAPED_UNICODE)) : $e->alumnados,
            ];
        }

        $this->informe($encuentros, $listas, $aliasNuevo, $infoEncuentro, $miembros, $equiposPorEncuentro,
            count($updTareas), count($updReflexiones), count($updRespuestas) + count($updFases) + count($updDiagnosticos));

        if (!$commit) {
            $this->warn('Dry-run: no se ha escrito nada. Ejecuta con --commit para aplicarlo.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aliasNuevo, $updTareas, $updReflexiones, $updRespuestas, $updFases, $updDiagnosticos, $updEncuentros) {
            foreach ($aliasNuevo as $id => $alias) {
                DB::table('equipo_miembros')->where('id', $id)->update(['alias' => $alias]);
            }
            foreach ($updTareas as $id => $v) {
                DB::table('equipo_tareas')->where('id', $id)->update(['responsable' => $v]);
            }
            foreach ($updReflexiones as $id => $v) {
                DB::table('equipo_reflexiones')->where('id', $id)->update(['autor_nombre' => $v]);
            }
            foreach ($updRespuestas as $id => $v) {
                DB::table('equipo_reflexiones')->where('id', $id)->update(['respuestas' => $v]);
            }
            foreach ($updFases as $id => $v) {
                DB::table('equipo_fases')->where('id', $id)->update(['datos' => $v]);
            }
            foreach ($updDiagnosticos as $id => $v) {
                DB::table('equipos')->where('id', $id)->update(['diagnostico_final' => $v]);
            }
            foreach ($updEncuentros as $id => $v) {
                DB::table('encuentros')->where('id', $id)->update($v);
            }
        });

        $this->info('Hecho: alumnado de la demo reasignado.');

        return self::SUCCESS;
    }

    /**
     * Columna cifrada con un alias (responsable / autor) → alias nuevo, cifrado igual que
     * el cast 'encrypted' del modelo. Si no corresponde a ningún miembro, se deja vacía.
     *
     * @param \Illuminate\Support\Collection<int, int> $equipoIds
     * @param array<int, array<string, string>> $traduccion
     * @return array<int, ?string>
     */
    private function planCifrado(string $tabla, string $columna, $equipoIds, array $traduccion): array
    {
        $plan = [];
        foreach (DB::table($tabla)->whereIn('equipo_id', $equipoIds)->whereNotNull($columna)->select('id', 'equipo_id', $columna)->get() as $fila) {
            $actual = $this->descifrar($fila->{$columna});
            $nuevo = $traduccion[$fila->equipo_id][AliasGenerator::normalizar($actual)] ?? null;
            $plan[$fila->id] = $nuevo === null ? null : Crypt::encryptString($nuevo);
        }

        return $plan;
    }

    /**
     * Sustituye alias antiguos por los nuevos dentro de un JSON, sobre el texto tal cual (sin
     * decodificar y recodificar, que cambiaría su forma: {} → []). Cubre las dos formas en
     * que puede estar guardado un alias: con los acentos escapados (\u00ed) o en claro.
     * Null si no cambia nada.
     *
     * @param array<string, string> $reemplazos
     */
    private function reemplazarEnJson(?string $json, array $reemplazos): ?string
    {
        if ($json === null || $json === '' || !$reemplazos) {
            return null;
        }
        $pares = [];
        foreach ($reemplazos as $viejo => $nuevo) {
            $pares[$viejo] = $nuevo;
            $pares[substr((string) json_encode($viejo), 1, -1)] = substr((string) json_encode($nuevo), 1, -1);
        }
        // strtr prueba primero las claves más largas: no sustituye a medias un alias que contenga a otro
        $resultado = strtr($json, $pares);

        return $resultado === $json ? null : $resultado;
    }

    private function descifrar(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            return $valor;
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, \stdClass> $encuentros
     * @param array<string, list<string>> $listas
     * @param array<int, string> $aliasNuevo
     * @param array<int, array{letra: string, curso: int, clave: string, n: int}> $infoEncuentro
     * @param \Illuminate\Support\Collection<array-key, \Illuminate\Support\Collection<int, \stdClass>> $miembros
     * @param \Illuminate\Support\Collection<array-key, \Illuminate\Support\Collection<int, \stdClass>> $equiposPorEncuentro
     */
    private function informe($encuentros, array $listas, array $aliasNuevo, array $infoEncuentro, $miembros, $equiposPorEncuentro,
                             int $tareas, int $reflexiones, int $textos): void
    {
        $antes = $miembros->flatten(1)->map(fn ($m) => AliasGenerator::nombreDeAlias($m->alias) ?? '')->countBy();
        $usados = collect($aliasNuevo)->unique();
        $participaciones = collect($aliasNuevo)->countBy();
        // Solo el alumnado que aparece en algún encuentro (el resto de la lista no se ve en la app)
        $pilaUsada = $usados->map(fn ($a) => AliasGenerator::nombreDeAlias($a))->countBy();
        $tam = collect($listas)->map(fn ($l) => count($l));

        $this->table(['', 'Antes', 'Después'], [
            ['Encuentros de demo', $encuentros->count(), $encuentros->count()],
            ['Alumnos distintos', $antes->sum() . ' (uno por participación)', $usados->count() . ' (en ' . count($listas) . ' clases)'],
            ['Alumnos por clase (mín · media · máx)', '—', $tam->min() . ' · ' . round((float) $tam->avg(), 1) . ' · ' . $tam->max()],
            ['Proyectos por alumno (media · máx)', '1 · 1', round((float) $participaciones->avg(), 1) . ' · ' . $participaciones->max()],
            ['Nombre de pila más repetido', $antes->sortDesc()->keys()->first() . ' (' . $antes->max() . ' alumnos)', $pilaUsada->sortDesc()->keys()->first() . ' (' . $pilaUsada->max() . ' alumnos)'],
            ['Alumnos por nombre de pila (media)', round($antes->sum() / max(1, $antes->count()), 1), round($usados->count() / max(1, $pilaUsada->count()), 1)],
            ['Nombres de pila distintos', $antes->count(), $pilaUsada->count()],
        ]);
        $this->line("Responsables de tarea: {$tareas} · autores de reflexión: {$reflexiones} · textos con alias antiguos: {$textos}");

        $clave = array_key_first($listas);
        $this->line("Ejemplo — clase {$clave}: " . implode(', ', array_slice($listas[$clave], 0, 8)) . '…');
    }
}
