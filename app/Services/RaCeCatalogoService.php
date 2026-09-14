<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Selección de RA/CE asistida por IA a partir del catálogo oficial (closed-book):
 * la IA solo elige ids de un currículo cerrado que se le entrega, nunca redacta
 * el texto — el texto final siempre se recupera de la base de datos. Se usa tanto
 * al generar microretos (MicroretoIAController) como al sugerir RA/CE en un
 * microproyecto de StartUp Day (MicroproyectoController), para que ambos flujos
 * compartan la misma garantía de no-alucinación.
 */
class RaCeCatalogoService
{
    /**
     * Construye el índice ra_id -> {ra, modulo} y el texto de currículo (con ids
     * reales embebidos, p.ej. "[RA id=123]: ...") a partir de módulos cargados con
     * `ras.criteriosEvaluacion`. Los RA sin CE se omiten en silencio — son módulos
     * aún no importados del BOE, no hay nada real que ofrecerle a la IA.
     *
     * @param Collection<int, \App\Models\Modulo> $modulos
     * @return array{0: array<int, array{ra: \App\Models\ResultadoAprendizaje, modulo: string}>, 1: string, 2: bool}
     */
    public function construirIndiceYTexto(Collection $modulos): array
    {
        $raIndex = [];
        foreach ($modulos as $modulo) {
            foreach ($modulo->ras as $ra) {
                if ($ra->criteriosEvaluacion->isEmpty()) continue;
                $raIndex[$ra->id] = ['ra' => $ra, 'modulo' => $modulo->nombre];
            }
        }
        $hayCurriculumDisponible = !empty($raIndex);

        $curriculumTexto = '';
        $moduloActual = null;
        foreach ($raIndex as $raId => $entry) {
            if ($entry['modulo'] !== $moduloActual) {
                $curriculumTexto .= "\n[MÓDULO]: {$entry['modulo']}\n";
                $moduloActual = $entry['modulo'];
            }
            $curriculumTexto .= "  - [RA id={$raId}]: {$entry['ra']->ra}\n";
            foreach ($entry['ra']->criteriosEvaluacion as $ce) {
                $curriculumTexto .= "    * [CE id={$ce->id}]: {$ce->ce}\n";
            }
        }
        if (!$hayCurriculumDisponible) {
            $curriculumTexto = "\n(No hay RA/CE cargados todavía en la base de datos para estos módulos.)\n";
        }

        return [$raIndex, $curriculumTexto, $hayCurriculumDisponible];
    }

    /**
     * Reconstruye una selección de RA/CE a partir de los ra_id/ce_ids que devuelve
     * la IA, usando SIEMPRE el texto real de BD — nunca el que la IA pudiera haber
     * escrito. Cualquier id que no exista en el currículo proporcionado (alucinado)
     * se descarta en vez de guardarse.
     *
     * @param mixed $itemsCrudos
     * @param array<int, array{ra: \App\Models\ResultadoAprendizaje, modulo: string}> $raIndex
     * @return array<int, array{modulo:string, ra_id:int, ra:string, ce_ids:array, ce:array, aplicacion:string}>
     */
    public function resolver($itemsCrudos, array $raIndex): array
    {
        if (!is_array($itemsCrudos)) return [];

        $resueltos = [];
        foreach ($itemsCrudos as $item) {
            $raId = $item['ra_id'] ?? null;
            if (!$raId || !isset($raIndex[$raId])) continue;

            $ra               = $raIndex[$raId]['ra'];
            $moduloNombre     = $raIndex[$raId]['modulo'];
            $ceIdsSolicitados = is_array($item['ce_ids'] ?? null) ? $item['ce_ids'] : [];

            $ceSeleccionados = $ra->criteriosEvaluacion
                ->whereIn('id', $ceIdsSolicitados)
                ->values();

            if ($ceSeleccionados->isEmpty()) continue;

            $resueltos[] = [
                'modulo'     => $moduloNombre,
                'ra_id'      => $ra->id,
                'ra'         => $ra->ra,
                'ce_ids'     => $ceSeleccionados->pluck('id')->values()->all(),
                'ce'         => $ceSeleccionados->pluck('ce')->values()->all(),
                'aplicacion' => is_string($item['aplicacion'] ?? null) ? $item['aplicacion'] : '',
            ];
        }
        return $resueltos;
    }

    /**
     * Serializa una selección ya resuelta al formato de texto plano
     * "[Módulo]\nRA: ...\nCE:\n  • ..." usado como `ra_ce` en microproyectos.
     */
    public function serializarATexto(array $seleccionResuelta): string
    {
        return collect($seleccionResuelta)->map(function ($item) {
            $ces = collect($item['ce'])->map(fn ($c) => "  • {$c}")->join("\n");
            return "[{$item['modulo']}]\nRA: {$item['ra']}\nCE:\n{$ces}";
        })->join("\n\n");
    }

    /**
     * Parsea el formato de texto legacy de `ra_ce` de vuelta a la misma forma que
     * `resolver()` — sin ids (ra_id null, ce_ids vacío), ya que el texto libre nunca
     * los conservó. Se usa como fallback de lectura para proyectos creados antes de
     * la columna `evaluacion_oficial`, hasta que se resuelvan sus ids reales (con la
     * IA, el catálogo manual o un futuro comando de backfill).
     *
     * @return array<int, array{modulo:string, ra_id:null, ra:string, ce_ids:array, ce:array, aplicacion:string}>
     */
    public function parsearTextoLegacy(?string $texto): array
    {
        $texto = trim((string) $texto);
        if ($texto === '') return [];

        $resultado = [];
        foreach (preg_split('/\n{2,}/', $texto) as $bloque) {
            $lineas = explode("\n", trim($bloque));
            if (!preg_match('/^\[(.+)\]$/u', $lineas[0] ?? '', $cabecera)) continue;

            $ra  = '';
            $ces = [];
            foreach (array_slice($lineas, 1) as $linea) {
                $linea = trim($linea);
                if (str_starts_with($linea, 'RA:')) {
                    $ra = trim(substr($linea, 3));
                } elseif (preg_match('/^•\s*(.*)$/u', $linea, $criterio)) {
                    $ces[] = trim($criterio[1]);
                }
            }
            if ($ra === '' && empty($ces)) continue;

            $resultado[] = [
                'modulo'     => trim($cabecera[1]),
                'ra_id'      => null,
                'ra'         => $ra,
                'ce_ids'     => [],
                'ce'         => $ces,
                'aplicacion' => '',
            ];
        }
        return $resultado;
    }
}
