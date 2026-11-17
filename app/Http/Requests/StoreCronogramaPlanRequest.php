<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaPlanificacionSesiones;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cronograma de un plan de proyecto (POST /planes/{uuid}/cronograma, P4b): fechas, hitos (las
 * sesiones del cronograma, mismas reglas que planificacion_sesiones) y el alumnado del plan,
 * elegido por miembro (alias) de uno o varios encuentros. La visibilidad de esos encuentros y la
 * edición del plan las comprueba CronogramaPlanService.
 */
class StoreCronogramaPlanRequest extends FormRequest
{
    use ValidaPlanificacionSesiones;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($saneada = $this->planificacionSesionesSaneada()) {
            $this->merge($saneada);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $numEquipos = (int) $this->input('num_equipos', 1);

        return [
            'fecha'                 => ['required', 'date'],
            'fecha_fin'             => ['nullable', 'date', 'after_or_equal:fecha'],
            'num_equipos'           => ['required', 'integer', 'between:1,10'],
            'miembros'              => ['required', 'array', 'min:1', 'max:40'],
            'miembros.*.id'         => ['required', 'integer', 'distinct', 'exists:equipo_miembros,id'],
            'miembros.*.equipo_num' => ['required', 'integer', 'min:1', 'max:' . max(1, $numEquipos)],
            ...$this->reglasPlanificacionSesiones(),
            // Prácticas de meses: cada hito con su fecha real, en orden y dentro de las fechas del plan
            'planificacion_sesiones'         => ['required', 'array', 'min:1', 'max:60'],
            'planificacion_sesiones.*.fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha',
                ...($this->filled('fecha_fin') ? ['before_or_equal:fecha_fin'] : []),
                function ($attribute, $value, $fail) {
                    $i = (int) explode('.', $attribute)[1];
                    $anterior = $this->input('planificacion_sesiones.' . ($i - 1) . '.fecha');
                    if ($i > 0 && is_string($anterior) && is_string($value) && $value < $anterior) {
                        $fail('Los hitos tienen que ir en orden de fecha.');
                    }
                }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'miembros.required'           => 'Elige al alumnado del plan.',
            'miembros.min'                => 'Elige al alumnado del plan.',
            'miembros.*.equipo_num.max'   => 'Hay alumnado asignado a un grupo que no existe.',
            'planificacion_sesiones.required'             => 'Añade al menos un hito al cronograma.',
            'planificacion_sesiones.*.fecha.required'     => 'Pon la fecha de cada hito.',
            'planificacion_sesiones.*.fecha.after_or_equal'  => 'Ningún hito puede ser anterior al inicio de las prácticas.',
            'planificacion_sesiones.*.fecha.before_or_equal' => 'Ningún hito puede ser posterior al fin de las prácticas.',
            ...$this->mensajesPlanificacionSesiones(),
        ];
    }
}
