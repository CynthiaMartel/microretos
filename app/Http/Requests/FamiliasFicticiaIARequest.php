<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /empresas/ficticia-ia/familias — familias con las que se puede crear una empresa
 * ficticia con IA para un destino. Mismo criterio de destino que CrearEmpresaFicticiaIARequest:
 * docente/admin, siempre su centro; superadmin, el centro que indique o el catálogo DuaLab.
 */
class FamiliasFicticiaIARequest extends FormRequest
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
            'centro'   => $this->user()?->isSuperAdmin()
                ? 'nullable|string|max:255|exists:centro_educativo,nombre'
                : 'exclude',
            'catalogo' => $this->user()?->isSuperAdmin() ? 'sometimes|boolean' : 'exclude',
        ];
    }
}
