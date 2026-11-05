<?php

namespace App\Http\Requests;

use App\Support\SectoresEmpresa;
use Illuminate\Validation\Rule;

/**
 * POST /comercial/empresas — alta de una empresa real por un comercial, en la entrevista.
 * Subconjunto de StoreEmpresaRequest (mismos límites y nombres camelCase): ni centro, ni
 * es_simulada, ni estado de contacto ni ciclos — eso lo decide DuaLab después. El
 * diagnóstico va aparte (PATCH .../diagnostico), así el alta se puede guardar sin él.
 */
class StoreEmpresaComercialRequest extends StoreEmpresaRequest
{
    protected const CAMPOS = [
        'nombreComercial', 'razonSocial', 'cif', 'sector', 'tamano', 'nivelDigitalizacion',
        'web', 'actividad', 'personaContacto', 'telefono', 'emailGeneral', 'direccion',
        'municipio', 'provincia', 'codigoPostal', 'familias', 'familias.*',
        'interesesFamilia', 'interesesFamilia.*',
    ];

    public function authorize(): bool
    {
        // Rol: middleware 'comercial' en routes/api.php
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = array_intersect_key(parent::rules(), array_flip(static::CAMPOS));
        // Sin 'familia' suelta: el comercial siempre manda la lista completa
        $reglas['familias'] = 'required|array|min:1|max:30';
        // Lo que se dice delante de la empresa es real: el sector sirve a la IA para el reto
        $reglas['sector'] = ['required', 'string', Rule::in(SectoresEmpresa::LISTA)];
        return $reglas;
    }
}
