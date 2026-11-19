<?php

namespace App\Http\Resources;

use App\Models\ActividadCentral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActividadCentral */
class ActividadCentralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'quien'       => $this->usuario_nombre,
            'rol'         => $this->usuario_rol,
            'seccion'     => $this->seccion,
            'accion'      => $this->accion,
            'descripcion' => $this->descripcion,
            'critica'     => $this->critica,
            'fecha'       => $this->created_at?->toIso8601String(),
        ];
    }
}
