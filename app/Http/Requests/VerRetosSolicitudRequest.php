<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /central/solicitudes-reto/{id}/retos — los retos marcados para la vista previa del docente.
 * Los que no son de la solicitud se ignoran (SolicitudRetoService::fichasRetos).
 */
class VerRetosSolicitudRequest extends FormRequest
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
        ];
    }
}
