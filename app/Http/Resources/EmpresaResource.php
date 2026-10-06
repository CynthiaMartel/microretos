<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Empresa para las vistas internas (Generador de Retos, Empresas, Startup Day,
 * Gestión de usuarios). Whitelist explícita de los campos que consume el frontend:
 * los de diagnóstico (friccion_*, consecuencias…) son necesarios para precargar el
 * Paso 2 del generador. Quedan fuera timestamps, soft-delete y campos internos
 * sin uso en cliente (proyecto_asociado, fecha_cita).
 */
/** @mixin \App\Models\Empresa */
class EmpresaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'cif'                 => $this->cif,
            'nombre_comercial'    => $this->nombre_comercial,
            'razon_social'        => $this->razon_social,
            'es_simulada'         => $this->es_simulada,
            // Catálogo DuaLab (T2): plantilla compartida, o copia de una plantilla en un centro
            'es_catalogo'         => (bool) $this->es_catalogo,
            'copiada_de_id'       => $this->copiada_de_id,
            'sector'              => $this->sector,
            'actividad'           => $this->actividad,
            'tamano'              => $this->tamano,
            'web'                 => $this->web,
            'estado_contacto'     => $this->estado_contacto,

            // Contacto
            'telefono'            => $this->telefono,
            'email_general'       => $this->email_general,
            'persona_contacto'    => $this->persona_contacto,
            'email_contacto'      => $this->email_contacto,
            'posicion_contacto'   => $this->posicion_contacto,
            'horario_atencion'    => $this->horario_atencion,

            // Dirección
            'direccion'           => $this->direccion,
            'numero'              => $this->numero,
            'otros_direccion'     => $this->otros_direccion,
            'codigo_postal'       => $this->codigo_postal,
            'municipio'           => $this->municipio,
            'provincia'           => $this->provincia,

            // Centro (string legacy + FK)
            'centro_educativo'    => $this->centro_educativo,
            'centro_id'           => $this->centro_id,

            // Diagnóstico — materia prima del Paso 2 del generador
            'dia_a_normal'        => $this->dia_a_normal,
            'friccion_area'       => $this->friccion_area,
            'friccion_problema'   => $this->friccion_problema,
            'consecuencias'       => $this->consecuencias,
            'restricciones'       => $this->restricciones,
            'lo_que_no_quieren'   => $this->lo_que_no_quieren,
            'expectativas_alumno' => $this->expectativas_alumno,

            // Familias vinculadas (filtro del generador, chips en Empresas, autoselección en Startup Day)
            'familias'            => $this->whenLoaded('familias', fn () =>
                $this->familias->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()
            ),
        ];
    }
}
