<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /central/solicitudes-reto/{id}/publicar — pasa retos de la recámara de la solicitud a la
 * biblioteca de un centro. Que sean de la solicitud lo comprueba SolicitudRetoService::publicarRetos.
 */
class PublicarRetosSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // superadmin y admin_central_dualab: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'retos'     => 'required|array|min:1|max:20',
            'retos.*'   => 'required|uuid|distinct',
            'centro_id' => 'required|integer|exists:centro_educativo,id',
        ];
    }
}
