<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearEmpresaFicticiaIARequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rol (docente/admin/superadmin) lo resuelve el middleware 'docente' de la ruta.
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'familiaId' => 'required|integer|exists:familias,id,deleted_at,NULL',
            // Solo superadmin elige centro; a docente/admin se les descarta el campo y el
            // controller usa siempre su propio centro (nunca se confía en el cliente).
            // Superadmin: centro, o 'catalogo' para crear una plantilla del catálogo DuaLab
            // (sin centro, compartida). La combinación la comprueba el controller.
            'centro'    => $this->user()?->isSuperAdmin()
                ? 'nullable|string|max:255|exists:centro_educativo,nombre'
                : 'exclude',
            'catalogo'  => $this->user()?->isSuperAdmin() ? 'sometimes|boolean' : 'exclude',
        ];
    }
}
