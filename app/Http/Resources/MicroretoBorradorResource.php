<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reto en borrador generado por un comercial (app comercial y revisión del superadmin).
 * Las firmas internas de generar() (ra_ce_firma, interes_firma…) nunca salen.
 */
/** @mixin \App\Models\MicroretoBorrador */
class MicroretoBorradorResource extends JsonResource
{
    private const CAMPOS_VISIBLES = [
        'titulo', 'subtitulo', 'quien_es', 'dia_a_dia', 'pregunta_reto', 'dificultades',
        'que_necesitan', 'limitaciones', 'prototipos', 'ods_sugeridos', 'evaluacion_oficial',
        'tips_profesorado', 'variantes', 'ciclo', 'modulo', 'curso', 'nivel_grupo',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'estado'      => $this->estado,
            'familia'     => $this->familia,
            'reto'        => array_intersect_key($this->datos ?? [], array_flip(self::CAMPOS_VISIBLES)),
            'captacion'   => $this->whenLoaded('captacion', fn () => $this->captacion ? [
                'id'               => $this->captacion->id,
                'nombre_comercial' => $this->captacion->nombre_comercial,
                'estado'           => $this->captacion->estado,
                'empresa_id'       => $this->captacion->empresa_id,
            ] : null),
            'creado_por'  => $this->whenLoaded('creadoPor', fn () => $this->creadoPor?->name),
            'created_at'  => $this->created_at?->toIso8601String(),
            'revisado_at' => $this->revisado_at?->toIso8601String(),
        ];
    }
}
