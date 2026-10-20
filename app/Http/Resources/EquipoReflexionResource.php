<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reflexión (F4) de un equipo en el workspace del alumnado (rutas públicas /equipo/{token}/…).
 * Mismos campos que formatWorkspace() en EquipoPublicoController.
 *
 * @mixin \App\Models\EquipoReflexion
 */
class EquipoReflexionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'tipo'         => $this->tipo,
            // Protección de datos: alias del autor (ver EquipoTareaResource)
            'autor_nombre' => $this->equipo?->aliasDe($this->autor_nombre),
            'respuestas'   => $this->respuestas,
            'created_at'   => $this->created_at,
        ];
    }
}
