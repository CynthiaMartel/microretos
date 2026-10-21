<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /startup/sugerir-objetivos — objetivos sugeridos con IA.
 * Los campos van al prompt de la IA: todos con lista blanca y longitud máxima.
 */
class SugerirObjetivosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo'        => 'nullable|string|max:300',
            'pregunta_reto' => 'nullable|string|max:1000',
            'descripcion'   => 'nullable|string|max:2000',
            'entregables'   => 'nullable|string|max:1000',
            'ra_ce'         => 'nullable|string|max:30000',
            'reto_origen'   => 'nullable|string|max:3000',
        ];
    }
}
