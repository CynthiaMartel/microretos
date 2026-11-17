<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros de la bandeja «Primer contacto» de Central (los mismos que admite la API de la web). */
class ListarFormulariosWebRequest extends FormRequest
{
    public const ESTADOS     = ['nuevo', 'leido', 'archivado'];
    public const FORMULARIOS = ['rapido', 'centro', 'empresa', 'alumno', 'administracion'];
    public const ACCIONES    = ['registro', 'info', 'demo'];

    public function authorize(): bool
    {
        return true; // solo superadmin: lo comprueba el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'estado'     => ['nullable', Rule::in(self::ESTADOS)],
            'formulario' => ['nullable', Rule::in(self::FORMULARIOS)],
            'accion'     => ['nullable', Rule::in(self::ACCIONES)],
            'buscar'     => ['nullable', 'string', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
