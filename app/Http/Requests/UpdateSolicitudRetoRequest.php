<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /central/solicitudes-reto/{id} — DuaLab asigna la empresa (de toda la solicitud o, con
 * `bloque`, de una petición) y el centro, anota y descarta o recupera la solicitud. Las respuestas
 * del docente no se editan aquí: se ajustan en el generador.
 */
class UpdateSolicitudRetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'empresa_id' => 'sometimes|nullable|integer|exists:empresas,id,deleted_at,NULL',
            'bloque'     => 'sometimes|integer|min:0|max:' . (EnviarSolicitudRetoRequest::MAX_BLOQUES - 1),
            'centro_id'  => 'sometimes|nullable|integer|exists:centro_educativo,id',
            'notas'      => 'sometimes|nullable|string|max:2000',
            'descartada' => 'sometimes|boolean',
        ];
    }
}
