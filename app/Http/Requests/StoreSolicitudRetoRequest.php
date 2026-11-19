<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /central/solicitudes-reto — DuaLab crea una solicitud de retos y se la envía al docente.
 * El rol (red_dualab, central_dualab o superadmin) lo comprueba el middleware 'primer.contacto'.
 */
class StoreSolicitudRetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre_docente' => 'required|string|max:150',
            'email_docente'  => 'required|email:rfc|max:255',
            'centro_nombre'  => 'required|string|max:255',
            // Si el centro ya está dado de alta
            'centro_id'      => 'sometimes|nullable|integer|exists:centro_educativo,id',
            'notas'          => 'sometimes|nullable|string|max:2000',
        ];
    }
}
