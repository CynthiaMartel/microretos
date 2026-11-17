<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valoración final de la empresa (POST /valoracion-empresa/{token}). Sin sesión: la autorización
 * es el token, que el controller comprueba (existe, no ha caducado y no se ha respondido ya).
 * Las claves de PREGUNTAS deben coincidir con las de ValoracionEmpresa.vue.
 */
class ValorarPropuestaEmpresaRequest extends FormRequest
{
    public const PREGUNTAS = [
        'responde_necesidad'     => ['Sí', 'Parcialmente', 'No'],
        'aplicable'              => ['Sí', 'Parcialmente', 'No'],
        'calidad'                => ['Sí', 'Parcialmente', 'No'],
        'interes_plan_practicas' => ['Sí', 'Quizá', 'No'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentarios'))) {
            $this->merge(['comentarios' => strip_tags($this->input('comentarios'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valoracion_global' => ['required', 'integer', 'between:1,5'],
            // Lista blanca: solo las claves conocidas y, en cada una, una de sus opciones
            'respuestas'        => ['required', 'array:' . implode(',', array_keys(self::PREGUNTAS))],
            ...collect(self::PREGUNTAS)->mapWithKeys(fn ($opciones, $k) => [
                "respuestas.$k" => ['required', 'string', 'in:' . implode(',', $opciones)],
            ])->all(),
            'comentarios'       => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'valoracion_global.required' => 'Indica una valoración global de 1 a 5.',
            'respuestas.*.required'      => 'Responde a todas las preguntas.',
        ];
    }
}
