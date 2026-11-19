<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /central/solicitudes-reto/{id}/generador|generacion|empresas?bloque=N — qué petición de la solicitud.
 * En «empresas», todas=1 devuelve empresas de cualquier familia (no solo la de la petición).
 */
class DatosGeneradorSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // superadmin: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bloque' => 'sometimes|integer|min:0|max:' . EnviarSolicitudRetoRequest::MAX_BLOQUES,
            'todas'  => 'sometimes|boolean',
        ];
    }
}
