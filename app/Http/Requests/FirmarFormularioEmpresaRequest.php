<?php

namespace App\Http\Requests;

/**
 * POST /public/formulario-empresa/{token}/firma — la empresa firma desde el enlace: mismos
 * consentimientos, firmante y versiones de los textos que en la tablet
 * (FirmarEmpresaComercialRequest). Sin 'capturadaAt': aquí no hay firmas sin conexión.
 */
class FirmarFormularioEmpresaRequest extends FirmarEmpresaComercialRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['capturadaAt']);

        return $reglas;
    }
}
