<?php

namespace App\Http\Requests;

/**
 * PUT /empresas/{id} — mismas reglas que el alta, pero todo es 'sometimes': un campo
 * que no llega no se toca. El Generador de Retos solo envía los campos del diagnóstico
 * y no debe vaciar el resto (CIF, contacto, dirección…).
 */
class UpdateEmpresaRequest extends StoreEmpresaRequest
{
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (string $regla, string $campo) => str_ends_with($campo, '.*') ? $regla : 'sometimes|' . $regla)
            ->all();
    }
}
