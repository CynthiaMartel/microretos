<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Interés de la empresa en UNA familia ya vinculada (para qué quiere a ese alumnado), desde
// el paso 3 del Generador. Qué empresas se pueden tocar lo decide EmpresaPolicy::actualizarDiagnostico
// en el controlador: es información que da la empresa, como el diagnóstico.
class UpdateInteresEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->interes)) {
            $this->merge(['interes' => trim(strip_tags($this->interes))]);
        }
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'familia' => 'required|string|max:255',
            // Vacío = quitar el interés de esa familia
            'interes' => 'present|nullable|string|max:500',
        ];
    }
}
