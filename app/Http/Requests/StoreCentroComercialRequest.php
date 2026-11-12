<?php

namespace App\Http\Requests;

use App\Models\CentroCaptacion;
use App\Support\MunicipiosCanarias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /comercial/centros — entrevista a un centro educativo en la app comercial. Mismos campos
 * y límites que el formulario de centros del frontoffice (ContactoRequest), más la aceptación de
 * la información de protección de datos y las cesiones (sin firma). 'versiones' son las de los
 * textos que vio el centro: si no coinciden con las vigentes, no se registra.
 */
class StoreCentroComercialRequest extends FormRequest
{
    public const MAX_FAMILIAS = 4;

    public function authorize(): bool
    {
        // Rol: middleware 'comercial' en routes/api.php
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = [
            'accion'            => ['required', Rule::in(CentroCaptacion::ACCIONES)],
            'centroNombre'      => 'required|string|max:160',
            'direccion'         => 'required|string|max:200',
            'provincia'         => ['required', Rule::in(MunicipiosCanarias::PROVINCIAS)],
            'municipio'         => ['required', 'string', Rule::in(array_merge(...array_values(MunicipiosCanarias::MUNICIPIOS)))],
            'familias'          => 'required|array|min:1|max:' . self::MAX_FAMILIAS,
            'familias.*'        => 'string|distinct|exists:familias,nombre,deleted_at,NULL',
            'contactoNombre'    => 'required|string|max:120',
            'contactoApellidos' => 'required|string|max:120',
            'contactoCargo'     => 'required|string|max:120',
            'contactoEmail'     => 'required|email|max:255',
            'contactoTelefono'  => 'required|string|max:20|regex:/^\+?[0-9 ]{9,16}$/',
            'comentarios'       => 'nullable|string|max:2000',
            'aceptaInfoRgpd'    => 'accepted',
            'cesiones'          => 'present|array',
            'versiones'         => 'required|array',
            // Alta sin conexión en la tablet: se reenvía con el mismo identificador al sincronizar
            'clienteUuid'       => 'nullable|uuid',
        ];
        foreach (array_keys(config('captacion.cesiones')) as $clave) {
            $reglas["cesiones.{$clave}"] = 'required|boolean';
        }
        foreach (self::clavesTextos() as $clave) {
            $reglas["versiones.{$clave}"] = 'required|string|max:20';
        }
        return $reglas;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $provincia = (string) $this->input('provincia');
                if (isset(MunicipiosCanarias::MUNICIPIOS[$provincia])
                    && !in_array($this->input('municipio'), MunicipiosCanarias::MUNICIPIOS[$provincia], true)) {
                    $validator->errors()->add('municipio', 'Ese municipio no es de la provincia elegida.');
                }
                foreach (self::clavesTextos() as $clave) {
                    $vigente = config("captacion.centros.textos.{$clave}.version") ?? config("captacion.cesiones.{$clave}.version");
                    if ($this->input("versiones.{$clave}") !== $vigente) {
                        $validator->errors()->add('versiones', 'Los textos legales han cambiado: vuelve a cargar la pantalla.');
                        return;
                    }
                }
            },
        ];
    }

    /** @return array<int, string> */
    public static function clavesTextos(): array
    {
        return array_map('strval', array_merge(array_keys(config('captacion.centros.textos')), array_keys(config('captacion.cesiones'))));
    }
}
