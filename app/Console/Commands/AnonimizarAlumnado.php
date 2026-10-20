<?php

namespace App\Console\Commands;

use App\Support\AliasGenerator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Protección de datos — fase 2: elimina de la BD los nombres reales completos del alumnado
 * que quedan de antes de trabajar solo con alias ("NombreDePila Animal Color", ver
 * AliasGenerator).
 *
 *  1. Alias: se conservan los que ya son "NombreDePila Animal Color" y únicos en su
 *     encuentro (los antiguos ya lo eran: "Adrián Colibrí Lima"). El resto (vacíos,
 *     repetidos o sin nombre) se regeneran con la PRIMERA palabra del nombre real — los
 *     apellidos se descartan — o, si no hay nombre, con el nombre por defecto.
 *  2. equipo_tareas.responsable y equipo_reflexiones.autor_nombre: nombre real / alias
 *     antiguo → alias nuevo del miembro. Si no corresponde a ningún miembro, se vacía.
 *  3. equipo_fases (F0) datos.miembros[]: fuera 'nombre', alias nuevo.
 *  4. encuentros.alumnados (snapshot del reparto): solo alias, grupo y rol.
 *  5. equipo_miembros.nombre → NULL.
 *
 * Además informa (sin tocarlos) de los textos libres donde aparece algún nombre completo
 * de un miembro de ese equipo — diagnósticos de IA, datos de fases, reflexiones —, para
 * revisarlos a mano.
 *
 * Dry-run por defecto: calcula todo sin escribir nada. Solo con --commit aplica los cambios,
 * en una transacción. Idempotente. Nunca muestra nombres por consola, solo recuentos e ids.
 * Incluye encuentros en la papelera (DB::table no aplica el scope de soft delete).
 */
class AnonimizarAlumnado extends Command
{
    protected $signature = 'alumnado:anonimizar
                            {--commit : Aplica los cambios en BD. Sin esta opción es un dry-run.}';

    protected $description = 'Sustituye los nombres reales del alumnado por alias y vacía equipo_miembros.nombre.';

    /** @var array<int, array<string, string>> equipo_id → (texto normalizado → alias nuevo) */
    private array $traduccion = [];

    /** @var array<int, string> miembro_id → alias nuevo */
    private array $aliasMiembro = [];

    /** @var array<int, array<int, string>> equipo_id → nombres completos (para el informe de textos libres) */
    private array $nombresEquipo = [];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $equipos  = DB::table('equipos')->select('id', 'encuentro_id', 'numero_equipo', 'diagnostico_final')->orderBy('id')->get();
        $miembros = DB::table('equipo_miembros')->select('id', 'equipo_id', 'nombre', 'alias', 'rol')->orderBy('id')->get();

        // ── 1. Alias ───────────────────────────────────────────────────────────
        $cambiosMiembros = $this->planificarAlias($equipos, $miembros);

        // ── 2. Responsables y autores ──────────────────────────────────────────
        $cambiosTareas      = $this->planificarTexto('equipo_tareas', 'responsable');
        $cambiosReflexiones = $this->planificarTexto('equipo_reflexiones', 'autor_nombre');

        // ── 3. Miembros en los datos de F0 ─────────────────────────────────────
        $cambiosF0 = $this->planificarF0();

        // ── 4. Snapshot del reparto en los encuentros ──────────────────────────
        $cambiosEncuentros = $this->planificarEncuentros($equipos, $miembros);

