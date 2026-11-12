<?php

namespace App\Http\Requests;

/**
 * PUT /comercial/empresas/{id} — corrige los datos de la empresa mientras la entrevista no
 * está firmada (EmpresaCaptacionPolicy::editar). Mismos campos que el alta, todos opcionales:
 * solo se cambia lo que llega. 'familias' reemplaza la lista entera.
 */
class UpdateEmpresaComercialRequest extends StoreEmpresaComercialRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['clienteUuid']); // solo tiene sentido en el alta
        foreach (['nombreComercial', 'familias', 'sector'] as $campo) {
            $reglas[$campo] = is_array($reglas[$campo])
                ? array_merge(['sometimes'], $reglas[$campo])
                : 'sometimes|' . $reglas[$campo];
        }
        return $reglas;
    }

    /** @return string[]  familias enviadas o ya vinculadas a la empresa */
    protected function familiasConInteresPermitido(): array
    {
        $vinculadas = \App\Models\EmpresaCaptacion::find((int) $this->route('id'))?->nombresFamilias() ?? [];
        return array_merge($vinculadas, array_filter((array) $this->input('familias', []), 'is_string'));
    }
}
