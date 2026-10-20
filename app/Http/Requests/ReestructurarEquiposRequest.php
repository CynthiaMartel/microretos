<?php

namespace App\Http\Requests;

use App\Support\AliasGenerator;
use Illuminate\Foundation\Http\FormRequest;

class ReestructurarEquiposRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Protección de datos: del alumnado solo se recibe el alias (lista blanca de campos;
        // un 'nombre' que llegue se descarta aquí y nunca se guarda).
        if ($this->has('alumnados') && is_array($this->input('alumnados'))) {
            $sanitized = collect($this->input('alumnados'))
                ->filter(fn($a) => is_array($a))
                ->map(fn($a) => [
                    'id'         => !empty($a['id'])        ? (int) $a['id']               : null,
                    'alias'      => isset($a['alias'])      ? trim(strip_tags((string) $a['alias'])) : '',
                    'equipo_num' => isset($a['equipo_num']) ? (int) $a['equipo_num']       : null,
                    'rol'        => isset($a['rol'])        ? strip_tags((string) $a['rol']) : null,
                ])
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
            // Sin AliasAlumnado: los miembros antiguos conservan su alias de antes hasta
            // `alumnado:anonimizar`; los nuevos (sin id) sí tienen que traer uno generado.
            'alumnados.*.alias'      => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'alumnados.*'            => [function ($attribute, $value, $fail) {
                if (empty($value['id']) && !AliasGenerator::esValido($value['alias'] ?? null)) {
                    $fail('El alias tiene que ser el nombre de pila (una sola palabra, sin apellidos) seguido del animal y el color generados.');
                }
            }],
            'alumnados.*.equipo_num' => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) use ($numEquipos) {
                if ($numEquipos > 0 && $value > $numEquipos) {
                    $fail("El grupo asignado ({$value}) supera el número de grupos ({$numEquipos}).");
                }
            }],
            'alumnados.*.rol'        => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'num_equipos.required'      => 'Indica el número de grupos.',
            'alumnados.required'        => 'Reparte el alumnado en grupos antes de guardar.',
            'alumnados.min'             => 'Reparte el alumnado en grupos antes de guardar.',
            'alumnados.*.equipo_num.required' => 'Todos los alumnos deben tener un grupo asignado.',
            'alumnados.*.alias.required'      => 'Todos los alumnos necesitan un alias.',
            'alumnados.*.alias.distinct'      => 'Hay dos alumnos con el mismo alias. Genera otro con el dado.',
        ];
    }
}
