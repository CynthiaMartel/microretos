<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición manual por el docente del diagnóstico final que redactó la IA.
 * Mismos campos que devuelve la IA (ver EquipoGestionController::diagnosticoFinal),
 * lista blanca: cualquier otra clave se descarta en validated().
 */
class UpdateDiagnosticoFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'resumen'          => 'required|string|max:3000',
            'fortalezas'       => 'present|array|max:10',
            'fortalezas.*'     => 'required|string|max:500',
            'areas_mejora'     => 'present|array|max:10',
            'areas_mejora.*'   => 'required|string|max:500',
            'valoracion_ra_ce' => 'nullable|string|max:3000',
            'conclusion'       => 'nullable|string|max:1000',
        ];
    }
}
