<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaPlanificacionSesiones;
use App\Models\Encuentro;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEncuentroRequest extends FormRequest
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->mensajesPlanificacionSesiones();
    }

    private ?Encuentro $encuentroCache = null;

    private function encuentroEditable(): ?Encuentro
    {
        return $this->encuentroCache ??= Encuentro::where('id', $this->route('id'))->editablesPara($this->user())->first();
    }

    public function rules(): array
    {
        return [
            'fecha_fin' => ['sometimes', 'nullable', 'date', function ($attribute, $value, $fail) {
                if (!$value) return;

                $encuentro = Encuentro::where('id', $this->route('id'))
                    ->editablesPara($this->user())
                    ->first();
                if (!$encuentro || !$encuentro->fecha) return;

                // Si en la misma petición cambia la planificación, el mínimo se calcula con la nueva
                if (is_array($this->input('planificacion_sesiones'))) {
                    $encuentro->planificacion_sesiones = $this->input('planificacion_sesiones');
                }
                $minimo = $encuentro->fechaFinSugerida(Carbon::parse($encuentro->fecha));
                if ($minimo && Carbon::parse($value)->lt($minimo)) {
                    $total = $encuentro->totalSesiones();
                    $fail("La fecha fin no puede ser anterior a {$minimo->toDateString()} ({$total} sesiones planificadas en este encuentro).");
                }
            }],
            ...$this->reglasPlanificacionSesiones(),
            // Cronograma de un plan de proyecto: cada hito con fecha real, en orden y desde el inicio
            // (sobre el hito entero: si faltara la clave `fecha`, una regla sobre `*.fecha` no se evaluaría)
            'planificacion_sesiones.*' => ['array', function ($attribute, $hito, $fail) {
                $encuentro = $this->encuentroEditable();
                if (!$encuentro || $encuentro->tipo !== Encuentro::TIPO_PLAN) return;
                $campo = $attribute . '.fecha';
                $fecha = is_array($hito) ? ($hito['fecha'] ?? null) : null;
                if (!is_string($fecha) || $fecha === '') { $this->validator->errors()->add($campo, 'Pon la fecha de cada hito.'); return; }
                if ($encuentro->fecha && $fecha < $encuentro->fecha->format('Y-m-d')) {
                    $this->validator->errors()->add($campo, 'Ningún hito puede ser anterior al inicio de las prácticas.'); return;
                }
                $i = (int) explode('.', $attribute)[1];
                $anterior = $this->input('planificacion_sesiones.' . ($i - 1) . '.fecha');
                if ($i > 0 && is_string($anterior) && $fecha < $anterior) {
                    $this->validator->errors()->add($campo, 'Los hitos tienen que ir en orden de fecha.');
                }
            }],
        ];
    }
}
