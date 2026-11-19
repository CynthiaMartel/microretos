<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /central/centros — filtros de Red DuaLab → Centros (solo lectura) */
class ListarCentrosCentralRequest extends FormRequest
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
            'page'       => 'sometimes|integer|min:1',
            'per_page'   => 'sometimes|integer|min:1|max:50',
        ];
    }
}
