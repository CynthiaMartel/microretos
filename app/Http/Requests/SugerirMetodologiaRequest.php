<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /startup/sugerir-metodologia — metodología sugerida con IA.
 * Los campos van al prompt de la IA: todos con lista blanca y longitud máxima.
 */
class SugerirMetodologiaRequest extends FormRequest
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
            'fases'         => 'nullable|string|max:500',
            'ciclo'         => 'nullable|string|max:255',
            'curso'         => 'nullable|string|max:20',
            'empresa'       => 'nullable|string|max:500',
            'modulos'       => 'nullable|string|max:1000',
            'reto_origen'   => 'nullable|string|max:3000',
        ];
    }
}
