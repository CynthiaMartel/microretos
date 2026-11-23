<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /central/solicitudes-reto/{id}/autorizar — DuaLab autoriza al docente (con cuenta) a trabajar
 * los retos elegidos. Que sean de esta solicitud y que tenga cuenta lo comprueba
 * SolicitudRetoService::autorizar.
 */
class AutorizarRetosSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // superadmin y admin_central_dualab: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'retos'   => 'required|array|min:1|max:20',
            'retos.*' => 'required|uuid|distinct',
            // Unas líneas para el docente, opcionales
            'mensaje' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
