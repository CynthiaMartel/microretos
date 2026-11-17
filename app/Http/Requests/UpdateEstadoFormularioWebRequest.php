<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cambiar el estado de un formulario de la web desde Central (nuevo / leído / archivado). */
class UpdateEstadoFormularioWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // solo superadmin: lo comprueba el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(ListarFormulariosWebRequest::ESTADOS)],
        ];
    }
}
