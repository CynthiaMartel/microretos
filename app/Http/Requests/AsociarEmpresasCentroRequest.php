<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsociarEmpresasCentroRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta ya está restringida por el middleware 'superadmin'.
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_ids'   => 'required|array|min:1',
            'empresa_ids.*' => 'integer|distinct|exists:empresas,id',
        ];
    }
}
