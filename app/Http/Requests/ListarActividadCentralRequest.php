<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /central/historial — filtros del historial de Central */
class ListarActividadCentralRequest extends FormRequest
{
    public const SECCIONES = ['captaciones', 'papelera', 'usuarios', 'solicitudes', 'retos', 'correo'];

    public function authorize(): bool
    {
        return true; // superadmin y admin_central_dualab: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'seccion'  => 'sometimes|nullable|in:' . implode(',', self::SECCIONES),
            'criticas' => 'sometimes|boolean',
            'buscar'   => 'sometimes|nullable|string|max:100',
            'page'     => 'sometimes|integer|min:1',
        ];
    }
}
