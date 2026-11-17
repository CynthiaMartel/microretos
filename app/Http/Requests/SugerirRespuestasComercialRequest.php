<?php

namespace App\Http\Requests;

/**
 * POST /comercial/empresas/{id}/sugerencias — lo que la empresa lleva contado en la entrevista
 * (aún sin guardar), para que las ideas de la IA lo tengan en cuenta. Mismos campos y límites
 * que el diagnóstico, todos opcionales.
 */
class SugerirRespuestasComercialRequest extends UpdateDiagnosticoEmpresaRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_map(fn ($regla) => str_replace('required|', 'nullable|', $regla), parent::rules());
    }
}
