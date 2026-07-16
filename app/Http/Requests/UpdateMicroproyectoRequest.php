<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMicroproyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $textFields = ['titulo', 'curso', 'ra_ce'];
        $sanitized  = [];

        foreach ($textFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $sanitized[$field] = strip_tags($this->input($field));
            }
        }

        if ($this->has('evaluacion_oficial') && is_array($this->input('evaluacion_oficial'))) {
            $sanitized['evaluacion_oficial'] = $this->sanitizeRecursively($this->input('evaluacion_oficial'));
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
            'titulo'               => 'sometimes|string|max:255',
            'curso'                => 'sometimes|nullable|string|max:100',
            'empresa_id'           => 'sometimes|nullable|exists:empresas,id',
            'centro_id'            => 'sometimes|nullable|exists:centro_educativo,id',
            'familia_id'           => 'sometimes|nullable|exists:familias,id',
            'ciclo_id'             => 'sometimes|nullable|exists:ciclos_formativos,id',
            'datos_empresa'        => 'sometimes|nullable|array',
            'datos_centro'         => 'sometimes|nullable|array',
            'equipo'               => 'sometimes|nullable|array',
            'modulos_seleccionados'=> 'sometimes|nullable|array',
            'ra_ce'                => 'sometimes|nullable|string|max:50000',
            'evaluacion_oficial'              => 'sometimes|nullable|array',
            'evaluacion_oficial.*.modulo'     => 'nullable|string|max:255',
            'evaluacion_oficial.*.ra_id'      => 'nullable|integer|exists:resultados_aprendizaje,id',
            'evaluacion_oficial.*.ra'         => 'nullable|string|max:2000',
            'evaluacion_oficial.*.ce_ids'     => 'nullable|array',
            'evaluacion_oficial.*.ce_ids.*'   => 'integer|exists:criterios_evaluacion,id',
            'evaluacion_oficial.*.ce'         => 'nullable|array',
            'evaluacion_oficial.*.ce.*'       => 'nullable|string|max:1000',
            'evaluacion_oficial.*.aplicacion' => 'nullable|string|max:1000',
            'fundamentacion'       => 'sometimes|nullable|array',
            'diseno_reto'          => 'sometimes|nullable|array',
            'diseno_microproyecto' => 'sometimes|nullable|array',
            'resumen'              => 'sometimes|nullable|array',
            'objetivos'            => 'sometimes|nullable|array',
            'kpis'                 => 'sometimes|nullable|array',
            'validacion_empresa'   => 'sometimes|nullable|array',
            'paso_actual'          => 'sometimes|integer|min:1|max:13',
            'estado'               => 'sometimes|in:en_edicion,propuesta,validado,archivado',
            'enviado_a_empresa_mail' => 'sometimes|boolean',
        ];
    }
}
