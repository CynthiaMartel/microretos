<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReestructurarEquiposRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('alumnados') && is_array($this->input('alumnados'))) {
            $sanitized = collect($this->input('alumnados'))
                ->map(fn($a) => [
                    'id'         => !empty($a['id'])        ? (int) $a['id']               : null,
                    'nombre'     => isset($a['nombre'])     ? strip_tags($a['nombre'])     : '',
                    'equipo_num' => isset($a['equipo_num']) ? (int) $a['equipo_num']       : null,
                    'rol'        => isset($a['rol'])        ? strip_tags($a['rol'])        : null,
                ])
                ->filter(fn($a) => !empty($a['nombre']))
                ->values()
                ->toArray();

            $this->merge(['alumnados' => $sanitized]);
        }
    }

    public function rules(): array
    {
        $numEquipos = (int) $this->input('num_equipos', 0);

        return [
            'num_equipos'            => 'required|integer|min:1|max:30',
            'alumnados'              => 'required|array|min:1|max:200',
            'alumnados.*.id'         => 'nullable|integer',
            'alumnados.*.nombre'     => 'required|string|max:100',
            'alumnados.*.equipo_num' => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) use ($numEquipos) {
                if ($numEquipos > 0 && $value > $numEquipos) {
                    $fail("El equipo asignado ({$value}) supera el número de equipos ({$numEquipos}).");
                }
            }],
            'alumnados.*.rol'        => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'num_equipos.required'      => 'Indica el número de equipos.',
            'alumnados.required'        => 'Reparte el alumnado en equipos antes de guardar.',
            'alumnados.min'             => 'Reparte el alumnado en equipos antes de guardar.',
            'alumnados.*.equipo_num.required' => 'Todos los alumnos deben tener un equipo asignado.',
        ];
    }
}
