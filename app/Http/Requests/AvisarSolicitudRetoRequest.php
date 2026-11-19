<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /central/solicitudes-reto/{id}/avisar — DuaLab decide qué retos enviar al docente. Que sean
 * de esta solicitud lo comprueba SolicitudRetoService::avisar.
 */
class AvisarSolicitudRetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
