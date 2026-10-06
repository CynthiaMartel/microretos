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
/** @mixin \App\Models\Microreto */
class MicroretoFichaResource extends JsonResource
{
    private function esPersonal(Request $request): bool
    {
        $user = $request->user();
        return $user !== null && ($user->isDocente() || $user->isAdmin() || $user->isSuperAdmin());
    }

    private function diagnosticoDesactualizado(): bool
    {
        // Ya calculado al enriquecer (la empresa cargada ahí es la copia, no la actual).
        if (array_key_exists('diagnostico_desactualizado', $this->resource->getAttributes())) {
            return (bool) $this->resource->getAttribute('diagnostico_desactualizado');
        }
        if (!is_array($this->diagnostico_empresa) || !$this->relationLoaded('empresa') || !$this->empresa) return false;
        if (!empty($this->diagnostico_empresa['modificado_en_reto'])) return false;
        return \App\Services\MicroretoFichaService::diagnosticoCambiado($this->diagnostico_empresa, $this->empresa);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'uuid'                => $this->uuid,
            'titulo'              => $this->titulo,
            'subtitulo'           => $this->subtitulo,
            'empresa_nombre'      => $this->empresa_nombre,
            // Fechas (T3): cuándo se creó el reto y cuándo se recogió el diagnóstico que muestra.
            'creado_en'               => $this->created_at?->toIso8601String(),
            'diagnostico_recogido_en' => \App\Services\MicroretoFichaService::fechaCopiaDiagnostico($this->resource),
            // El diagnóstico de este reto se ajustó al generarlo y no coincide con la ficha de la
            // empresa: se etiqueta para no atribuir a la empresa respuestas que no dio.
            'diagnostico_modificado_en_reto' => is_array($this->diagnostico_empresa) && !empty($this->diagnostico_empresa['modificado_en_reto']),
            // ¿La empresa ha cambiado su diagnóstico después de crear el reto? Solo para personal
            // (docente/admin/superadmin): al alumnado y al escaparate público no se les avisa.
            'diagnostico_desactualizado' => $this->when($this->esPersonal($request), fn () => $this->diagnosticoDesactualizado()),
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
            'multimodulo'         => (bool) $this->multimodulo,
            'ra_ce_origen'        => $this->ra_ce_origen,
            'duracion'            => $this->duracion,
            // Calculados por MicroretoFichaService::enriquecer()/enriquecerLote()
            'familia'             => $this->familia,
            'centro_educativo'    => $this->centro_educativo,
            'empresa_es_simulada' => $this->empresa_es_simulada,
            'es_simulado'         => (bool) $this->es_simulado,
            // Botón "Ver proyecto asociado completado" — el más reciente que cumple el
            // mismo gate (completado + visible_publico), ver PublicMicroretoCatalogoController.
            'proyecto_completado_uuid' => $this->whenLoaded('microproyectos', fn () => $this->microproyectos->first()?->uuid),
            // Sector/tamaño + diagnóstico crudo de la empresa ("Datos recogidos de la empresa"
            // en la ficha — la materia prima que la IA resume en quien_es/dia_a_dia/dificultades/
            // que_necesitan/limitaciones, mostrada aparte para lectura comparativa). Nunca
            // CIF/teléfono/email/contacto/dirección/web.
            // Si el reto guardó copia del diagnóstico (T3), se muestra esa copia: editar la
            // empresa después no altera la ficha. Retos antiguos sin copia: empresa en directo.
            'empresa' => $this->whenLoaded('empresa', fn () => $this->empresa ? array_merge(
                $this->empresa->only(\App\Services\MicroretoFichaService::CAMPOS_DIAGNOSTICO),
                is_array($this->diagnostico_empresa)
                    ? array_intersect_key($this->diagnostico_empresa, array_flip(\App\Services\MicroretoFichaService::CAMPOS_DIAGNOSTICO))
                    : [],
            ) : null),
        ];
    }
}
