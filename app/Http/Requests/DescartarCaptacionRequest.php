<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// POST /captacion/empresas/{id}/descartar — no se usa (spam, duplicada, no sigue adelante).
// Rol: middleware 'superadmin'.
class DescartarCaptacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'motivo' => 'nullable|string|max:500',
        ];
    }
}
