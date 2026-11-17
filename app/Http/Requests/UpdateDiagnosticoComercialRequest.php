<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * PATCH /comercial/empresas/{id}/diagnostico — la entrevista del comercial: el diagnóstico
 * (mismas reglas que UpdateDiagnosticoEmpresaRequest) y qué respuestas partieron de una
 * sugerencia de la IA que la empresa confirmó.
 */
class UpdateDiagnosticoComercialRequest extends UpdateDiagnosticoEmpresaRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'respuestasIa'   => 'nullable|array|max:' . count(self::CAMPOS_DIAGNOSTICO),
            'respuestasIa.*' => ['string', 'distinct', Rule::in(self::CAMPOS_DIAGNOSTICO)],
        ];
    }
}
