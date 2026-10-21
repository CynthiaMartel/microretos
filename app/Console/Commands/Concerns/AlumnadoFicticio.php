<?php

namespace App\Console\Commands\Concerns;

use App\Support\AliasGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alumnado ficticio creíble para la demo: cada clase tiene una lista fija de alumnos
 * ("Nombre Animal Color", sin nombres repetidos dentro de la clase) y los encuentros de esa
 * clase toman alumnos de su lista, como pasaría en un centro real.
 *
 * Una clase es centro del proyecto + ciclo + letra + promoción: el 1ºA de 2025-26 es el 2ºA
 * de 2026-27 (mismas personas un curso después). Solo hay clases A y B (C→A, D→B).
 *
 * La lista de una clase es determinista por su clave (demo:reasignar-alumnado da siempre el
 * mismo resultado); los generadores eligen al azar el tamaño de los equipos y qué alumnos de
 * la lista participan, como cualquier otro dato de demo.
 */
trait AlumnadoFicticio
{
    // Nombres de pila variados (chicas y chicos) — nunca apellidos
    protected const NOMBRES_PILA_DEMO = [
        'Lucía', 'Sofía', 'Martina', 'María', 'Julia', 'Paula', 'Valeria', 'Emma', 'Daniela', 'Carla',
        'Alba', 'Noa', 'Sara', 'Carmen', 'Vega', 'Lola', 'Olivia', 'Valentina', 'Claudia', 'Jimena',
        'Abril', 'Chloe', 'Elena', 'Aitana', 'Irene', 'Adriana', 'Laia', 'Marina', 'Ana', 'Alejandra',
        'Candela', 'Lara', 'Inés', 'Triana', 'Nora', 'Leire', 'Mía', 'Ainhoa', 'Ariadna', 'Blanca',
        'Nerea', 'Cayetana', 'Iratxe', 'Naiara', 'Rocío', 'Celia', 'Andrea', 'Lorena', 'Patricia', 'Raquel',
        'Silvia', 'Cristina', 'Beatriz', 'Miriam', 'Yaiza', 'Tamara', 'Noelia', 'Estela', 'Gemma', 'Ruth',
        'Itziar', 'Aroa', 'Erika', 'Judith', 'Salma', 'Yasmina', 'Amira', 'Fátima', 'Zaira', 'Gabriela',
        'Camila', 'Renata', 'Ximena', 'Luna', 'Alma', 'Nahia', 'Ona', 'Maya', 'Elsa', 'Greta',
        'Hugo', 'Martín', 'Lucas', 'Mateo', 'Leo', 'Daniel', 'Alejandro', 'Pablo', 'Manuel', 'Álvaro',
        'Adrián', 'Mario', 'Diego', 'Enzo', 'Marcos', 'Izan', 'Javier', 'Bruno', 'Oliver', 'Thiago',
        'Marco', 'Antonio', 'Gonzalo', 'Miguel', 'Carlos', 'Rodrigo', 'Nicolás', 'Iker', 'Héctor', 'Rubén',
        'Sergio', 'Jorge', 'Raúl', 'Óscar', 'Iván', 'Unai', 'Aitor', 'Eric', 'Samuel', 'Gabriel',
        'Víctor', 'Joel', 'Asier', 'Aarón', 'Guillermo', 'Rayan', 'Youssef', 'Omar', 'Saúl', 'Kevin',
        'Cristian', 'Dylan', 'Gael', 'Liam', 'Nil', 'Pau', 'Jan', 'Pol', 'Arnau', 'Biel',
        'Teo', 'Dario', 'Ismael', 'Rafael', 'Alberto', 'Fernando', 'Ignacio', 'Luis', 'Jaime', 'Tomás',
    ];

    /**
     * Clave de la clase de un encuentro: centro + ciclo + letra + promoción (año de inicio
     * del curso académico en que la clase estaba en 1º).
     */
    protected function claveClase(?int $centroId, ?int $cicloId, string $letra, int $curso, ?string $fecha): string
    {
        $f = Carbon::parse($fecha ?: 'now');
        $inicioCursoAcademico = $f->month >= 9 ? $f->year : $f->year - 1;
        $promocion = $inicioCursoAcademico - ($curso - 1);

        return "c{$centroId}|ci{$cicloId}|{$letra}|p{$promocion}";
    }

    /**
     * Lista fija de la clase: $existentes (alias ya usados en la clase, se conservan) más
     * alumnos nuevos hasta $tamano, con nombres de pila no repetidos dentro de la clase.
     *
     * @param list<string> $existentes
     * @return list<string>
     */
    protected function listaDeClase(string $clave, array $existentes, int $tamano): array
    {
        // Existentes: uno por nombre de pila y como mucho $tamano (los datos antiguos tenían un
        // alias aleatorio por participación y podían inflar la lista)
        $lista = [];
        $pilas = [];
        foreach ($existentes as $alias) {
            $pila = AliasGenerator::nombreDeAlias($alias);
            if ($pila !== null && !in_array($pila, $pilas, true) && count($lista) < $tamano) {
                $lista[] = $alias;
                $pilas[] = $pila;
            }
        }

        $libres = array_values(array_diff(self::NOMBRES_PILA_DEMO, $pilas));
        $this->barajar($libres, "{$clave}|nombres");

        while (count($lista) < $tamano) {
            // Si se agotara el repertorio, se admite repetir nombre (cambia animal y color)
            $nombre = array_shift($libres) ?? self::NOMBRES_PILA_DEMO[count($lista) % count(self::NOMBRES_PILA_DEMO)];
            mt_srand(crc32("{$clave}|{$nombre}|" . count($lista)));
            $lista[] = AliasGenerator::generar($nombre, $lista);
        }
        mt_srand();

        return $lista;
    }

