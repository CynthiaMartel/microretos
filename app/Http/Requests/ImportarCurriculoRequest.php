<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /ciclos/{id}/curriculo/importar — CSV con los módulos, RA y CE de un ciclo
 * (una fila por criterio de evaluación). Con `simular` solo se valida y se devuelve
 * el resumen, sin escribir nada.
 */
class ImportarCurriculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está restringida por el middleware 'superadmin'.
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:csv,txt|max:4096',
            'simular' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.mimes' => 'El archivo debe ser un CSV (en Excel: «Guardar como» → CSV UTF-8).',
            'archivo.max'   => 'El archivo no puede superar los 4 MB.',
        ];
    }
}
