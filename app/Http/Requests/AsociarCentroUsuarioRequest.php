<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsociarCentroUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo superadmin: lo resuelve el middleware 'superadmin' en routes/api.php
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'centro_educativo_id' => 'nullable|integer|exists:centro_educativo,id',
        ];
    }
}
