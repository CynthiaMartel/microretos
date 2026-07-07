<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $textFields = ['centro_educativo', 'ciclo_formativo', 'curso', 'grupo', 'notas'];

        $sanitized = [];
        foreach ($textFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $sanitized[$field] = strip_tags($this->input($field));
            }
        }

        if ($this->has('alumnados') && is_array($this->input('alumnados'))) {
            $sanitized['alumnados'] = collect($this->input('alumnados'))
                ->map(fn($a) => [
                    'nombre'     => isset($a['nombre'])     ? strip_tags($a['nombre'])     : '',
                    'equipo_num' => isset($a['equipo_num']) ? (int) $a['equipo_num']       : null,
                    'rol'        => isset($a['rol'])        ? strip_tags($a['rol'])        : null,
                ])
                ->filter(fn($a) => !empty($a['nombre']))
                ->values()
                ->toArray();
        }

        if ($sanitized) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        return [
            'microproyecto_id'       => 'nullable|integer|exists:microproyectos,id',
            'fecha'                  => 'required|date',
            'centro_educativo'       => 'nullable|string|max:255',
            'ciclo_formativo'        => 'nullable|string|max:255',
            'curso'                  => 'nullable|string|max:10',
            'grupo'                  => 'nullable|string|max:10',
            'num_alumnos'            => 'nullable|integer|min:1|max:999',
            'notas'                  => 'nullable|string|max:5000',
            'num_equipos'            => 'nullable|integer|min:1|max:30',
            'alumnados'              => 'nullable|array|max:200',
            'alumnados.*.nombre'     => 'required_with:alumnados|string|max:100',
            'alumnados.*.equipo_num' => 'nullable|integer|min:1|max:30',
            'alumnados.*.rol'        => 'nullable|string|max:50',
        ];
    }
}
