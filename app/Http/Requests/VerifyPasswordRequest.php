<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// POST /admin/verify-password — confirmar la contraseña antes de una acción sensible
class VerifyPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => 'required|string|max:128',
        ];
    }
}
