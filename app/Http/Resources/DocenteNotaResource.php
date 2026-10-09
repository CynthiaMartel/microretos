<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocenteNotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'texto'      => $this->texto,
            'fecha'      => $this->fecha?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
