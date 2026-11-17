<?php

namespace App\Http\Requests\Concerns;

/**
 * Formulario de la entrevista por correo: la empresa acepta la información de protección de
 * datos al empezar y cada guardado lo confirma con la versión que se le mostró.
 */
trait AceptaPrivacidadFormulario
{
    /** @return array<string, string> */
    protected function reglasPrivacidad(): array
    {
        return [
            'aceptaPrivacidad'  => 'accepted',
            'privacidadVersion' => 'required|string|max:30|in:' . config('captacion.formulario.privacidad.version'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'aceptaPrivacidad.accepted' => 'Tienes que leer y aceptar la información sobre protección de datos.',
            'privacidadVersion.in'      => 'La información de protección de datos ha cambiado: recarga la página para leerla.',
        ];
    }
}
