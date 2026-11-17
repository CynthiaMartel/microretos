<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros del listado de DuaLab Central → Alumnado destacado (GET /central/destacados).
 * El acceso (solo superadmin) lo garantiza el middleware de la ruta.
 */
class ListarDestacadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'buscar'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'solo_valorados' => ['sometimes', 'boolean'],
            'solo_destacados' => ['sometimes', 'boolean'],
        ];
    }
}
