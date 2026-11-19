<?php

namespace App\Http\Requests;

use App\Models\SolicitudReto;
use Illuminate\Foundation\Http\FormRequest;

/** GET /central/solicitudes-reto — filtros de la bandeja */
class ListarSolicitudesRetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado'   => 'sometimes|nullable|in:' . implode(',', [
                SolicitudReto::ESTADO_PENDIENTE, SolicitudReto::ESTADO_RESPONDIDA, SolicitudReto::ESTADO_DESCARTADA,
            ]),
            'buscar'   => 'sometimes|nullable|string|max:100',
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ];
    }
}
