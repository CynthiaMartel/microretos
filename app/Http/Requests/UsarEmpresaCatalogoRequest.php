<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UsarEmpresaCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rol (docente/admin/superadmin) lo resuelve el middleware 'docente' de la ruta.
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        // Solo superadmin elige el centro de la copia; docente/admin, siempre el suyo.
        return [
            'centro' => $this->user()?->isSuperAdmin()
                ? 'required|string|max:255|exists:centro_educativo,nombre'
                : 'exclude',
        ];
    }
}
