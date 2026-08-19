<?php

namespace App\Services;

use App\Models\CicloFormativo;
use App\Models\Microreto;
use App\Models\Modulo;
use Illuminate\Support\Collection;

class MicroretoFichaService
{
    /**
     * Añade a la instancia los campos derivados que espera la ficha de reto
     * (MicroretoModal.vue): centro_educativo, familia, empresa_es_simulada y curso.
     * Requiere que $reto tenga cargadas las relaciones empresa.centroEducativo y empresa.familias.
     *
     * Pensado para UN solo microreto (show()): derivarCurso()/derivarCursoDeEvaluacion()
     * lanzan queries propias. Para listados, usar enriquecerLote() en su lugar.
     */
    public static function enriquecer(Microreto $reto): Microreto
    {
        self::aplicarDatosEmpresa($reto);

        if (is_null($reto->curso)) {
            $reto->curso = self::derivarCurso($reto->ciclo_id, $reto->ciclo, $reto->modulo);
        }

        if (is_null($reto->curso) && $reto->evaluacion_oficial && $reto->ciclo_id) {
            $reto->curso = self::derivarCursoDeEvaluacion($reto->ciclo_id, $reto->evaluacion_oficial);
        }

        self::anotarCursoPorModulo($reto);

        return $reto;
    }

    /**
     * Añade `curso` (1|2|null) a cada entrada de evaluacion_oficial, buscando el módulo
     * real por nombre dentro del ciclo del reto — así la ficha puede mostrar a qué curso
     * pertenece cada módulo sugerido por la IA, sin depender de que se haya guardado en
     * su momento (funciona igual para retos antiguos y nuevos). Solo para enriquecer() —
     * enriquecerLote() no lo necesita porque los listados no muestran este detalle.
     */
    private static function anotarCursoPorModulo(Microreto $reto): void
    {
        if (empty($reto->evaluacion_oficial) || !$reto->ciclo_id) {
            return;
        }

        $cache = [];
        $evaluacion = $reto->evaluacion_oficial;
        foreach ($evaluacion as &$item) {
            $nombreModulo = $item['modulo'] ?? null;
            if (!$nombreModulo) {
                continue;
            }
            if (!array_key_exists($nombreModulo, $cache)) {
                $cache[$nombreModulo] = self::buscarCursoDeModulo($reto->ciclo_id, $nombreModulo);
            }
            $item['curso'] = $cache[$nombreModulo];
        }
        unset($item);

        $reto->evaluacion_oficial = $evaluacion;
    }

    /**
     * Igual que enriquecer(), pero pensado para listados (index()): precarga módulos y ciclos
     * UNA sola vez y deriva el curso en memoria, en vez de hacer las queries de
     * derivarCurso()/derivarCursoDeEvaluacion() por cada fila (evita N+1 sobre el listado completo).
     * No aplica el fallback de evaluacion_oficial (tampoco lo aplicaba la lógica que sustituye).
     */
    public static function enriquecerLote(Collection $retos): Collection
    {
        $modulosPorCiclo = Modulo::select('idcicloformativo', 'nombre', 'curso')
            ->get()
            ->groupBy('idcicloformativo');
        $ciclosPorNombre = CicloFormativo::pluck('id', 'nombre');

        return $retos->each(function (Microreto $reto) use ($modulosPorCiclo, $ciclosPorNombre) {
            self::aplicarDatosEmpresa($reto);

            if (is_null($reto->curso) && $reto->modulo && $reto->modulo !== 'Transversal') {
                $cicloId = $reto->ciclo_id ?? $ciclosPorNombre->get($reto->ciclo);
                if ($cicloId) {
                    $primerModulo    = trim(explode(' y ', $reto->modulo)[0]);
                    $modulosDelCiclo = $modulosPorCiclo->get($cicloId, collect());
                    $modulo = $modulosDelCiclo->first(fn($m) =>
                        $m->nombre === $primerModulo ||
                        str_starts_with($m->nombre, rtrim($primerModulo, '.'))
                    );
                    $reto->curso = $modulo?->curso;
                }
            }
        });
    }

