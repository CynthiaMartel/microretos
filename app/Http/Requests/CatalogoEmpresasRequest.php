<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /empresas/catalogo — filtros del catálogo DuaLab. El centro solo lo elige superadmin
 * (el que tenga seleccionado en el generador); docente/admin usan siempre el suyo.
 */
class CatalogoEmpresasRequest extends FormRequest
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
            'familia_id' => 'nullable|integer|exists:familias,id,deleted_at,NULL',
            'centro'     => $this->user()?->isSuperAdmin()
                ? 'nullable|string|max:255|exists:centro_educativo,nombre'
                : 'exclude',
        ];
    }
}
