<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /comercial/empresas/{id}/formulario — el comercial envía a la empresa el enlace del
 * formulario. La captación y quién puede hacerlo los comprueba el controller (Policy).
 */
class EnviarFormularioComercialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'email' => 'required|email:rfc|max:255',
        ];
    }
}
