<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Respuesta de la empresa a una propuesta desde su enlace público
 * (POST /startup/landing/{token}/validar). Sin sesión: la autorización es
 * el token_empresa, que el controller comprueba al buscar el proyecto.
 */
class ValidarPropuestaEmpresaRequest extends FormRequest
{
    // Preguntas y opciones del formulario de StartupDayLanding.vue (preguntas / radios)
    private const PREGUNTAS = ['reto_comprensible', 'objetivos_alineados', 'equipo_adecuado', 'viabilidad'];
    private const OPCIONES  = ['Sí', 'No', 'Parcialmente'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision'    => ['required', 'in:validar,no_validar_aun'],
            // Lista blanca: solo las claves conocidas y, en cada una, una de las opciones
            'respuestas'  => ['required', 'array:' . implode(',', self::PREGUNTAS)],
            ...collect(self::PREGUNTAS)->mapWithKeys(fn ($k) => [
                "respuestas.$k" => ['nullable', 'string', 'in:' . implode(',', self::OPCIONES)],
            ])->all(),
            'comentarios' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