    /** Tamaño de la lista de una clase: entre 20 y 28 alumnos, y nunca menos de los que necesite un encuentro. */
    protected function tamanoClase(string $clave, int $minimo): int
    {
        return max($minimo, 20 + (crc32("{$clave}|tamano") % 9));
    }

    /**
     * $n alumnos distintos de la lista para un encuentro (determinista por encuentro).
     *
     * @param list<string> $lista
     * @return list<string>
     */
    protected function alumnosParaEncuentro(array $lista, string $semilla, int $n): array
    {
        $this->barajar($lista, $semilla);

        return array_slice($lista, 0, $n);
    }

    /** @param list<string> $lista */
    private function barajar(array &$lista, string $semilla): void
    {
        mt_srand(crc32($semilla));
        shuffle($lista);
        mt_srand();
    }

    /** @var array<int, array<string, list<string>>> Listas de clase ya usadas, por centro (caché por ejecución) */
    private array $listasClaseDemo = [];

    /**
     * Alumnado de un encuentro nuevo de demo, sacado de la lista de su clase: reutiliza los
     * alias de encuentros anteriores de la misma clase y completa la lista si hace falta.
     * Equipos de 3-5; el primero de cada equipo es portavoz y el resto rota $roles.
     *
     * @param list<string> $roles
     * @return array{0: list<array{alias: string, equipo_num: int, rol: string}>, 1: int}
     */
    protected function alumnadoDeClase(?int $centroId, ?int $cicloId, string $letra, int $curso, string $fecha, int $numEquipos, array $roles): array
    {
        $clave = $this->claveClase($centroId, $cicloId, $letra, $curso, $fecha);
        $c = (int) $centroId;
        $this->listasClaseDemo[$c] ??= $this->listasClaseExistentes($centroId);

        $tamanos = [];
        for ($eq = 1; $eq <= $numEquipos; $eq++) {
            $tamanos[$eq] = random_int(3, 5);
        }
        $n = array_sum($tamanos);

        $lista = $this->listaDeClase($clave, $this->listasClaseDemo[$c][$clave] ?? [], $this->tamanoClase($clave, $n));
        $this->listasClaseDemo[$c][$clave] = $lista;
        $elegidos = $this->alumnosParaEncuentro($lista, $clave . '|' . bin2hex(random_bytes(4)), $n);

        $alumnado = [];
        foreach ($tamanos as $eq => $tam) {
            for ($m = 1; $m <= $tam; $m++) {
                $alumnado[] = [
                    'alias'      => (string) array_shift($elegidos),
                    'equipo_num' => $eq,
                    'rol'        => $m === 1 ? 'portavoz' : $roles[($m - 2) % count($roles)],
                ];
            }
        }

        return [$alumnado, $n];
    }

    /**
     * Alias ya usados en cada clase de la demo (encuentros de proyectos es_demo del centro),
     * del más antiguo al más reciente.
     *
     * @return array<string, list<string>>
     */
    private function listasClaseExistentes(?int $centroId): array
    {
        $filas = DB::table('encuentros as n')
            ->join('microproyectos as p', 'p.id', '=', 'n.microproyecto_id')
            ->join('equipos as q', 'q.encuentro_id', '=', 'n.id')
            ->join('equipo_miembros as m', 'm.equipo_id', '=', 'q.id')
            ->where('p.centro_id', $centroId)->where('p.es_demo', true)
            ->select('n.fecha', 'n.curso', 'n.grupo', 'p.ciclo_id', 'p.curso as curso_proyecto', 'm.alias')
            ->orderBy('n.fecha')->orderBy('m.id')
            ->get();

        $listas = [];
        foreach ($filas as $f) {
            [$letra, $curso] = $this->letraYCurso($f->grupo, $f->curso, $f->curso_proyecto);
            $listas[$this->claveClase($centroId, $f->ciclo_id, $letra, $curso, $f->fecha)][] = (string) $f->alias;
        }

        return array_map(fn ($l) => array_values(array_unique($l)), $listas);
    }

    /**
     * "1ºB" / "B" → ['B', 1]; curso por defecto el del proyecto o 1.
     *
     * @return array{0: string, 1: int}
     */
    protected function letraYCurso(?string $grupo, ?string $curso, ?string $cursoProyecto): array
    {
        $grupo = (string) $grupo;
        preg_match('/([1-2])/', $grupo . ' ' . (string) $curso . ' ' . (string) $cursoProyecto, $m);
        preg_match('/([A-Da-d])\s*$/', $grupo, $l);

        $letra = strtoupper($l[1] ?? 'A');

        return [['C' => 'A', 'D' => 'B'][$letra] ?? $letra, (int) ($m[1] ?? 1)];
    }

    /**
     * Clase de un encuentro nuevo de demo: curso del proyecto ('ambos_cursos' → 1 o 2 al azar)
     * y letra A o B al azar.
     *
     * @return array{0: string, 1: int}
     */
    protected function claseAleatoria(?string $cursoProyecto): array
    {
        $curso = in_array($cursoProyecto, ['1', '2'], true) ? (int) $cursoProyecto : random_int(1, 2);

        return [['A', 'B'][random_int(0, 1)], $curso];
    }
}
