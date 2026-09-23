<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha pública de un Microproyecto completado (escaparate dualab.es/info.dualab.es,
 * sin autenticación). Whitelist explícita — replica el diseño de secciones de
 * StartupDayDetalle.vue/ProyectoFichaModal.vue en el backoffice, pero NUNCA expone lo
 * que ese detalle sí muestra a docentes/admins autenticados:
 *   - token_empresa (secreto de validación del proyecto)
 *   - datos_empresa (incluye persona_contacto/email reales)
 *   - datos_centro (incluye docente_nombre/docente_email reales)
 *   - equipo (nombres reales de alumnado, en su mayoría menores de edad)
 *   - validacion_empresa (comentarios internos de validación, no pensados para publicar)
 * Cualquier campo nuevo añadido a $fillable en Microproyecto/Empresa NO se expone
 * automáticamente aquí: hay que añadirlo a propósito, igual que en MicroretoFichaResource.
 *
 * ra_ce y recursos (vídeos/documentos) SÍ se exponen aquí a propósito — a diferencia
 * de equipo/datos_empresa/datos_centro, no contienen identidad real: el currículo es
 * público por naturaleza y los recursos de los proyectos marcados visible_publico son
 * datos de muestra generados (empresas ficticias), no material real de alumnado.
 */
class MicroproyectoFichaPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                 => $this->uuid,
            'titulo'               => $this->titulo,
            'curso'                => $this->curso,
            'empresa_nombre'       => $this->empresa?->nombre_comercial,
            'centro_nombre'        => $this->centroEducativo?->nombre,
            'ciclo_nombre'         => $this->cicloFormativo?->nombre,
            'familia'              => $this->familia?->nombre,
            'imagen_portada_url'   => $this->imagenPortada?->url,

            'diseno_reto' => $this->diseno_reto ? [
                'pregunta_reto' => $this->diseno_reto['pregunta_reto'] ?? null,
                'descripcion'   => $this->diseno_reto['descripcion'] ?? null,
                'restricciones' => $this->diseno_reto['restricciones'] ?? null,
                'entregables'   => $this->diseno_reto['entregables'] ?? null,
            ] : null,

            'fundamentacion' => $this->fundamentacion ? [
                'contexto'      => $this->fundamentacion['contexto'] ?? null,
                'justificacion' => $this->fundamentacion['justificacion'] ?? null,
                'innovacion'    => $this->fundamentacion['innovacion'] ?? null,
            ] : null,

            'modulos_seleccionados' => collect($this->modulos_seleccionados ?? [])
                ->map(fn ($m) => $m['nombre'] ?? null)->filter()->values(),
            'ra_ce' => $this->ra_ce,

            'fases' => collect($this->diseno_microproyecto['fases'] ?? [])
                ->map(fn ($f) => ['nombre' => $f['nombre'] ?? null, 'descripcion' => $f['descripcion'] ?? null])
                ->values(),
            'metodologia' => $this->diseno_microproyecto['metodologia'] ?? null,

            'objetivos' => $this->objetivos['lista'] ?? [],
            'kpis'      => $this->kpis['lista'] ?? [],
            'resumen'   => $this->resumen['texto'] ?? null,

            'videos' => $this->whenLoaded('recursos', fn () => $this->recursos
                ->where('tipo', 'video')
                ->map(fn ($r) => ['url' => $r->url, 'label' => $r->label, 'filename' => $r->filename])
                ->values()),
            'documentos' => $this->whenLoaded('recursos', fn () => $this->recursos
                ->where('tipo', 'documento')
                ->map(fn ($r) => ['url' => $r->url, 'label' => $r->label, 'filename' => $r->filename])
                ->values()),

            // Botón "Ver reto original" — solo si el reto vinculado también es público.
            'microreto_uuid' => ($this->microreto && $this->microreto->visible_publico)
                ? $this->microreto->uuid
                : null,
        ];
    }
}
