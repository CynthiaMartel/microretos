<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta de proyecto completado para el listado público del escaparate (frontoffice).
 * Whitelist explícita, igual criterio que MicroretoListadoPublicoResource: nunca datos
 * de contacto de empresa/centro ni nombres reales de alumnado (ver
 * MicroproyectoFichaPublicaResource, que documenta qué se excluye a propósito).
 */
class MicroproyectoListadoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'titulo'              => $this->titulo,
            'pregunta_reto'       => $this->diseno_reto['pregunta_reto'] ?? null,
            'empresa_nombre'      => $this->empresa?->nombre_comercial,
            'familia'             => $this->familia?->nombre,
            'curso'               => $this->curso,
            'imagen_portada_url'  => $this->imagenPortada?->url,
        ];
    }
}
