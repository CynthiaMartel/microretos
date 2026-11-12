<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// PUT /captacion/empresas/{id}/validar — el superadmin valida la captación y elige los centros
// en los que queda la empresa (reemplaza la lista si ya estaba validada). empresa_existente_id:
// es la misma que una empresa real que ya está en Base de datos (se fusiona, no se duplica).
// Rol: middleware 'superadmin'.
class ValidarEmpresaCaptacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'centro_ids'   => 'required|array|min:1|max:50',
            'centro_ids.*' => 'integer|distinct|exists:centro_educativo,id,deleted_at,NULL',
            'empresa_existente_id' => 'nullable|integer|exists:empresas,id,deleted_at,NULL',
        ];
    }
}
