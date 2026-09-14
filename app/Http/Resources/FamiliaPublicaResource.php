<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Familia expuesta al escaparate público (frontoffice). Whitelist explícita:
 * solo nombre e imagen, nada de relaciones internas (empresas, ciclos).
 */
class FamiliaPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'nombre'     => $this->nombre,
            'imagen_url' => $this->imagen_url,
        ];
    }
}
