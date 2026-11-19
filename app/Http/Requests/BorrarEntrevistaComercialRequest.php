<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DELETE /comercial/empresas/{id} — mover una entrevista en curso a la papelera. Como en Base de
 * datos, se pide la contraseña de quien borra; aquí la comprueba el backend en la misma petición.
 * La captación y quién puede borrarla los comprueba el controller (Policy).
 */
class BorrarEntrevistaComercialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:128', 'current_password:sanctum'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required'         => 'Escribe tu contraseña para confirmar.',
            'password.current_password' => 'La contraseña no es correcta.',
        ];
    }
}
