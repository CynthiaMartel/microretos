<?php

namespace App\Http\Requests;

use App\Models\Microproyecto;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMicroproyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $textFields = ['titulo', 'curso', 'ra_ce', 'expectativas_empresa'];
        $sanitized  = [];

        foreach ($textFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $sanitized[$field] = strip_tags($this->input($field));
            }
        }

        // Protección de datos: del equipo solo se guarda el docente responsable. Antes admitía
        // cualquier array (p. ej. 'alumnos' con nombres reales del alumnado), que se descarta.
        if ($this->has('equipo') && is_array($this->input('equipo'))) {
            $sanitized['equipo'] = array_intersect_key($this->input('equipo'), array_flip(['docente_responsable']));
        }

        if ($this->has('evaluacion_oficial') && is_array($this->input('evaluacion_oficial'))) {
            $sanitized['evaluacion_oficial'] = $this->sanitizeRecursively($this->input('evaluacion_oficial'));
        }

        if ($this->has('diseno_microproyecto') && is_array($this->input('diseno_microproyecto'))) {
            $sanitized['diseno_microproyecto'] = $this->sanitizeDiseno($this->input('diseno_microproyecto'));
        }

        if ($sanitized) {
            $this->merge($sanitized);
        }
    }

    /**
     * Lista blanca de diseno_microproyecto: { fases[], clases[{fases[]}], metodologia, cronograma }.
     * Se descartan las claves desconocidas en vez de rechazarlas, porque el asistente reenvía el
     * objeto entero y las propuestas antiguas y las de demo llevan 'numero', 'duracion' o 'duracion_clases' en cada fase.
     *
     * @param array<mixed> $diseno
     * @return array<string, mixed>
     */
    private function sanitizeDiseno(array $diseno): array
    {
        $limpio = array_intersect_key($diseno, array_flip(['fases', 'clases', 'metodologia', 'cronograma']));

        if (isset($limpio['fases']) && is_array($limpio['fases'])) {
            $limpio['fases'] = array_map(
                fn ($f) => is_array($f)
                    ? $this->sanitizeRecursively(array_intersect_key($f, array_flip(['nombre', 'descripcion', 'numero', 'duracion', 'duracion_clases'])))
                    : $f,
                array_values($limpio['fases'])
            );
        }

        if (isset($limpio['clases']) && is_array($limpio['clases'])) {
            $limpio['clases'] = array_map(
                fn ($c) => is_array($c) ? array_intersect_key($c, array_flip(['fases'])) : $c,
                array_values($limpio['clases'])
            );
        }

        foreach (['metodologia', 'cronograma'] as $campo) {
            if (isset($limpio[$campo]) && is_string($limpio[$campo])) {
                $limpio[$campo] = strip_tags($limpio[$campo]);
            }
        }

        return $limpio;
    }

    /**
     * evaluacion_oficial contiene objetos anidados (modulo, ra, ce[], aplicacion),
     * a diferencia del resto de campos array que son listas planas de strings.
     */
    private function sanitizeRecursively(mixed $value): mixed
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizeRecursively($item), $value);
        }

        return $value;
    }

    public function rules(): array
    {
        return [
            'titulo'               => 'sometimes|string|max:255',
            'curso'                => 'sometimes|nullable|string|max:100',
            'empresa_id'           => 'sometimes|nullable|exists:empresas,id',
            'centro_id'            => 'sometimes|nullable|exists:centro_educativo,id',
            'familia_id'           => 'sometimes|nullable|exists:familias,id',
            'ciclo_id'             => 'sometimes|nullable|exists:ciclos_formativos,id',
            'datos_empresa'        => 'sometimes|nullable|array',
            'datos_centro'         => 'sometimes|nullable|array',
            'equipo'               => 'sometimes|nullable|array',
            'equipo.docente_responsable' => 'nullable|string|max:255',
            'modulos_seleccionados'=> 'sometimes|nullable|array',
            'ra_ce'                => 'sometimes|nullable|string|max:50000',
            'evaluacion_oficial'              => 'sometimes|nullable|array',
            'evaluacion_oficial.*.modulo'     => 'nullable|string|max:255',
            'evaluacion_oficial.*.ra_id'      => 'nullable|integer|exists:resultados_aprendizaje,id',
            'evaluacion_oficial.*.ra'         => 'nullable|string|max:2000',
            'evaluacion_oficial.*.ce_ids'     => 'nullable|array',
            'evaluacion_oficial.*.ce_ids.*'   => 'integer|exists:criterios_evaluacion,id',
            'evaluacion_oficial.*.ce'         => 'nullable|array',
            'evaluacion_oficial.*.ce.*'       => 'nullable|string|max:1000',
            'evaluacion_oficial.*.aplicacion' => 'nullable|string|max:1000',
            'fundamentacion'       => 'sometimes|nullable|array',
            'diseno_reto'          => 'sometimes|nullable|array',
            'diseno_microproyecto' => 'sometimes|nullable|array',
            'diseno_microproyecto.fases'                   => 'nullable|array|max:10',
            'diseno_microproyecto.fases.*'                 => 'array',
            'diseno_microproyecto.fases.*.nombre'          => 'nullable|string|max:255',
            'diseno_microproyecto.fases.*.descripcion'     => 'nullable|string|max:2000',
            'diseno_microproyecto.fases.*.numero'          => 'nullable|integer|between:0,4',
            'diseno_microproyecto.fases.*.duracion'        => 'nullable|string|max:100',
            'diseno_microproyecto.fases.*.duracion_clases' => 'nullable|integer|between:0,100',
            'diseno_microproyecto.clases'                  => 'nullable|array|max:60',
            'diseno_microproyecto.clases.*'                => 'array',
            'diseno_microproyecto.clases.*.fases'          => 'nullable|array|max:5',
            'diseno_microproyecto.clases.*.fases.*'        => 'integer|between:0,4',
            'diseno_microproyecto.metodologia'             => 'nullable|string|max:10000',
            'diseno_microproyecto.cronograma'              => 'nullable|string|max:10000',
            'resumen'              => 'sometimes|nullable|array',
            'objetivos'            => 'sometimes|nullable|array',
            'kpis'                 => 'sometimes|nullable|array',
            // validacion_empresa no se acepta aquí: solo la escribe la empresa desde su enlace
            // (validarEmpresa). Si el asistente la reenvía, validated() la descarta.
            'paso_actual'          => 'sometimes|integer|min:1|max:13',
            // A 'validado' solo se llega por validarDocente o validarEmpresa. El asistente reenvía
            // siempre el estado actual, así que se acepta 'validado' únicamente si ya lo estaba.
            'estado'               => ['sometimes', 'in:en_edicion,propuesta,validado,completado,archivado', function ($attribute, $value, $fail) {
                if (!in_array($value, ['validado', 'completado'], true)) {
                    return;
                }
                $actual = Microproyecto::where('uuid', $this->route('uuid'))->value('estado');
                if ($value === 'validado' && $actual !== 'validado') {
                    $fail('El reto ajustado solo se valida con la validación del docente.');
                }
                // Completado = los grupos han terminado: solo desde un reto ajustado ya validado
                if ($value === 'completado' && !in_array($actual, ['validado', 'completado'], true)) {
                    $fail('Solo se puede completar un reto ajustado validado.');
                }
            }],
            'enviado_a_empresa_mail' => 'sometimes|boolean',
            // Solo tienen sentido en un plan de proyecto (etapa 2)
            'horas_estimadas'      => 'sometimes|nullable|integer|between:1,2000',
            'expectativas_empresa' => 'sometimes|nullable|string|max:3000',
        ];
    }
}
