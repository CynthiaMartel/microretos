<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /central/valoraciones-empresa/{id}/enviar — DuaLab envía a la empresa, por info@, el enlace
 * para ver las soluciones de los grupos y valorar el reto. Quién puede: middleware de la ruta.
 */
class EnviarValoracionEmpresaCentralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Por defecto el de contacto de la empresa; se puede cambiar antes de enviar
            'email'   => 'required|email:rfc|max:255',
            // Unas líneas para la empresa, opcionales
            'mensaje' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
