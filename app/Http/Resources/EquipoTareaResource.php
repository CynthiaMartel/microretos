<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarea de un equipo en el workspace del alumnado (rutas públicas /equipo/{token}/…).
 * Mismos campos que formatWorkspace() en EquipoPublicoController.
 */
class EquipoTareaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'descripcion' => $this->descripcion,
            'tipo'        => $this->tipo,
            'obligatoria' => (bool) $this->obligatoria,
            // Protección de datos: alias del miembro; las tareas antiguas guardan el nombre
            // real y Equipo::aliasDe lo traduce sin exponerlo
            'responsable' => $this->equipo?->aliasDe($this->responsable),
            'estado'      => $this->estado,
            'orden'       => $this->orden,
        ];
    }
}
