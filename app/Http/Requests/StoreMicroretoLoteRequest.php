<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Mismas reglas que StoreMicroretoRequest, aplicadas a cada elemento de `microretos`.
// El saneado (strip_tags) se hace en MicroretoIAController::guardarLote tras validar.
class StoreMicroretoLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'microretos'                        => 'required|array|max:50',
            'microretos.*.demo_id'              => 'nullable|integer|exists:demos,id',
            'microretos.*.empresa_id'           => 'nullable|integer|exists:empresas,id',
            'microretos.*.empresa_nombre'       => 'nullable|string|max:255',
            'microretos.*.titulo'               => 'nullable|string|max:500',
            'microretos.*.subtitulo'            => 'nullable|string|max:500',
            'microretos.*.quien_es'             => 'nullable|string|max:5000',
            'microretos.*.dia_a_dia'            => 'nullable|string|max:5000',
            'microretos.*.pregunta_reto'        => 'nullable|string|max:5000',
            'microretos.*.dificultades'         => 'nullable|array',
            'microretos.*.dificultades.*'       => 'nullable|string|max:1000',
            'microretos.*.que_necesitan'        => 'nullable|array',
            'microretos.*.que_necesitan.*'      => 'nullable|string|max:1000',
            'microretos.*.limitaciones'         => 'nullable|array',
            'microretos.*.limitaciones.*'       => 'nullable|string|max:1000',
            'microretos.*.prototipos'           => 'nullable|array',
            'microretos.*.prototipos.*'         => 'nullable|string|max:1000',
            'microretos.*.ods_sugeridos'        => 'nullable|array',
            'microretos.*.ods_sugeridos.*'      => 'nullable|string|max:255',
            'microretos.*.soft_skills'          => 'nullable|array',
            'microretos.*.soft_skills.*'        => 'nullable|string|max:255',
            'microretos.*.evaluacion_oficial'              => 'nullable|array',
            'microretos.*.evaluacion_oficial.*.modulo'     => 'nullable|string|max:255',
            'microretos.*.evaluacion_oficial.*.ra_id'      => 'nullable|integer|exists:resultados_aprendizaje,id',
            'microretos.*.evaluacion_oficial.*.ra'         => 'nullable|string|max:2000',
            'microretos.*.evaluacion_oficial.*.ce_ids'     => 'nullable|array',
            'microretos.*.evaluacion_oficial.*.ce_ids.*'   => 'integer|exists:criterios_evaluacion,id',
            'microretos.*.evaluacion_oficial.*.ce'         => 'nullable|array',
            'microretos.*.evaluacion_oficial.*.ce.*'       => 'nullable|string|max:1000',
            'microretos.*.evaluacion_oficial.*.aplicacion' => 'nullable|string|max:1000',
            'microretos.*.tips_profesorado'     => 'nullable|array',
            'microretos.*.tips_profesorado.*'   => 'nullable|string|max:2000',
            'microretos.*.variantes'            => 'nullable|array',
            'microretos.*.variantes.*'          => 'nullable|string|max:2000',
            'microretos.*.nivel_grupo'          => 'nullable|string|max:100',
            'microretos.*.curso'                => 'nullable|in:1,2,ambos_cursos', // columna varchar: 1, 2 o 'ambos_cursos'
            'microretos.*.ciclo_id'             => 'nullable|integer|exists:ciclos_formativos,id',
            'microretos.*.ciclo'                => 'nullable|string|max:255',
            'microretos.*.modulo'               => 'nullable|string|max:255',
            'microretos.*.multimodulo'          => 'nullable|boolean',
            'microretos.*.ra_ce_origen'         => 'nullable|in:ia,docente,mixto',
            'microretos.*.ra_ce_firma'          => 'nullable|string|size:64',
            // Diagnóstico con el que se generó (si difería del guardado) + su firma de generar()
            'microretos.*.diagnostico_usado'   => 'nullable|array',
            'microretos.*.diagnostico_usado.*' => 'nullable|string|max:2000',
            'microretos.*.diagnostico_firma'   => 'nullable|string|size:64',
            'microretos.*.duracion'             => 'nullable|string|max:100',
            'microretos.*.es_simulado'          => 'nullable|boolean',
        ];
    }
}
