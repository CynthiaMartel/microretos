<?php

namespace App\Http\Requests;

use App\Mail\SoporteAvisoMail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// "Contacta con soporte" de la pantalla de acceso (pública: quien escribe puede no
// tener sesión, p. ej. porque no consigue entrar)
class StoreSoporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre'  => 'required|string|max:120',
            'email'   => 'required|email|max:255',
            'tipo'    => ['required', Rule::in(array_keys(SoporteAvisoMail::TIPOS))],
            'mensaje' => 'required|string|min:10|max:3000',
            // Honeypot: campo oculto que solo rellenan los bots (ver SoporteController)
            'website' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.min' => 'Cuéntanos un poco más (mínimo 10 caracteres).',
        ];
    }
}
