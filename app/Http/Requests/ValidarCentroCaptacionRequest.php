<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// PUT /captacion/centros/{id}/validar — alta del centro en DuaLab o, si ya existía, enlace con
// ese centro (centro_existente_id). Rol: middleware 'superadmin'.
class ValidarCentroCaptacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'centro_existente_id' => 'nullable|integer|exists:centro_educativo,id,deleted_at,NULL',
        ];
    }
}
