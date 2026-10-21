<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ciclo formativo tal como lo devuelven el alta y la edición de «Base de datos».
 *
 * @mixin \App\Models\CicloFormativo
 */
class CicloFormativoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'idCiclo'       => $this->idCiclo,
            'nombre'        => $this->nombre,
            'familia'       => $this->familia,     // legacy
            'familia_id'    => $this->familia_id,
            'grado'         => $this->grado,
            'referenciaBOE' => $this->referenciaBOE,
            'siglasGrado'   => $this->siglasGrado,
        ];
    }
}
