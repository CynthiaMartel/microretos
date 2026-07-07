<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSesionLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $sesiones = $this->input('sesiones');
        if (!is_array($sesiones)) {
            return;
        }

        $textFields = ['centro_educativo', 'ciclo_formativo', 'curso', 'grupo', 'notas'];

        $sanitized = array_map(function (mixed $s) use ($textFields) {
            if (!is_array($s)) return $s;
            foreach ($textFields as $field) {
                if (isset($s[$field]) && is_string($s[$field])) {
                    $s[$field] = strip_tags($s[$field]);
                }
            }
            return $s;
        }, $sesiones);

        $this->merge(['sesiones' => $sanitized]);
    }

    public function rules(): array
    {
        return [
            'sesiones'                    => 'required|array',
            'sesiones.*.fecha'            => 'required|date',
            'sesiones.*.centro_educativo' => 'nullable|string|max:255',
            'sesiones.*.ciclo_formativo'  => 'nullable|string|max:255',
            'sesiones.*.curso'            => 'nullable|string|max:10',
            'sesiones.*.grupo'            => 'nullable|string|max:10',
            'sesiones.*.num_alumnos'      => 'nullable|integer|min:1|max:999',
            'sesiones.*.notas'            => 'nullable|string|max:5000',
        ];
    }
}
