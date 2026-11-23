<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaPlanificacionSesiones;
use App\Models\Encuentro;
use App\Models\Microproyecto;
use App\Rules\AliasAlumnado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreEncuentroRequest extends FormRequest
{
    use ValidaPlanificacionSesiones;

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

        // Protección de datos: del alumnado solo se recibe el alias (lista blanca de campos;
        // un 'nombre' que llegue se descarta aquí y nunca se guarda).
        if ($this->has('alumnados') && is_array($this->input('alumnados'))) {
            $sanitized['alumnados'] = collect($this->input('alumnados'))
                ->filter(fn($a) => is_array($a))
                ->map(fn($a) => [
                    'alias'      => isset($a['alias'])      ? trim(strip_tags((string) $a['alias'])) : '',
                    'equipo_num' => isset($a['equipo_num']) ? (int) $a['equipo_num']       : null,
                    'rol'        => isset($a['rol'])        ? strip_tags((string) $a['rol']) : null,
                ])
                ->values()
                ->toArray();
        }

        $sanitized += $this->planificacionSesionesSaneada();

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
                $proyecto = Microproyecto::find($value);

                if (!$proyecto) {
                    return;
                }

                if (!$proyecto->esVisiblePara($user)) {
                    $fail('No tienes acceso a este reto ajustado. Pide al docente propietario que lo comparta contigo o crea uno nuevo.');
                    return;
                }

                if (!in_array($proyecto->estado, ['validado', 'completado'], true)) {
                    $fail('Solo se pueden llevar al aula retos ajustados validados. Valida el reto ajustado antes de crear el encuentro.');
                }
            }],
            'fecha'                  => 'required|date',
            // Opcional: sin ella se usa la sugerida. Puede alargarse, no quedar antes de la sugerida
            // (mismo criterio que UpdateEncuentroRequest).
            'fecha_fin'              => ['sometimes', 'nullable', 'date', 'after_or_equal:fecha', function ($attribute, $value, $fail) {
                if (!$value || !strtotime((string) $this->input('fecha'))) return;
                $planificacion = $this->input('planificacion_sesiones');
                if (empty($planificacion)) {
                    $planificacion = Microproyecto::find($this->input('microproyecto_id'))?->diseno_microproyecto['clases'] ?? null;
                }
                $minimo = (new Encuentro(['planificacion_sesiones' => $planificacion]))
                    ->fechaFinSugerida(Carbon::parse($this->input('fecha')));
                if ($minimo && Carbon::parse($value)->lt($minimo)) {
                    $fail("La fecha de fin no puede ser anterior al {$minimo->format('d/m/Y')}, según las sesiones planificadas.");
                }
            }],
            'centro_educativo'       => 'nullable|string|max:255',
            'ciclo_formativo'        => 'nullable|string|max:255',
            'curso'                  => 'required|string|max:20',
            'grupo'                  => 'required|string|max:10',
            'num_alumnos'            => 'nullable|integer|min:1|max:999',
            'notas'                  => 'nullable|string|max:5000',
            'num_equipos'            => 'required|integer|min:1|max:30',
            'alumnados'              => 'required|array|min:1|max:200',
            'alumnados.*.alias'      => ['required', 'string', 'distinct:ignore_case', new AliasAlumnado()],
            'alumnados.*.equipo_num' => ['required', 'integer', 'min:1', function ($attribute, $value, $fail) use ($numEquipos) {
                if ($numEquipos > 0 && $value > $numEquipos) {
                    $fail("El grupo asignado ({$value}) supera el número de grupos del encuentro ({$numEquipos}).");
                }
            }],
            'alumnados.*.rol'        => 'nullable|string|max:50',
            ...$this->reglasPlanificacionSesiones(),
        ];
    }

    public function messages(): array
    {
        return [
            'microproyecto_id.required' => 'Debes asociar un reto ajustado al encuentro antes de guardarlo.',
            'curso.required'            => 'Indica el curso del encuentro.',
            'grupo.required'            => 'Indica la clase del encuentro.',
            'num_equipos.required'      => 'Indica el número de grupos del encuentro.',
            'alumnados.required'        => 'Reparte el alumnado en grupos antes de guardar el encuentro.',
            'alumnados.min'             => 'Reparte el alumnado en grupos antes de guardar el encuentro.',
            'alumnados.*.equipo_num.required' => 'Todos los alumnos deben tener un grupo asignado.',
            'alumnados.*.alias.required'      => 'Todos los alumnos necesitan un alias.',
            'alumnados.*.alias.distinct'      => 'Hay dos alumnos con el mismo alias. Genera otro con el dado.',
            ...$this->mensajesPlanificacionSesiones(),
        ];
    }
}
