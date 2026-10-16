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
            'empresaNombre'    => 'required|string|max:255',
            'empresaSector'    => 'required|string|max:255',
            'empresaTamano'    => 'nullable|string|max:50',
            'empresaUbicacion' => 'nullable|string|max:520', // municipio + ", " + provincia
        ];
    }
}
