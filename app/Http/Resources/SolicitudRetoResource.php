<?php

namespace App\Http\Resources;

use App\Models\SolicitudReto;
use App\Services\SolicitudRetoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solicitud de retos en DuaLab Central. `fase` resume en qué punto está para la bandeja:
 * pendiente → respondida → con_retos → avisada → con_encuentro (o descartada). Las respuestas
 * con los nombres del currículo y los retos solo van en la ficha (show), no en la lista.
 *
 * @mixin SolicitudReto
 */
class SolicitudRetoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $servicio = app(SolicitudRetoService::class);
        $numRetos = $this->microretos_count ?? null;
        $numEncuentros = $this->encuentros_count ?? null;

        return [
            'id'             => $this->id,
            'uuid'           => $this->uuid,
            'nombre_docente' => $this->nombre_docente,
            'email_docente'  => $this->email_docente,
            'centro_nombre'  => $this->centro_nombre,
            'centro'         => $this->whenLoaded('centro', fn () => $this->centro ? ['id' => $this->centro->id, 'nombre' => $this->centro->nombre] : null),
            'empresa'        => $this->whenLoaded('empresa', fn () => $this->empresa ? ['id' => $this->empresa->id, 'nombre' => $this->empresa->nombre_comercial] : null),
            'estado'         => $this->estado,
            'fase'           => $this->fase($numRetos, $numEncuentros),
            'sesion'         => $servicio->sesion($this->resource),
            'num_retos'      => $numRetos,
            'enviado_at'     => $this->enviado_at?->toIso8601String(),
            'expira_at'      => $this->expira_at?->toIso8601String(),
            'enlace_vigente' => $this->enlaceVigente(),
            'respondido_at'  => $this->respondido_at?->toIso8601String(),
            'avisado_at'     => $this->avisado_at?->toIso8601String(),
            'creada_por'     => $this->whenLoaded('creadaPor', fn () => $this->creadaPor?->name),
            'created_at'     => $this->created_at?->toIso8601String(),

            // Solo en la ficha
            'notas'          => $this->when($this->relationLoaded('microretos'), $this->notas),
            'respuestas'     => $this->when($this->relationLoaded('microretos'), fn () => (object) $servicio->respuestas($this->resource)),
            // Un bloque por cada petición para el generador, con los nombres del currículo
            'bloques'        => $this->when($this->relationLoaded('microretos'), fn () => $servicio->curriculo($this->resource)),
            'retos'          => $this->whenLoaded('microretos', fn () => $this->microretos->map(fn ($m) => [
                'uuid'       => $m->uuid,
                'titulo'     => $m->titulo,
                'empresa'    => $m->empresa?->nombre_comercial ?? $m->empresa_nombre,
                'avisado'    => in_array($m->uuid, $this->retos_avisados ?? [], true),
                // En la recámara (solo DuaLab) o ya publicado en la biblioteca de un centro
                'en_recamara' => (bool) $m->en_recamara,
                'centro'     => $m->en_recamara ? null : $m->centro?->nombre,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()),
        ];
    }

    private function fase(?int $numRetos, ?int $numEncuentros): string
    {
        return match (true) {
            $this->estado === SolicitudReto::ESTADO_DESCARTADA => 'descartada',
            $this->estado === SolicitudReto::ESTADO_PENDIENTE  => 'pendiente',
            ($numEncuentros ?? 0) > 0                          => 'con_encuentro',
            $this->avisado_at !== null                         => 'avisada',
            ($numRetos ?? 0) > 0                               => 'con_retos',
            default                                            => 'respondida',
        };
    }
}
