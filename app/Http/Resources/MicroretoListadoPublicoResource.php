<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta de reto para el listado público del escaparate (frontoffice). Whitelist
 * explícita y deliberadamente más corta que MicroretoFichaResource (esa es para la
 * ficha completa tras entrar al detalle) — aquí solo lo necesario para una grid de
 * tarjetas. Nunca exponer aquí campos de Empresa distintos de los ya calculados por
 * MicroretoFichaService (familia, empresa_nombre): ver el aviso en
 * MicroretoFichaResource sobre CIF/teléfono/email/contacto/dirección/web.
 */
class MicroretoListadoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'           => $this->uuid,
            'titulo'         => $this->titulo,
            'subtitulo'      => $this->subtitulo,
            'familia'        => $this->familia,
            'empresa_nombre' => $this->empresa_nombre,
            'nivel_grupo'    => $this->nivel_grupo,
            'curso'          => $this->curso,
        ];
    }
}
