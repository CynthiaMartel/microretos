<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/equipo/{token}/fase/{fase} — ruta pública (token del equipo, sin sesión).
 *
 * Lista blanca por fase de lo que el alumnado puede escribir en equipo_fases.datos: todo lo
 * demás se descarta. Así no puede colar claves que escribe el docente (p. ej.
 * 'evaluacion_docente' en F4, de donde sale la nota final) ni un nombre real en los
 * miembros de F0 (protección de datos: solo alias).
 */
class GuardarFaseEquipoRequest extends FormRequest
{
    private const CAMPOS = [
        0 => ['contrato_firmado', 'miembros'],
        1 => ['sintesis', 'reto_frase', 'hallazgos'],
        2 => ['propuesta', 'explicacion_propuesta', 'solucion_final', 'justificacion_solucion_final',
              'tipo_prototipo', 'prototipo_url', 'iteracion'],
        3 => ['descripcion_entregable', 'url_entregable'],
        4 => ['expone_clase', 'organizacion'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $datos = $this->input('datos');
        if (!is_array($datos)) {
            return;
        }

        $fase  = (int) $this->route('fase');
        $datos = array_intersect_key($datos, array_flip(self::CAMPOS[$fase] ?? []));

        if (isset($datos['miembros']) && is_array($datos['miembros'])) {
            $datos['miembros'] = collect($datos['miembros'])
                ->filter(fn ($m) => is_array($m))
                ->map(fn ($m) => [
                    'id'            => !empty($m['id']) ? (int) $m['id'] : null,
                    'alias'         => isset($m['alias']) ? trim(strip_tags((string) $m['alias'])) : '',
                    'rol'           => isset($m['rol']) ? strip_tags((string) $m['rol']) : null,
                    'fortalezas'    => is_array($m['fortalezas'] ?? null) ? array_values($m['fortalezas']) : [],
                    'puntos_mejora' => is_array($m['puntos_mejora'] ?? null) ? array_values($m['puntos_mejora']) : [],
                ])
                ->values()
                ->all();
        }

        if (isset($datos['organizacion']) && is_array($datos['organizacion'])) {
            $datos['organizacion'] = array_intersect_key($datos['organizacion'], array_flip(['modo_intervencion', 'tipo_exposicion']));
        }

        $this->merge(['datos' => $datos]);
    }

    public function rules(): array
    {
        $texto = 'nullable|string|max:10000';

        return [
            // present (no required): una fase sin nada que guardar llega como {}
            'datos'                         => 'present|array',

            // F0
            'datos.contrato_firmado'        => 'sometimes|boolean',
            'datos.miembros'                => 'sometimes|array|max:30',
            'datos.miembros.*.id'           => 'nullable|integer',
            'datos.miembros.*.alias'        => 'nullable|string|max:255',
            'datos.miembros.*.rol'          => 'nullable|string|max:50',
            'datos.miembros.*.fortalezas'   => 'array|max:20',
            'datos.miembros.*.fortalezas.*' => 'nullable|string|max:100',
            'datos.miembros.*.puntos_mejora'   => 'array|max:20',
            'datos.miembros.*.puntos_mejora.*' => 'nullable|string|max:100',

            // F1
            'datos.sintesis'                => 'sometimes|array|max:20',
            'datos.sintesis.*.pregunta'     => 'nullable|string|max:500',
            'datos.sintesis.*.respuesta'    => $texto,
            'datos.reto_frase'              => $texto,
            'datos.hallazgos'               => 'sometimes|array|max:50',
            'datos.hallazgos.*'             => 'nullable|string|max:280',

            // F2
            'datos.propuesta'                    => $texto,
            'datos.explicacion_propuesta'        => $texto,
            'datos.solucion_final'               => $texto,
            'datos.justificacion_solucion_final' => $texto,
            'datos.tipo_prototipo'               => 'nullable|string|max:100',
            'datos.prototipo_url'                => 'nullable|string|max:2048',
            'datos.iteracion'                    => 'nullable|integer|min:1|max:99',

            // F3
            'datos.descripcion_entregable'  => $texto,
            'datos.url_entregable'          => 'nullable|string|max:2048',

            // F4
            'datos.expone_clase'                     => 'nullable|boolean',
            'datos.organizacion'                     => 'sometimes|array',
            'datos.organizacion.modo_intervencion'   => 'nullable|in:todos,portavoz',
            'datos.organizacion.tipo_exposicion'     => 'sometimes|array|max:10',
            'datos.organizacion.tipo_exposicion.*'   => 'string|max:100',
        ];
    }
}
