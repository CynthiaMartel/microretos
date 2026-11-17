<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marca o desmarca a un miembro (alias) como destacado para prácticas
 * (PUT /central/destacados/miembros/{id}). Solo superadmin, por el middleware de la ruta.
 */
class MarcarDestacadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'destacado' => ['required', 'boolean'],
        ];
    }
}
