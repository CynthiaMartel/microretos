<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnviarValidacionEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pertenencia de la empresa al centro: la comprueba el controller (autorizarEmpresa)
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'remitente'     => 'required|email|max:255',
            'proyecto_uuid' => 'required|string|exists:microproyectos,uuid',
            'mensaje'       => 'nullable|string|max:2000',
        ];
    }
}
