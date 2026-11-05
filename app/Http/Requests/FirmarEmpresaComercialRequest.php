<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * POST /comercial/empresas/{id}/firma — consentimientos y firma del empresario.
 * 'versiones' son las versiones de los textos que la tablet mostró: si no coinciden con las
 * vigentes (config/captacion.php) no se firma, para no registrar la aceptación de un texto
 * que el empresario no ha visto.
 */
class FirmarEmpresaComercialRequest extends FormRequest
{
    // PNG en base64 del lienzo de firma: ~300 KB de sobra para una firma a mano
    private const MAX_FIRMA_BASE64 = 400_000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->firmanteDni)) {
            $this->merge(['firmanteDni' => strtoupper((string) preg_replace('/[\s.-]/', '', $this->firmanteDni))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $reglas = [
            'firmanteNombre'   => 'required|string|max:255',
            // DNI (8 dígitos + letra) o NIE (X/Y/Z + 7 dígitos + letra); la letra se comprueba en after()
            'firmanteDni'      => ['required', 'string', 'regex:/^([0-9]{8}|[XYZ][0-9]{7})[A-Z]$/'],
            'firmanteCargo'    => 'nullable|string|max:255',
            'firmanteEmail'    => 'nullable|email|max:255',
            'aceptaAvisoLegal' => 'accepted',
            'aceptaInfoRgpd'   => 'accepted',
            'cesiones'         => 'present|array',
            'versiones'        => 'required|array',
            // Sin la coma final: starts_with separa sus valores por comas
            'firma'            => ['required', 'string', 'max:' . self::MAX_FIRMA_BASE64, 'starts_with:data:image/png;base64'],
        ];
        // Una casilla por cesionaria: se registra el sí y el no, nunca se presupone
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
                $dni = (string) $this->input('firmanteDni');
                if (preg_match('/^([0-9]{8}|[XYZ][0-9]{7})([A-Z])$/', $dni, $m)) {
                    $numero = (int) strtr($m[1], ['X' => '0', 'Y' => '1', 'Z' => '2']);
                    if ('TRWAGMYFPDXBNJZSQVHLCKE'[$numero % 23] !== $m[2]) {
                        $validator->errors()->add('firmanteDni', 'La letra del DNI/NIE no es correcta.');
                    }
                }

                foreach (self::clavesTextos() as $clave) {
                    $vigente = config("captacion.textos.{$clave}.version") ?? config("captacion.cesiones.{$clave}.version");
                    if ($this->input("versiones.{$clave}") !== $vigente) {
                        $validator->errors()->add('versiones', 'Los textos legales han cambiado: vuelve a cargar la pantalla de firma.');
                        return;
                    }
                }
            },
        ];
    }

    /** @return array<int, string> */
    private static function clavesTextos(): array
    {
        return array_map('strval', array_merge(array_keys(config('captacion.textos')), array_keys(config('captacion.cesiones'))));
    }
}
