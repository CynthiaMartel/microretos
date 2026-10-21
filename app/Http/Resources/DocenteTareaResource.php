<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DocenteTarea */
class DocenteTareaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'texto' => $this->texto,
            'hecha' => (bool) $this->hecha,
        ];
    }
}
