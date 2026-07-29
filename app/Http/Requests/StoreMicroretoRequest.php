<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMicroretoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $textFields = [
            'empresa_nombre', 'titulo', 'subtitulo', 'quien_es', 'dia_a_dia',
            'pregunta_reto', 'ciclo', 'modulo', 'duracion', 'nivel_grupo',
        ];

        $sanitized = [];
        foreach ($textFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $sanitized[$field] = strip_tags($this->input($field));
            }
        }

        foreach (['dificultades', 'que_necesitan', 'limitaciones', 'prototipos',
                  'ods_sugeridos', 'soft_skills', 'evaluacion_oficial', 'tips_profesorado', 'variantes'] as $field) {
            if ($this->has($field) && is_array($this->input($field))) {
                $sanitized[$field] = $this->sanitizeRecursively($this->input($field));
            }
        }

        if ($sanitized) {
            $this->merge($sanitized);
        }
    }

    /**
     * evaluacion_oficial contiene objetos anidados (modulo, ra, ce[], aplicacion),
     * a diferencia del resto de campos array que son listas planas de strings.
     */
    private function sanitizeRecursively(mixed $value): mixed
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizeRecursively($item), $value);
        }

        return $value;
    }

    public function rules(): array
    {
        return [
            'demo_id'              => 'nullable|integer|exists:demos,id',
            'empresa_id'           => 'nullable|integer|exists:empresas,id',
            'empresa_nombre'       => 'nullable|string|max:255',
            'titulo'               => 'nullable|string|max:500',
            'subtitulo'            => 'nullable|string|max:500',
            'quien_es'             => 'nullable|string|max:5000',
            'dia_a_dia'            => 'nullable|string|max:5000',
            'pregunta_reto'        => 'nullable|string|max:5000',
            'dificultades'         => 'nullable|array',
            'dificultades.*'       => 'nullable|string|max:1000',
            'que_necesitan'        => 'nullable|array',
            'que_necesitan.*'      => 'nullable|string|max:1000',
            'limitaciones'         => 'nullable|array',
            'limitaciones.*'       => 'nullable|string|max:1000',
            'prototipos'           => 'nullable|array',
            'prototipos.*'         => 'nullable|string|max:1000',
            'ods_sugeridos'        => 'nullable|array',
            'ods_sugeridos.*'      => 'nullable|string|max:255',
            'soft_skills'          => 'nullable|array',
            'soft_skills.*'        => 'nullable|string|max:255',
            'evaluacion_oficial'   => 'nullable|array',
            'evaluacion_oficial.*.modulo'     => 'nullable|string|max:255',
            'evaluacion_oficial.*.ra_id'      => 'nullable|integer|exists:resultados_aprendizaje,id',
            'evaluacion_oficial.*.ra'         => 'nullable|string|max:2000',
            'evaluacion_oficial.*.ce_ids'     => 'nullable|array',
            'evaluacion_oficial.*.ce_ids.*'   => 'integer|exists:criterios_evaluacion,id',
            'evaluacion_oficial.*.ce'         => 'nullable|array',
            'evaluacion_oficial.*.ce.*'       => 'nullable|string|max:1000',
            'evaluacion_oficial.*.aplicacion' => 'nullable|string|max:1000',
            'tips_profesorado'     => 'nullable|array',
            'tips_profesorado.*'   => 'nullable|string|max:2000',
            'variantes'            => 'nullable|array',
            'variantes.*'          => 'nullable|string|max:2000',
            'nivel_grupo'          => 'nullable|string|max:100',
            'curso'                => 'nullable|integer',
            'ciclo_id'             => 'nullable|integer|exists:ciclos_formativos,id',
            'ciclo'                => 'nullable|string|max:255',
            'modulo'               => 'nullable|string|max:255',
            'duracion'             => 'nullable|string|max:100',
            'es_simulado'          => 'nullable|boolean',
        ];
    }
}
