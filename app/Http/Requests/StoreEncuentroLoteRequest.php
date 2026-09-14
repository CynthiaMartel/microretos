<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Valida POST /encuentros/lote, usado solo por la migración silenciosa de
// DashboardDocente.vue que sube encuentros heredados de localStorage (clave
// legacy "dualab_sesiones", de cuando los encuentros se llamaban "sesiones").
// No lo llama el formulario de creación — no es código muerto ni una feature
// especulativa: mientras exista algún docente con esa clave sin migrar, sigue
// siendo necesario.
class StoreEncuentroLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $encuentros = $this->input('encuentros');
        if (!is_array($encuentros)) {
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
        }, $encuentros);

        $this->merge(['encuentros' => $sanitized]);
    }

    public function rules(): array
    {
        return [
            'encuentros'                    => 'required|array',
            'encuentros.*.fecha'            => 'required|date',
            'encuentros.*.centro_educativo' => 'nullable|string|max:255',
            'encuentros.*.ciclo_formativo'  => 'nullable|string|max:255',
            'encuentros.*.curso'            => 'nullable|string|max:10',
            'encuentros.*.grupo'            => 'nullable|string|max:10',
            'encuentros.*.num_alumnos'      => 'nullable|integer|min:1|max:999',
            'encuentros.*.notas'            => 'nullable|string|max:5000',
        ];
    }
}
