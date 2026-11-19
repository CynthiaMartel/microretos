<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /central/empresas — filtros de Red DuaLab → Empresas (solo lectura) */
class ListarEmpresasCentralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Red DuaLab: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'buscar'     => 'sometimes|nullable|string|max:100',
            'centro_id'  => 'sometimes|nullable|integer|exists:centro_educativo,id',
            'familia_id' => 'sometimes|nullable|integer|exists:familias,id',
            'tipo'       => 'sometimes|nullable|in:real,ficticia',
            'page'       => 'sometimes|integer|min:1',
            'per_page'   => 'sometimes|integer|min:1|max:50',
        ];
    }
}
