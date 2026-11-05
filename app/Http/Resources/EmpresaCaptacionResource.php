<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Empresa captada por un comercial: lo que rellena en la entrevista y su estado. La firma
 * solo se resume (quién firmó, cuándo, qué aceptó): el DNI, el email y la imagen no salen.
 */
/** @mixin \App\Models\Empresa */
class EmpresaCaptacionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $firma = $this->relationLoaded('firmas') ? $this->firmas->sortByDesc('firmado_at')->first() : null;

        return [
            'id'                   => $this->id,
            'nombre_comercial'     => $this->nombre_comercial,
            'razon_social'         => $this->razon_social,
            'cif'                  => $this->cif,
            'sector'               => $this->sector,
            'actividad'            => $this->actividad,
            'tamano'               => $this->tamano,
            'nivel_digitalizacion' => $this->nivel_digitalizacion,
            'web'                  => $this->web,
            'persona_contacto'     => $this->persona_contacto,
            'telefono'             => $this->telefono,
            'email_general'        => $this->email_general,
            'direccion'            => $this->direccion,
            'municipio'            => $this->municipio,
            'provincia'            => $this->provincia,
            'codigo_postal'        => $this->codigo_postal,

            'dia_a_normal'         => $this->dia_a_normal,
            'friccion_area'        => $this->friccion_area,
            'friccion_problema'    => $this->friccion_problema,
            'consecuencias'        => $this->consecuencias,
            'restricciones'        => $this->restricciones,
            'lo_que_no_quieren'    => $this->lo_que_no_quieren,
            'expectativas_alumno'  => $this->expectativas_alumno,

            'familias'             => $this->whenLoaded('familias', fn () => $this->familias
                ->map(fn ($f) => ['nombre' => $f->nombre, 'interes' => $f->pivot?->getAttribute('interes')])->values()),

            'estado_captacion'     => $this->estado_captacion,
            // Datos recomendados que no se recogieron en la entrevista (aviso al superadmin)
            'datos_incompletos'    => \App\Services\CaptacionEmpresaService::datosIncompletos($this->resource),
            'firmada_at'           => $this->firmada_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'comercial'            => $this->whenLoaded('creadaPor', fn () => $this->creadaPor?->name),
            'centros'              => $this->whenLoaded('centrosAsignados', fn () => $this->centrosAsignados
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()),
            'borradores_pendientes' => $this->whenCounted('borradores'),
            'firma'                => $firma ? [
                'uuid'             => $firma->uuid,
                'firmante_nombre'  => $firma->firmante_nombre,
                'firmante_cargo'   => $firma->firmante_cargo,
                'firmado_at'       => $firma->firmado_at->toIso8601String(),
                'documento_sha256' => $firma->documento_sha256,
                'consentimientos'  => $firma->relationLoaded('consentimientos')
                    ? $firma->consentimientos->map(fn ($c) => ['clave' => $c->clave, 'version' => $c->version, 'aceptado' => $c->aceptado])->values()
                    : null,
            ] : null,
        ];
    }
}
