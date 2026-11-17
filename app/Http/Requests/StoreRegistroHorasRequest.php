<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Anotar horas de prácticas (POST /agenda-practicas/{id}/horas, A3). Que el miembro sea de ese
 * cronograma y que el usuario pueda editarlo lo comprueba RegistroHorasService.
 */
class StoreRegistroHorasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('descripcion'))) {
            $this->merge(['descripcion' => trim(strip_tags($this->input('descripcion')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'miembro_id'  => ['required', 'integer', 'exists:equipo_miembros,id'],
            'fecha'       => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'horas'       => ['required', 'numeric', 'min:0.25', 'max:24'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'No se pueden anotar horas de días que aún no han llegado.',
            'horas.min'             => 'Anota al menos un cuarto de hora (0,25).',
            'horas.max'             => 'Un día no puede tener más de 24 horas.',
        ];
    }
}
