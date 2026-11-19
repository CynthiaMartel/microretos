<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Empresa en Red DuaLab → Empresas (solo lectura): lo que hace falta para saber quién es, con quién
 * hablar y a qué centros está asociada. Sin el diagnóstico ni los campos internos del generador.
 */
class EmpresaCentralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Su centro (alta directa) y los centros donde se colocó al validar una captación (centro_empresa)
        $centros = collect([$this->centroEducativo?->nombre ?? $this->centro_educativo])
            ->merge($this->centrosAsignados->pluck('nombre'))->filter()->unique()->values();

        return [
            'id'                => $this->id,
            'nombre'            => $this->nombre_comercial,
            'razon_social'      => $this->razon_social,
            'sector'            => $this->sector,
            'actividad'         => $this->actividad,
            'tamano'            => $this->tamano,
            'es_simulada'       => (bool) $this->es_simulada,
            'es_catalogo'       => (bool) $this->es_catalogo,
            'estado_contacto'   => $this->estado_contacto,
            'web'               => $this->web,
            'telefono'          => $this->telefono,
            'email_general'     => $this->email_general,
            'persona_contacto'  => $this->persona_contacto,
            'email_contacto'    => $this->email_contacto,
            'posicion_contacto' => $this->posicion_contacto,
            'municipio'         => $this->municipio,
            'provincia'         => $this->provincia,
            'familias'          => $this->familias->pluck('nombre')->unique()->values(),
            'centros'           => $centros,
            'retos'             => $this->microretos_count,
        ];
    }
}
