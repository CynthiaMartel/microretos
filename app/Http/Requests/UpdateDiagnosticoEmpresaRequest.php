<?php

namespace App\Http\Requests;

// Solo el diagnóstico (P1–P4) de StoreEmpresaRequest: mismos límites y la misma
// normalización de 'consecuencias' (prepareForValidation heredado). Cualquier otro
// campo de la empresa (CIF, contacto, es_simulada...) queda fuera de la lista blanca.
class UpdateDiagnosticoEmpresaRequest extends StoreEmpresaRequest
{
    private const CAMPOS_DIAGNOSTICO = [
        'diaANormal', 'friccionArea', 'friccionProblema',
        'consecuencias', 'restricciones', 'loQueNoQuieren', 'expectativasAlumno',
    ];

    // P1 y P2/P2b son la base mínima de un diagnóstico (igual que exige el paso 2).
    private const OBLIGATORIOS = ['diaANormal', 'friccionArea', 'friccionProblema'];

    /** @return array<string, string> */
    public function rules(): array
    {
        $reglas = array_intersect_key(parent::rules(), array_flip(self::CAMPOS_DIAGNOSTICO));
        foreach (self::OBLIGATORIOS as $campo) {
            $reglas[$campo] = str_replace('nullable|', 'required|', $reglas[$campo]);
        }
        return $reglas;
    }
}
