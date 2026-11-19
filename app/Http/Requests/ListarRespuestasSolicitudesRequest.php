<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /central/solicitudes-reto/correos — filtros de «Respuestas de solicitudes» (los de la API de la web) */
class ListarRespuestasSolicitudesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // acceso por el middleware de la ruta
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado'   => ['nullable', Rule::in(ListarCorreosWebRequest::ESTADOS)],
            'page'     => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
