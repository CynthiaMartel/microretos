<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Centro educativo en Red DuaLab → Centros (solo lectura): sus ciclos por familia y cifras */
class CentroCentralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $familias = $this->ciclos->groupBy(fn ($c) => $c->familia?->nombre ?? 'Sin familia')
            ->map(fn ($ciclos, $familia) => ['familia' => $familia, 'ciclos' => $ciclos->pluck('nombre')->sort()->values()])
            ->sortBy('familia')->values();

        return [
            'id'        => $this->id,
            'nombre'    => $this->nombre,
            'municipio' => $this->municipio,
            'img'       => $this->img,
            'familias'  => $familias,
            'empresas'  => (int) $this->empresas_count + (int) $this->empresas_asignadas_count,
            'docentes'  => (int) $this->docentes_count,
        ];
    }
}
