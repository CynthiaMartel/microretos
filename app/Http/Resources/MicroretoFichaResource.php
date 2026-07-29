<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha pública de un Microreto (MicroretoModal.vue). Whitelist explícita: nunca se debe poder
 * llegar a exponer aquí campos sensibles de la empresa real (CIF, teléfono, email, persona de
 * contacto, dirección, web) — este recurso se usa también desde un endpoint público sin
 * autenticación (equipo por token), así que cualquier campo añadido a $fillable en Empresa o
 * Microreto NO se expone automáticamente: hay que añadirlo aquí a propósito.
 */
class MicroretoFichaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'uuid'                => $this->uuid,
            'titulo'              => $this->titulo,
            'subtitulo'           => $this->subtitulo,
            'empresa_nombre'      => $this->empresa_nombre,
            'quien_es'            => $this->quien_es,
            'dia_a_dia'           => $this->dia_a_dia,
            'pregunta_reto'       => $this->pregunta_reto,
            'dificultades'        => $this->dificultades,
            'que_necesitan'       => $this->que_necesitan,
            'limitaciones'        => $this->limitaciones,
            'prototipos'          => $this->prototipos,
            'ods_sugeridos'       => $this->ods_sugeridos,
            'soft_skills'         => $this->soft_skills,
            'evaluacion_oficial'  => $this->evaluacion_oficial,
            'tips_profesorado'    => $this->tips_profesorado,
            'variantes'           => $this->variantes,
            'nivel_grupo'         => $this->nivel_grupo,
            'curso'               => $this->curso,
            'ciclo'               => $this->ciclo,
            'modulo'              => $this->modulo,
            'duracion'            => $this->duracion,
            // Calculados por MicroretoFichaService::enriquecer()/enriquecerLote()
            'familia'             => $this->familia,
            'centro_educativo'    => $this->centro_educativo,
            'empresa_es_simulada' => $this->empresa_es_simulada,
            // Sector/tamaño + diagnóstico crudo de la empresa ("Datos recogidos de la empresa"
            // en la ficha — la materia prima que la IA resume en quien_es/dia_a_dia/dificultades/
            // que_necesitan/limitaciones, mostrada aparte para lectura comparativa). Nunca
            // CIF/teléfono/email/contacto/dirección/web.
            'empresa' => $this->whenLoaded('empresa', fn () => $this->empresa ? [
                'sector'            => $this->empresa->sector,
                'tamano'            => $this->empresa->tamano,
                'dia_a_normal'      => $this->empresa->dia_a_normal,
                'friccion_area'     => $this->empresa->friccion_area,
                'friccion_problema' => $this->empresa->friccion_problema,
                'consecuencias'     => $this->empresa->consecuencias,
                'restricciones'     => $this->empresa->restricciones,
                'lo_que_no_quieren' => $this->empresa->lo_que_no_quieren,
            ] : null),
        ];
    }
}
