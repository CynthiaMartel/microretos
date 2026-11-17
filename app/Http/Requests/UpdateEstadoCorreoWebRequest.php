<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Marcar un correo de info@ como no leído, leído o archivado desde Central. */
class UpdateEstadoCorreoWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // acceso por el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(ListarCorreosWebRequest::ESTADOS)],
        ];
    }
}
