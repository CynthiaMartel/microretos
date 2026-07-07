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

        if ($sanitized) {
            $this->merge($sanitized);
        }
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
