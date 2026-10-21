<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Misma política de contraseña que UpdatePerfilRequest (/mi-usuario), pero obligatoria
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token'    => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => [
                'required',
                'string',
                'max:128',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'password_confirmation' => 'required|string',
        ];
    }
}
