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
     * Con $seleccionFijada (salida de resolverSeleccionDocente), los módulos que el
     * docente ha fijado solo exponen sus RA/CE elegidos, marcados como fijados — así
     * la IA no puede "colar" otros RA de esos módulos. Los módulos sin fijar se
     * exponen completos, como siempre.
     *
     * @param Collection<int, \App\Models\Modulo> $modulos
     * @param array{fijadas?: array<int, array<string, mixed>>, modulo_ids?: array<int>} $seleccionFijada
     * @return array{0: array<int, array{ra: \App\Models\ResultadoAprendizaje, modulo: string}>, 1: string, 2: bool}
     */
    public function construirIndiceYTexto(Collection $modulos, array $seleccionFijada = []): array
    {
        $ceFijadosPorRa   = collect($seleccionFijada['fijadas'] ?? [])->mapWithKeys(fn ($item) => [$item['ra_id'] => $item['ce_ids']])->all();
        $modulosFijados   = $seleccionFijada['modulo_ids'] ?? [];

        $raIndex = [];
        foreach ($modulos as $modulo) {
            $esModuloFijado = in_array($modulo->id, $modulosFijados, true);
            foreach ($modulo->ras as $ra) {
                if ($ra->criteriosEvaluacion->isEmpty()) continue;
                if ($esModuloFijado && !isset($ceFijadosPorRa[$ra->id])) continue;
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
            $esFijado = isset($ceFijadosPorRa[$raId]);
            $curriculumTexto .= "  - [RA id={$raId}]" . ($esFijado ? ' (FIJADO POR EL DOCENTE)' : '') . ": {$entry['ra']->ra}\n";
            $ces = $esFijado
                ? $entry['ra']->criteriosEvaluacion->whereIn('id', $ceFijadosPorRa[$raId])
                : $entry['ra']->criteriosEvaluacion;
            foreach ($ces as $ce) {
                $curriculumTexto .= "    * [CE id={$ce->id}]: {$ce->ce}\n";
            }
        }
        if (!$hayCurriculumDisponible) {
            $curriculumTexto = "\n(No hay RA/CE cargados todavía en la base de datos para estos módulos.)\n";
        }

        return [$raIndex, $curriculumTexto, $hayCurriculumDisponible];
    }

    /**
     * Valida y resuelve los RA/CE que el docente ha fijado a mano contra los módulos
     * forzados ya cargados: cada RA debe pertenecer a uno de esos módulos (y tener CE
     * cargados) y cada CE debe pertenecer a su RA. A diferencia de resolver(), aquí
     * no se descarta en silencio — un id ajeno es una petición manipulada o desfasada,
     * y se rechaza entera (null) para que el docente sepa que su selección no se aplicó.
     *
     * @param Collection<int, \App\Models\Modulo> $modulos
     * @param array<int, array<string, mixed>> $seleccion  validada por GenerarMicroretoRequest ({ra_id, ce_ids[]})
     * @return array{fijadas: array<int, array{modulo:string, ra_id:int, ra:string, ce_ids:array<int, int>, ce:array<int, string>, aplicacion:string}>, modulo_ids: array<int>}|null
     */
    public function resolverSeleccionDocente(Collection $modulos, array $seleccion): ?array
    {
        $raDisponibles = [];
        foreach ($modulos as $modulo) {
            foreach ($modulo->ras as $ra) {
                if ($ra->criteriosEvaluacion->isEmpty()) continue;
                $raDisponibles[$ra->id] = ['ra' => $ra, 'modulo' => $modulo];
            }
        }

        $fijadas    = [];
        $moduloIds  = [];
        foreach ($seleccion as $item) {
            $raId  = (int) ($item['ra_id'] ?? 0);
            $ceIds = array_map('intval', is_array($item['ce_ids'] ?? null) ? $item['ce_ids'] : []);
            if (!isset($raDisponibles[$raId]) || empty($ceIds)) return null;

            $ra  = $raDisponibles[$raId]['ra'];
            $ces = $ra->criteriosEvaluacion->whereIn('id', $ceIds)->values();
            if ($ces->count() !== count(array_unique($ceIds))) return null;

            $fijadas[] = [
                'modulo'     => $raDisponibles[$raId]['modulo']->nombre,
                'ra_id'      => $ra->id,
                'ra'         => $ra->ra,
                'ce_ids'     => $ces->pluck('id')->values()->all(),
                'ce'         => $ces->pluck('ce')->values()->all(),
                'aplicacion' => '',
            ];
            $moduloIds[] = $raDisponibles[$raId]['modulo']->id;
        }

        return ['fijadas' => $fijadas, 'modulo_ids' => array_values(array_unique($moduloIds))];
    }

    /**
     * Garantiza que los RA/CE fijados por el docente aparecen SIEMPRE en el reto,
     * tal cual los eligió, independientemente de lo que haya devuelto la IA: de la
     * respuesta de la IA solo se toma la `aplicacion` de cada RA fijado, y se añaden
     * detrás sus entradas de módulos no fijados (modo mixto). Devuelve también si
     * a algún RA fijado le falta la `aplicacion`, para avisar al docente.
     *
     * La `aplicacion` se busca en la respuesta cruda de la IA, no en la ya resuelta:
     * si la IA copió mal los ce_ids de un RA fijado, resolver() descarta esa entrada,
     * pero su `aplicacion` sigue siendo aprovechable porque los CE se imponen aquí.
     *
     * @param array<int, array<string, mixed>> $resueltasIA salida de resolver() sobre la respuesta de la IA
     * @param array<int, array<string, mixed>> $fijadas     clave `fijadas` de resolverSeleccionDocente()
     * @param mixed $itemsCrudos evaluacion_oficial tal cual la devolvió la IA
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    public function fusionarConFijadas(array $resueltasIA, array $fijadas, $itemsCrudos = []): array
    {
        $aplicacionPorRa = collect(is_array($itemsCrudos) ? $itemsCrudos : [])
            ->filter(fn ($item) => is_array($item) && isset($item['ra_id']) && is_string($item['aplicacion'] ?? null) && trim($item['aplicacion']) !== '')
            ->mapWithKeys(fn ($item) => [(int) $item['ra_id'] => $item['aplicacion']])
            ->all();
        $raFijados = collect($fijadas)->pluck('ra_id')->all();

        $faltaAplicacion = false;
        $final = [];
        foreach ($fijadas as $item) {
            $item['aplicacion'] = $aplicacionPorRa[$item['ra_id']] ?? '';
            if ($item['aplicacion'] === '') $faltaAplicacion = true;
            $final[] = $item;
        }
        foreach ($resueltasIA as $item) {
            if (!in_array($item['ra_id'], $raFijados, true)) $final[] = $item;
        }

        return [$final, $faltaAplicacion];
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
     * Firma (HMAC con APP_KEY) quién eligió los RA/CE de un reto recién generado, ligada
     * al usuario y a los ra_id/ce_ids exactos. El reto viaja al frontend y vuelve al
     * guardar: sin firma, cualquiera podría guardar un reto marcado como 'docente'
     * cuyos RA/CE en realidad no eligió (o cambiarlos después y conservar la marca).
     *
     * @param array<int, array<string, mixed>> $evaluacion
     */
    public function firmarOrigen(string $origen, array $evaluacion, int $userId): string
    {
        return hash_hmac('sha256', $this->cadenaOrigen($origen, $evaluacion, $userId), (string) config('app.key'));
    }

    /** @param array<int, array<string, mixed>> $evaluacion */
    public function verificarOrigen(?string $origen, ?string $firma, array $evaluacion, int $userId): bool
    {
        if (!$origen || !$firma) return false;
        return hash_equals($this->firmarOrigen($origen, $evaluacion, $userId), $firma);
    }

    /** @param array<int, array<string, mixed>> $evaluacion */
    private function cadenaOrigen(string $origen, array $evaluacion, int $userId): string
    {
        $pares = [];
        foreach ($evaluacion as $item) {
            $ces = array_map('intval', is_array($item['ce_ids'] ?? null) ? $item['ce_ids'] : []);
            sort($ces);
            $pares[] = ((int) ($item['ra_id'] ?? 0)) . ':' . implode(',', $ces);
        }
        sort($pares);
        return "{$origen}#{$userId}#" . implode('|', $pares);
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