        // ── Informe ────────────────────────────────────────────────────────────
        $conNombre = $miembros->filter(fn ($m) => $m->nombre !== null && $m->nombre !== '')->count();
        $this->table(['Cambio', 'Filas'], [
            ['Alias conservados (ya eran "Nombre Animal Color")', $cambiosMiembros['conservados']],
            ['Alias regenerados (inválidos o repetidos en la clase)', $cambiosMiembros['regenerados']],
            ['Alias creados (miembros sin alias)', $cambiosMiembros['creados']],
            ['  · de ellos sin nombre de pila (usan "' . AliasGenerator::NOMBRE_POR_DEFECTO . '")', $cambiosMiembros['sinNombre']],
            ['Tareas: responsable traducido a alias', $cambiosTareas['traducidos']],
            ['Tareas: responsable vaciado (no es ningún miembro)', $cambiosTareas['vaciados']],
            ['Reflexiones: autor traducido a alias', $cambiosReflexiones['traducidos']],
            ['Reflexiones: autor vaciado (no es ningún miembro)', $cambiosReflexiones['vaciados']],
            ['Fases F0 sin nombres en datos.miembros', count($cambiosF0)],
            ['Encuentros con el reparto reescrito solo con alias', count($cambiosEncuentros)],
            ['Miembros con nombre real a vaciar', $conNombre],
        ]);

        $this->informarTextosLibres($equipos);

        if (!$commit) {
            $this->warn('Dry-run: no se ha escrito nada. Haz una copia de la BD y ejecuta con --commit para aplicarlo.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambiosMiembros, $cambiosTareas, $cambiosReflexiones, $cambiosF0, $cambiosEncuentros) {
            foreach ($cambiosMiembros['updates'] as $id => $alias) {
                DB::table('equipo_miembros')->where('id', $id)->update(['alias' => $alias]);
            }
            foreach (['equipo_tareas' => [$cambiosTareas, 'responsable'], 'equipo_reflexiones' => [$cambiosReflexiones, 'autor_nombre']] as $tabla => [$cambios, $columna]) {
                foreach ($cambios['updates'] as $id => $alias) {
                    // Las columnas siguen con el cast 'encrypted' en sus modelos
                    DB::table($tabla)->where('id', $id)->update([$columna => $alias === null ? null : Crypt::encryptString($alias)]);
                }
            }
            foreach ($cambiosF0 as $id => $datos) {
                DB::table('equipo_fases')->where('id', $id)->update(['datos' => json_encode($datos, JSON_UNESCAPED_UNICODE)]);
            }
            foreach ($cambiosEncuentros as $id => $lista) {
                // Mismo formato que el cast 'encrypted:array' de Encuentro
                DB::table('encuentros')->where('id', $id)->update(['alumnados' => Crypt::encryptString(json_encode($lista, JSON_UNESCAPED_UNICODE))]);
            }
            DB::table('equipo_miembros')->whereNotNull('nombre')->update(['nombre' => null]);
        });

        $restantes = DB::table('equipo_miembros')->whereNotNull('nombre')->count();
        $this->info("Hecho. Miembros que aún tienen nombre: {$restantes}.");

