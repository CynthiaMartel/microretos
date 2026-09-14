<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipoReflexionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo'                   => 'required|in:individual,grupal',
            'autor_nombre'           => 'required_if:tipo,individual|nullable|string|max:100',
            'respuestas'             => 'required|array|min:1',
            'respuestas.*.pregunta'  => 'required|string',
            'respuestas.*.respuesta' => 'required|string|max:2000',
        ];
    }
}
