<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimularInfoEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización de rol (docente/admin/superadmin) ya la resuelve
        // el middleware 'docente' en routes/api.php.
        return true;
    }

    public function rules(): array
    {
        return [
            'empresaNombre'    => 'required|string',
            'empresaSector'    => 'required|string',
            'empresaTamano'    => 'nullable|string',
            'empresaUbicacion' => 'nullable|string',
        ];
    }
}
