<?php

namespace App\Http\Resources;

use App\Support\UrlSegura;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encuentro en DuaLab Central → Alumnado destacado (solo superadmin): la propuesta, la valoración
 * de la empresa, la entrega de cada grupo y sus alias marcables. Solo alias: DuaLab pide los datos
 * reales al docente fuera de la plataforma. Recurso = Encuentro con docente, valoracionEmpresa,
 * microproyecto.empresa y equipos.{miembros,fases} cargados.
 */
class DestacadoEncuentroResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mp = $this->microproyecto;
        $v  = $this->valoracionEmpresa;

        return [
            'id'               => $this->id,
            'fecha'            => $this->fecha?->format('Y-m-d'),
            'curso'            => $this->curso,
            'grupo'            => $this->grupo,
            'centro_educativo' => $this->centro_educativo,
            'docente'          => $this->docente?->name,
            'propuesta'        => [
                'uuid'    => $mp?->uuid,
                'titulo'  => $mp?->titulo,
                'empresa' => $mp?->empresa?->nombre_comercial ?? ($mp?->datos_empresa['nombre'] ?? null),
            ],
            'valoracion' => $v?->respondida() ? [
                'global'                 => $v->valoracion_global,
                'interes_plan_practicas' => $v->respuestas['interes_plan_practicas'] ?? null,
                'comentarios'            => $v->comentarios,
                'respondido_en'          => $v->respondido_en?->toIso8601String(),
            ] : null,
            'equipos' => $this->equipos->map(function ($e) {
                $f3 = $e->fases->firstWhere('numero_fase', 3);

                return [
                    'id'                => $e->id,
                    'nombre'            => $e->numero_equipo ? "Grupo {$e->numero_equipo}" : ($e->nombre ?: 'Grupo'),
                    'fases_completadas' => $e->fases->where('completada', true)->count(),
                    'entrega'           => $f3?->completada ? [
                        'descripcion' => $f3->datos['descripcion_entregable'] ?? null,
                        'url'         => UrlSegura::http($f3->datos['url_entregable'] ?? null),
                    ] : null,
                    'miembros' => $e->miembros->map(fn ($m) => [
                        'id'           => $m->id,
                        'alias'        => $m->alias,
                        'rol'          => $m->rol,
                        'destacado'    => $m->esDestacado(),
                        'destacado_en' => $m->destacado_en?->toIso8601String(),
                    ])->values(),
                ];
            })->values(),
        ];
    }
}
