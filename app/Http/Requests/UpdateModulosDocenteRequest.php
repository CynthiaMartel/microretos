<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Módulos que imparte un docente (PUT /perfil/modulos y PUT /admin/usuarios/{user}/modulos).
 * Aquí solo la forma; que cada módulo sea de la oferta del centro del docente lo comprueba
 * ModulosDocenteController con ModulosDocenteService (depende del docente de destino).
 */
class UpdateModulosDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modulo_ids'   => ['present', 'array', 'max:80'],
            'modulo_ids.*' => ['integer', 'distinct'],
        ];
    }
}
