<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Equipo con su progreso por fases, miembros, entregables y reflexiones — seguimiento
 * docente (Mis equipos, detalle del encuentro, ficha del proyecto).
 *
 * Requiere cargadas: microproyecto.familia, miembros, fases, reflexiones, prototipos.
 */
class EquipoProgresoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $proyecto = $this->microproyecto;

        return [
            'id'              => $this->id,
            'nombre'          => $this->nombre,
            'proyecto'        => $proyecto ? [
                'uuid'               => $proyecto->uuid,
                'titulo'             => $proyecto->titulo,
                'estado'             => $proyecto->estado,
                'evaluacion_oficial' => $proyecto->evaluacion_oficial,
                'familia'            => $proyecto->familia?->nombre,
                'microreto_id'       => $proyecto->microreto_id,
            ] : null,
            'codigo_acceso'   => $this->codigo_acceso,
            'token'           => $this->token,
            'fase_actual'     => $this->fase_actual,
            'fases_completas' => $this->fases->filter(fn ($f) => $f->completada)->count(),
            'diagnostico_final'       => $this->diagnostico_final,
            'diagnostico_generado_en' => $this->diagnostico_generado_en,
            'miembros'        => $this->miembros->map(fn ($m) => [
                'id'         => $m->id,
                'nombre'     => $m->nombre,
                'alias'      => $m->alias,
                'rol'        => $m->rol,
                'fortalezas' => $m->fortalezas,
                'puntos_mejora' => $m->puntos_mejora,
            ]),
            'fases'           => collect(range(0, 4))->map(function ($n) {
                $fase = $this->fases->firstWhere('numero_fase', $n);
                return [
                    'numero_fase'           => $n,
                    'completada'            => $fase?->completada ?? false,
                    'validado_docente'      => $fase?->validado_docente ?? false,
                    'nota_docente'          => $fase?->nota_docente,
                    'observaciones_docente' => $fase?->observaciones_docente,
                    'datos'                 => $fase?->datos,
                    'fecha_completada'      => $fase?->fecha_completada,
                ];
            }),
            // Archivos de F3 "Entrega de la solución" tal cual se suben desde el workspace
            // del alumnado (equipo_prototipos, contexto='entregable'), con su mime y nombre real.
            'archivos_entregable' => $this->prototipos
                ->where('contexto', 'entregable')
                ->map(fn ($p) => [
                    'id'       => $p->id,
                    'filename' => $p->filename,
                    'url'      => $p->url,
                    'mime'     => $p->mime,
                    'size'     => $p->size,
                ])->values(),
            'reflexiones'     => $this->reflexiones->map(fn ($r) => [
                'id'           => $r->id,
                'tipo'         => $r->tipo,
                'autor_nombre' => $r->autor_nombre,
                'respuestas'   => $r->respuestas,
                'created_at'   => $r->created_at,
            ]),
        ];
    }
}
