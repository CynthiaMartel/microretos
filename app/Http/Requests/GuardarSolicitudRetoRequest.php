<?php

namespace App\Http\Requests;

/**
 * PUT /public/solicitud-reto/{token} — el docente guarda lo que lleva al pasar de paso (para
 * seguir más tarde): mismos campos, límites y comprobaciones del currículo que al enviar, pero
 * ninguno obligatorio todavía. La privacidad sí: se acepta al empezar y cada guardado lo confirma.
 */
class GuardarSolicitudRetoRequest extends EnviarSolicitudRetoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $campo => $regla) {
            // La estructura de cada RA/CE elegido y la privacidad siguen siendo obligatorias
            if (str_contains($campo, 'seleccionRaCe.*.') || $campo === 'privacidadVersion') continue;
            $rules[$campo] = str_replace('required|', 'nullable|', $regla);
        }

        return $rules;
    }
}
