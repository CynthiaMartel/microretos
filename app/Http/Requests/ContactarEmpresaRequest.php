<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactarEmpresaRequest extends FormRequest
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
            'remitente' => 'required|email|max:255',
            'asunto'    => 'required|string|max:200',
            'mensaje'   => 'required|string|max:5000',
        ];
    }
}
