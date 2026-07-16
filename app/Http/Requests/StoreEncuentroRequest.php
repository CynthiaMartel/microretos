<?php

namespace App\Http\Requests;

use App\Models\Microproyecto;
use Illuminate\Foundation\Http\FormRequest;

class StoreEncuentroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $textFields = ['centro_educativo', 'ciclo_formativo', 'curso', 'grupo', 'notas'];

        $sanitized = [];
        foreach ($textFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $sanitized[$field] = strip_tags($this->input($field));
            }
        }

        if ($this->has('alumnados') && is_array($this->input('alumnados'))) {
            $sanitized['alumnados'] = collect($this->input('alumnados'))
                ->map(fn($a) => [
                    'nombre'     => isset($a['nombre'])     ? strip_tags($a['nombre'])     : '',
                    'equipo_num' => isset($a['equipo_num']) ? (int) $a['equipo_num']       : null,
                    'rol'        => isset($a['rol'])        ? strip_tags($a['rol'])        : null,
                ])
                ->filter(fn($a) => !empty($a['nombre']))
                ->values()
                ->toArray();
        }

        if ($sanitized) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        $numEquipos = (int) $this->input('num_equipos', 0);

        return [
            'microproyecto_id'       => ['required', 'integer', 'exists:microproyectos,id', function ($attribute, $value, $fail) {
                $user = $this->user();
                if ($user->isSuperAdmin()) return;

                $proyecto = Microproyecto::find($value);
                if ($proyecto && $proyecto->centro_id !== $user->centro_educativo_id) {
                    $fail('El proyecto seleccionado no pertenece a tu centro educativo.');
                }
            }],
            'fecha'                  => 'required|date',
            'centro_educativo'       => 'nullable|string|max:255',
            'ciclo_formativo'        => 'nullable|string|max:255',
            'curso'                  => 'nullable|string|max:10',
            'grupo'                  => 'nullable|string|max:10',
            'num_alumnos'            => 'nullable|integer|min:1|max:999',
            'notas'                  => 'nullable|string|max:5000',
            'num_equipos'            => 'required|integer|min:1|max:30',
            'alumnados'              => 'required|array|min:1|max:200',
            'alumnados.*.nombre'     => 'required|string|max:100',
            'alumnados.*.equipo_num' => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) use ($numEquipos) {
                if ($numEquipos > 0 && $value > $numEquipos) {
                    $fail("El equipo asignado ({$value}) supera el número de equipos del encuentro ({$numEquipos}).");
                }
            }],
            'alumnados.*.rol'        => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'microproyecto_id.required' => 'Debes asociar un proyecto al encuentro antes de guardarlo.',
            'num_equipos.required'      => 'Indica el número de equipos del encuentro.',
            'alumnados.required'        => 'Reparte el alumnado en equipos antes de guardar el encuentro.',
            'alumnados.min'             => 'Reparte el alumnado en equipos antes de guardar el encuentro.',
            'alumnados.*.equipo_num.required' => 'Todos los alumnos deben tener un equipo asignado.',
        ];
    }
}