        return self::SUCCESS;
    }

    /**
     * Decide el alias final de cada miembro y prepara la traducción texto → alias por equipo.
     *
     * @return array{conservados: int, regenerados: int, creados: int, sinNombre: int, updates: array<int, string>}
     */
    private function planificarAlias($equipos, $miembros): array
    {
        $porEquipo = $miembros->groupBy('equipo_id');
        $claveEncuentro = fn ($equipo) => $equipo->encuentro_id ? "e{$equipo->encuentro_id}" : "q{$equipo->id}";

        // Primera pasada: alias válidos que se conservan (el primero de cada repetido)
        $usados = [];
        $conservados = [];
        foreach ($equipos as $equipo) {
            $k = $claveEncuentro($equipo);
            $usados[$k] ??= [];
            foreach ($porEquipo->get($equipo->id, collect()) as $m) {
                $norm = AliasGenerator::normalizar($m->alias);
                if (AliasGenerator::esValido($m->alias) && !isset($usados[$k][$norm])) {
                    $usados[$k][$norm] = true;
                    $conservados[$m->id] = true;
                }
            }
        }

        $regenerados = 0;
        $creados = 0;
        $sinNombre = 0;
        $updates = [];
        foreach ($equipos as $equipo) {
            $k = $claveEncuentro($equipo);
            $this->traduccion[$equipo->id] = [];
            $this->nombresEquipo[$equipo->id] = [];

            foreach ($porEquipo->get($equipo->id, collect()) as $m) {
                $nombre = $this->descifrar($m->nombre);
                if (isset($conservados[$m->id])) {
                    $nuevo = $m->alias;
                } else {
                    // Solo la primera palabra del nombre real: los apellidos nunca pasan al alias
                    $pila = AliasGenerator::nombreDePila($nombre) ?? AliasGenerator::nombreDeAlias($m->alias);
                    $pila ?? $sinNombre++;
                    $nuevo = AliasGenerator::generar($pila, array_keys($usados[$k]));
                    $usados[$k][AliasGenerator::normalizar($nuevo)] = true;
                    $updates[$m->id] = $nuevo;
                    trim((string) $m->alias) === '' ? $creados++ : $regenerados++;
                }
                $this->aliasMiembro[$m->id] = $nuevo;

                foreach ([$nombre, $m->alias, $nuevo] as $texto) {
                    $norm = AliasGenerator::normalizar($texto);
                    if ($norm !== '') {
                        $this->traduccion[$equipo->id][$norm] ??= $nuevo;
                    }
                }
                if (str_contains(trim($nombre), ' ')) {
                    $this->nombresEquipo[$equipo->id][] = trim($nombre);
                }
            }
        }

        return [
            'conservados' => count($conservados),
            'regenerados' => $regenerados,
            'creados'     => $creados,
            'sinNombre'   => $sinNombre,
            'updates'     => $updates,
        ];
    }

    /** @return array{traducidos: int, vaciados: int, updates: array<int, ?string>} */
    private function planificarTexto(string $tabla, string $columna): array
    {
        $traducidos = 0;
        $vaciados = 0;
        $updates = [];

        DB::table($tabla)->whereNotNull($columna)->select('id', 'equipo_id', $columna)->orderBy('id')
            ->chunk(500, function ($filas) use ($columna, &$traducidos, &$vaciados, &$updates) {
                foreach ($filas as $fila) {
                    $actual = $this->descifrar($fila->{$columna});
                    $alias = $this->traduccion[$fila->equipo_id][AliasGenerator::normalizar($actual)] ?? null;
                    if ($alias === $actual) {
                        continue; // ya es el alias definitivo
                    }
                    $updates[$fila->id] = $alias;
                    $alias === null ? $vaciados++ : $traducidos++;
                }
            });

        return ['traducidos' => $traducidos, 'vaciados' => $vaciados, 'updates' => $updates];
    }

    /** @return array<int, array> equipo_fases.id → datos limpios */
    private function planificarF0(): array
    {
        $cambios = [];
        $filas = DB::table('equipo_fases')->where('numero_fase', 0)->whereNotNull('datos')->select('id', 'equipo_id', 'datos')->get();

        foreach ($filas as $fila) {
            $datos = json_decode($fila->datos, true);
            if (!is_array($datos) || !is_array($datos['miembros'] ?? null)) {
                continue;
            }

            $nuevos = array_map(function ($m) use ($fila) {
                if (!is_array($m)) {
                    return $m;
                }
                $alias = $this->aliasMiembro[(int) ($m['id'] ?? 0)]
                    ?? $this->traduccion[$fila->equipo_id][AliasGenerator::normalizar($m['nombre'] ?? $m['alias'] ?? '')]
                    ?? null;
                unset($m['nombre']);
                $m['alias'] = $alias;
                return $m;
            }, $datos['miembros']);

            if ($nuevos !== $datos['miembros']) {
                $datos['miembros'] = $nuevos;
                $cambios[$fila->id] = $datos;
            }
        }

        return $cambios;
    }

    /** @return array<int, array> encuentros.id → reparto solo con alias */
    private function planificarEncuentros($equipos, $miembros): array
    {
        $cambios = [];
        $equiposPorEncuentro = $equipos->whereNotNull('encuentro_id')->groupBy('encuentro_id');
        $miembrosPorEquipo = $miembros->groupBy('equipo_id');

        $filas = DB::table('encuentros')->whereNotNull('alumnados')->select('id', 'alumnados')->get();
        foreach ($filas as $fila) {
            $actual = json_decode($this->descifrar($fila->alumnados), true);
            if (!is_array($actual)) {
                continue;
            }

            $equiposEncuentro = $equiposPorEncuentro->get($fila->id, collect());
            if ($equiposEncuentro->isNotEmpty()) {
                // Con grupos creados: el reparto real son sus miembros
                $lista = $equiposEncuentro->sortBy('numero_equipo')->flatMap(fn ($e) => $miembrosPorEquipo->get($e->id, collect())
                    ->map(fn ($m) => ['alias' => $this->aliasMiembro[$m->id], 'equipo_num' => (int) $e->numero_equipo, 'rol' => $m->rol])
                )->values()->all();
            } else {
                // Sin grupos todavía: se conserva el reparto, cambiando nombres por alias
                $usados = [];
                $lista = array_values(array_map(function ($a) use (&$usados) {
                    $alias = is_array($a) && AliasGenerator::esValido($a['alias'] ?? null) && !in_array(AliasGenerator::normalizar($a['alias']), $usados, true)
                        ? $a['alias']
                        : AliasGenerator::generar(
                            is_array($a) ? (AliasGenerator::nombreDePila($a['nombre'] ?? null) ?? AliasGenerator::nombreDeAlias($a['alias'] ?? null)) : null,
                            $usados
                        );
                    $usados[] = AliasGenerator::normalizar($alias);
                    return ['alias' => $alias, 'equipo_num' => is_array($a) ? ($a['equipo_num'] ?? null) : null, 'rol' => is_array($a) ? ($a['rol'] ?? null) : null];
                }, $actual));
            }

            if ($lista !== $actual) {
                $cambios[$fila->id] = $lista;
            }
        }

        return $cambios;
    }

    /**
     * Textos libres que mencionan el nombre completo de algún miembro del equipo: no se
     * pueden limpiar de forma fiable, así que solo se listan (ids) para revisión manual.
     */
    private function informarTextosLibres($equipos): void
    {
        $contiene = function (?string $texto, int $equipoId): bool {
            if (!$texto) {
                return false;
            }
            foreach ($this->nombresEquipo[$equipoId] ?? [] as $nombre) {
                if (mb_stripos($texto, $nombre) !== false) {
                    return true;
                }
            }
            return false;
        };

        // Las columnas JSON guardan los acentos escapados (\u00e1): se decodifican para buscar
        $legible = function (?string $json, bool $sinMiembros = false): ?string {
            $valor = json_decode((string) $json, true);
            if (!is_array($valor)) {
                return $json;
            }
            if ($sinMiembros) {
                unset($valor['miembros']); // la lista de F0 ya la limpia este comando
            }
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        };

        $diagnosticos = $equipos->filter(fn ($e) => $contiene($legible($e->diagnostico_final), $e->id))->pluck('id');
        $fases = DB::table('equipo_fases')->whereNotNull('datos')->select('id', 'equipo_id', 'datos')->get()
            ->filter(fn ($f) => $contiene($legible($f->datos, true), $f->equipo_id))->pluck('equipo_id')->unique();
        $reflexiones = DB::table('equipo_reflexiones')->select('id', 'equipo_id', 'respuestas')->get()
            ->filter(fn ($r) => $contiene($legible($r->respuestas), $r->equipo_id))->pluck('equipo_id')->unique();

        $this->newLine();
        $this->line('Textos libres que mencionan el nombre completo de un miembro (revisar a mano, no se modifican):');
        $this->line('  Diagnósticos finales (equipos): ' . ($diagnosticos->implode(', ') ?: 'ninguno'));
        $this->line('  Datos de fases (equipos): ' . ($fases->values()->implode(', ') ?: 'ninguno'));
        $this->line('  Reflexiones (equipos): ' . ($reflexiones->values()->implode(', ') ?: 'ninguno'));
        $this->newLine();
    }

    private function descifrar(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            return $valor; // en claro (datos anteriores al cifrado)
        }
    }
}
