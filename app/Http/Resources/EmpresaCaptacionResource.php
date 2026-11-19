<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Empresa captada (empresa_captaciones): lo que se rellenó en la entrevista y su estado. La
 * firma solo se resume (quién firmó, cuándo, qué aceptó): el DNI, el email y la imagen no salen.
 */
/** @mixin \App\Models\EmpresaCaptacion */
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
            'notas'                => $this->notas, // formulario web: lo que no tiene columna propia
            // Preguntas (camelCase) cuya respuesta partió de una sugerencia de la IA confirmada por la empresa
            'respuestas_ia'        => $this->respuestas_ia ?? [],
            // Formulario enviado por correo a la empresa (el enlace en claro no se guarda)
            'formulario'           => $this->formulario_enviado_at ? [
                'enviado_at'    => $this->formulario_enviado_at->toIso8601String(),
                'expira_at'     => $this->formulario_expira_at?->toIso8601String(),
                'respondido_at' => $this->formulario_respondido_at?->toIso8601String(),
                'abierto'       => $this->resource->formularioAbierto(),
            ] : null,

            'familias'             => array_values(array_map(fn ($f) => [
                'nombre' => $f['nombre'] ?? '', 'interes' => $f['interes'] ?? null,
            ], $this->familias ?? [])),

            'estado'               => $this->estado,
            'origen'               => $this->whenLoaded('origen', fn () => ['clave' => $this->origen?->clave, 'nombre' => $this->origen?->nombre]),
            // Datos recomendados que no se recogieron en la entrevista (aviso al superadmin)
            'datos_incompletos'    => \App\Services\CaptacionEmpresaService::datosIncompletos($this->resource),
            'firmada_at'           => $this->firmada_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            // En la papelera (app comercial → Papelera)
            'eliminada_at'         => $this->deleted_at?->toIso8601String(),
            'comercial'            => $this->whenLoaded('creadaPor', fn () => $this->creadaPor?->name),
            'revisada_at'          => $this->revisada_at?->toIso8601String(),
            'motivo_descarte'      => $this->motivo_descarte,
            // Validada: la empresa de DuaLab que se creó y los centros en los que está colocada
            'empresa_id'           => $this->empresa_id,
            'centros'              => $this->whenLoaded('empresa', fn () => $this->empresa
                ? $this->empresa->centrosAsignados->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values()
                : []),
            'borradores_pendientes' => $this->whenCounted('borradores'),
            'firma'                => $firma ? [
                'uuid'             => $firma->uuid,
                'firmante_nombre'  => $firma->firmante_nombre,
                'firmante_cargo'   => $firma->firmante_cargo,
                'firmado_at'       => $firma->firmado_at->toIso8601String(),
                'documento_sha256' => $firma->documento_sha256,
                'consentimientos'  => $firma->relationLoaded('consentimientos')
                    ? $firma->consentimientos->map(fn ($c) => [
                        'clave' => $c->clave, 'version' => $c->version, 'aceptado' => $c->aceptado,
                        // Nombre legible del texto (las claves de config/captacion.php pueden cambiar)
                        'nombre' => config("captacion.textos.{$c->clave}.titulo")
                            ?? (($cesionaria = config("captacion.cesiones.{$c->clave}.cesionaria")) ? "Cesión a {$cesionaria}" : $c->clave),
                    ])->values()
                    : null,
            ] : null,
        ];
    }
}
