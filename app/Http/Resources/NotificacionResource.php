<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Illuminate\Notifications\DatabaseNotification */
class NotificacionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Solo los campos de presentación que guardan las clases de app/Notifications
        $data = $this->data ?? [];

        return [
            'id'         => $this->id,
            'tipo'       => $data['tipo'] ?? 'general',
            'titulo'     => $data['titulo'] ?? '',
            'mensaje'    => $data['mensaje'] ?? '',
            'ruta'       => $data['ruta'] ?? null,
            'leida'      => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