    private static function aplicarDatosEmpresa(Microreto $reto): void
    {
        $reto->es_simulado = (bool) $reto->es_simulado;

        if ($reto->empresa) {
            $reto->centro_educativo = $reto->empresa->centroEducativo?->nombre
                ?? $reto->empresa->centro_educativo
                ?? 'Centro Desconocido';

            $reto->familia = $reto->empresa->familias->first()?->nombre
                ?? 'Familia Desconocida';

            $reto->empresa_es_simulada = (bool) $reto->empresa->es_simulada;
        } else {
            $reto->centro_educativo    = 'Centro Desconocido';
            $reto->familia             = 'Familia Desconocida';
            $reto->empresa_es_simulada = false;
        }
    }

    /**
     * Deduce el número de curso (1 o 2) a partir del módulo guardado en el microreto.
     * Primero intenta por ciclo_id (FK), luego por nombre de ciclo (legacy).
     * Tolerante al punto final en nombres de módulo (datos BOE vs. texto libre).
     * Usado como fallback en guardarEnBD()/guardarLote() cuando el frontend no manda
     * `curso` ya calculado. No tiene en cuenta `multimodulo` (multi-módulo del mismo
     * curso) ni el Escenario B (ambos cursos) — esos casos siempre llegan con `curso`
     * ya resuelto explícitamente por el generador.
     */
    public static function derivarCurso(?int $cicloId, ?string $cicloNombre, ?string $moduloTexto): ?int
    {
        if (!$moduloTexto || $moduloTexto === 'Transversal') {
            return null;
        }

        // El campo 'modulo' puede ser "Módulo A y Módulo B" — tomamos el primero
        $primerModulo = trim(explode(' y ', $moduloTexto)[0]);

        $cicloIdResuelto = $cicloId;

        if (!$cicloIdResuelto && $cicloNombre) {
            $cicloIdResuelto = CicloFormativo::where('nombre', $cicloNombre)->value('id');
        }

        if (!$cicloIdResuelto) {
            return null;
        }

        // Intento exacto primero; si falla, toleramos punto final (nombres BOE acaban en '.')
        $curso = Modulo::where('idcicloformativo', $cicloIdResuelto)
            ->where('nombre', $primerModulo)
            ->value('curso');

        if (is_null($curso)) {
            $curso = Modulo::where('idcicloformativo', $cicloIdResuelto)
                ->where('nombre', 'LIKE', rtrim($primerModulo, '.') . '%')
                ->orderByRaw('LENGTH(nombre) ASC') // preferir el más corto (más específico)
                ->value('curso');
        }

        return $curso;
    }

    /**
     * Fallback: cuando modulo = 'Transversal', intentamos derivar el curso
     * mirando los módulos referenciados en el JSON de evaluacion_oficial.
     */
    private static function derivarCursoDeEvaluacion(int $cicloId, array $evaluacionOficial): ?int
    {
        foreach ($evaluacionOficial as $item) {
            $nombreModulo = $item['modulo'] ?? null;
            if (!$nombreModulo) continue;

            $curso = self::buscarCursoDeModulo($cicloId, $nombreModulo);
            if (!is_null($curso)) {
                return $curso;
            }
        }

        return null;
    }

    /**
     * Busca el curso (1|2) de un módulo por nombre dentro de un ciclo. Tolerante al
     * punto final en nombres BOE (p. ej. "Instalaciones eléctricas interiores." vs
     * el texto libre guardado sin el punto).
     */
    private static function buscarCursoDeModulo(int $cicloId, string $nombreModulo): ?int
    {
        return Modulo::where('idcicloformativo', $cicloId)
            ->where('nombre', 'LIKE', rtrim($nombreModulo, '.') . '%')
            ->orderByRaw('LENGTH(nombre) ASC')
            ->value('curso');
    }
}
