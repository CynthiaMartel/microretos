<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DocenteNota */
class DocenteNotaResource extends JsonResource
{
    /** @return array<string, mixed> */
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
