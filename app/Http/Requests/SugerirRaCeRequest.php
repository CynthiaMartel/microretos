<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /startup/sugerir-ra-ce — selección de RA/CE con IA (closed-book).
 * Los campos van al prompt de la IA: todos con lista blanca y longitud máxima.
 */
class SugerirRaCeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modulo_ids'    => 'required|array|min:1',
            'modulo_ids.*'  => 'integer|exists:modulos,id',
            'titulo'        => 'nullable|string|max:300',
            'pregunta_reto' => 'nullable|string|max:1000',
            'descripcion'   => 'nullable|string|max:2000',
            'contexto'      => 'nullable|string|max:2000',
            'reto_origen'   => 'nullable|string|max:3000',
        ];
    }
}
