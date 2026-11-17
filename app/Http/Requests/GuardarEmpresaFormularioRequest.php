<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AceptaPrivacidadFormulario;

/**
 * PUT /public/formulario-empresa/{token}/empresa — la empresa rellena sus datos desde el enlace:
 * los mismos campos y obligatorios que el paso «Empresa» del comercial
 * (StoreEmpresaComercialRequest). 'familias' reemplaza la lista entera.
 */
class GuardarEmpresaFormularioRequest extends StoreEmpresaComercialRequest
{
    use AceptaPrivacidadFormulario;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['clienteUuid']); // solo para altas sin conexión de la tablet

        return $reglas + $this->reglasPrivacidad();
    }
}
