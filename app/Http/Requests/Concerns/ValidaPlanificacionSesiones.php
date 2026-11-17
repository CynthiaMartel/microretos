<?php

namespace App\Http\Requests\Concerns;

/**
 * planificacion_sesiones del encuentro: [{ fases: [0..4] }]. Lo comparten Store y
 * UpdateEncuentroRequest. Lista blanca: de cada sesión solo se conservan `fases` (sin repetidas) y
 * `fecha` (fecha real del hito; obligatoria en el cronograma de un plan, ver StoreCronogramaPlanRequest).
 */
trait ValidaPlanificacionSesiones
{
    /** @return array<string, mixed> Para fusionar en prepareForValidation (vacío si no llega) */
    protected function planificacionSesionesSaneada(): array
    {
        if (!$this->has('planificacion_sesiones') || !is_array($this->input('planificacion_sesiones'))) {
            return [];
        }

        return ['planificacion_sesiones' => array_values(array_map(
            function ($s) {
                if (!is_array($s)) return $s;
                $s = array_intersect_key($s, array_flip(['fases', 'fecha']));
                // Fases repetidas dentro de una misma sesión: se quitan (entre sesiones sí se repiten)
                if (isset($s['fases']) && is_array($s['fases'])) {
                    $s['fases'] = array_values(array_unique($s['fases'], SORT_REGULAR));
                }
                return $s;
            },
            $this->input('planificacion_sesiones'),
        ))];
    }

    /** @return array<string, mixed> */
    protected function reglasPlanificacionSesiones(): array
    {
        return [
            'planificacion_sesiones'            => 'sometimes|nullable|array|max:60',
            'planificacion_sesiones.*'          => 'array',
            'planificacion_sesiones.*.fases'    => 'required|array|min:1|max:5',
            // Sin 'distinct': compararía las fases de todas las sesiones entre sí, y dos sesiones
            // pueden trabajar la misma fase (los duplicados dentro de una sesión se quitan arriba)
            'planificacion_sesiones.*.fases.*'  => 'integer|between:0,4',
            'planificacion_sesiones.*.fecha'    => 'nullable|date_format:Y-m-d',
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPlanificacionSesiones(): array
    {
        return [
            'planificacion_sesiones.max'              => 'La planificación admite como máximo 60 sesiones.',
            'planificacion_sesiones.*.fases.required' => 'Cada sesión de la planificación debe trabajar al menos una fase.',
            'planificacion_sesiones.*.fases.min'      => 'Cada sesión de la planificación debe trabajar al menos una fase.',
        ];
    }
}
