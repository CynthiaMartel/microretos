<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** GET /central/solicitudes-reto/{id}/generador?bloque=N — qué petición de la solicitud se lleva al generador */
class DatosGeneradorSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // superadmin: middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['bloque' => 'sometimes|integer|min:0|max:' . EnviarSolicitudRetoRequest::MAX_BLOQUES];
    }
}
