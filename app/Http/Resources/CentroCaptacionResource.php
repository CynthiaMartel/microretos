<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Centro educativo captado (centro_captaciones). Los datos de la persona de contacto solo los
 * ven quien lo captó y el superadmin (CentroCaptacionPolicy / middleware 'superadmin').
 */
/** @mixin \App\Models\CentroCaptacion */
class CentroCaptacionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'estado'             => $this->estado,
            'origen'             => $this->whenLoaded('origen', fn () => ['clave' => $this->origen?->clave, 'nombre' => $this->origen?->nombre]),
            'accion'             => $this->accion,
            'centro_nombre'      => $this->centro_nombre,
            'direccion'          => $this->direccion,
            'provincia'          => $this->provincia,
            'municipio'          => $this->municipio,
            'familias'           => $this->familias ?? [],
            'contacto_nombre'    => $this->contacto_nombre,
            'contacto_apellidos' => $this->contacto_apellidos,
            'contacto_cargo'     => $this->contacto_cargo,
            'contacto_email'     => $this->contacto_email,
            'contacto_telefono'  => $this->contacto_telefono,
            'comentarios'        => $this->comentarios,
            'comercial'          => $this->whenLoaded('creadaPor', fn () => $this->creadaPor?->name),
            'consentimientos'    => $this->whenLoaded('consentimientos', fn () => $this->consentimientos->map(fn ($c) => [
                'clave'    => $c->clave,
                'version'  => $c->version,
                'aceptado' => $c->aceptado,
                'nombre'   => config("captacion.centros.textos.{$c->clave}.titulo")
                    ?? (($cesionaria = config("captacion.cesiones.{$c->clave}.cesionaria")) ? "Cesión a {$cesionaria}" : $c->clave),
            ])->values()),
            'centro_educativo'   => $this->whenLoaded('centroEducativo', fn () => $this->centroEducativo
                ? ['id' => $this->centroEducativo->id, 'nombre' => $this->centroEducativo->nombre] : null),
            'motivo_descarte'    => $this->motivo_descarte,
            'revisada_at'        => $this->revisada_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
