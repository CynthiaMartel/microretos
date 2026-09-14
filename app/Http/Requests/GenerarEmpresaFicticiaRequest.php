<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerarEmpresaFicticiaRequest extends FormRequest
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
            'centroId'  => 'required|integer|exists:centro_educativo,id',
            'familiaId' => 'required|integer|exists:familias,id',
        ];
    }
}
