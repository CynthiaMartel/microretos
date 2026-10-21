<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncuentroResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user           = $request->user();
        $esPropietario  = $user && $this->user_id === $user->id;
        // Misma regla que scopeEditablesPara (propietario, colaborador con edición, admin del centro, superadmin)
        $puedeEditar    = $this->resource->esEditablePor($user);

        return [
            'id'                 => $this->id,
            'fecha'              => $this->fecha?->format('Y-m-d'),
            'fecha_fin'          => $this->fecha_fin?->format('Y-m-d'),
            'centro_educativo'   => $this->centro_educativo,
            'ciclo_formativo'    => $this->ciclo_formativo,
            'curso'              => $this->curso,
            'grupo'              => $this->grupo,
            'num_alumnos'        => $this->num_alumnos,
            'notas'              => $this->notas,
            'num_equipos'        => $this->num_equipos,
            // Protección de datos: snapshot del reparto con lista blanca de campos (solo alias,
            // grupo y rol)
            'alumnados'          => collect((array) ($this->alumnados ?? []))->map(fn($a) => [
                'alias'      => $a['alias'] ?? null,
                'equipo_num' => $a['equipo_num'] ?? null,
                'rol'        => $a['rol'] ?? null,
            ])->values(),
            'equipos'            => $this->whenLoaded('equipos', fn() => $this->equipos->map(fn($e) => [
                'id'            => $e->id,
                'numero_equipo' => $e->numero_equipo,
                'nombre'        => $e->nombre,
                'fase_actual'   => $e->fase_actual,
                // El equipo confirmó sus alias en F0 (paso explícito) — el frontend usa esto
                // para bloquear el alias en "Editar equipo".
                'nombres_confirmados' => $e->nombres_confirmados,
                'miembros'      => $e->miembros->map(fn($m) => [
                    'id'     => $m->id,
                    'alias'  => $m->alias,
                    'rol'    => $m->rol,
                ]),
            ])),
            // Códigos de acceso solo para quien puede editar el encuentro (no a colaboradores de solo lectura)
            'codigo_clase'       => $puedeEditar ? $this->codigo_clase : null,
            'codigo_ia'          => $puedeEditar ? $this->codigo_ia : null,
            'microproyecto_uuid' => $this->microproyecto?->uuid,
            'proyecto_titulo'    => $this->microproyecto?->titulo,
            'microreto_id'       => $this->microproyecto?->microreto_id,
            'microreto_titulo'   => $this->microproyecto?->microreto?->titulo,
            // Contexto curricular del proyecto (cards de la biblioteca de encuentros)
            'familia_nombre'     => $this->microproyecto?->familia?->nombre,
            'modulos'            => $this->microproyecto?->nombresModulos() ?? [],
            'es_propietario'     => $esPropietario,
            'puede_editar'       => $puedeEditar,
            'propietario_nombre' => $this->docente?->name,
            'colaboradores'      => $esPropietario || $user?->isAdmin() || $user?->isSuperAdmin()
                ? $this->colaboradores->map(fn($c) => [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'puede_editar' => (bool) $c->pivot->puede_editar,
                ])
                : [],
        ];
    }
}
