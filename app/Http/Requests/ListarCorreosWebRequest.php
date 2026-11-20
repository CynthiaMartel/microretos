<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros del Correo de info@ en Central («Primer contacto»); los mismos que admite la API de la web. */
class ListarCorreosWebRequest extends FormRequest
{
    // papelera: fuera de la bandeja; se restaura o se borra del todo en Red DuaLab → Papelera (superadmin)
    public const ESTADOS = ['nuevo', 'leido', 'archivado', 'papelera'];

    public function authorize(): bool
    {
        return true; // acceso por el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'carpeta'  => ['nullable', Rule::in(['recibidos', 'enviados'])],
            'estado'   => ['nullable', Rule::in(self::ESTADOS)],
            'buscar'   => ['nullable', 'string', 'max:100'],
            'adjuntos' => ['nullable', 'boolean'],
            'page'     => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
