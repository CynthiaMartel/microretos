<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// PUT /captacion/empresas/{id}/centros — centros en los que el superadmin coloca una empresa
// captada (reemplaza la lista). Rol: middleware 'superadmin'.
class AsignarCentrosEmpresaRequest extends FormRequest
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
        ];
    }
}
