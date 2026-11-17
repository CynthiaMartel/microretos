<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación docente de una propuesta (POST /startup/proyectos/{uuid}/validar-docente).
 * Junto con la validación de la empresa (validarEmpresa), es una de las dos vías que la llevan
 * a 'validado' y la habilitan para un encuentro creativo.
 * La autorización sobre la propuesta la hace el controller con la Policy.
 */
class ValidarDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:validar,desvalidar'],
        ];
    }
}
