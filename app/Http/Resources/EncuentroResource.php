<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncuentroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user           = $request->user();
        $esPropietario  = $user && $this->user_id === $user->id;
        $puedeEditar    = $esPropietario || ($user && $this->colaboradores->contains(
            fn($c) => $c->id === $user->id && $c->pivot->puede_editar
        ));

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
            'alumnados'          => $this->alumnados,
            'equipos'            => $this->whenLoaded('equipos', fn() => $this->equipos->map(fn($e) => [
                'id'            => $e->id,
                'numero_equipo' => $e->numero_equipo,
                'fase_actual'   => $e->fase_actual,
                // El equipo confirmó nombres en su F0 (paso explícito) — el frontend usa esto
                // para bloquear el nombre en "Editar equipo".
                'nombres_confirmados' => $e->nombres_confirmados,
                'miembros'      => $e->miembros->map(fn($m) => [
                    'id'     => $m->id,
                    'nombre' => $m->nombre,
                    'alias'  => $m->alias,
                    'rol'    => $m->rol,
                ]),
            ])),
            'codigo_clase'       => $this->codigo_clase,
            'codigo_ia'          => $this->codigo_ia,
            'microproyecto_uuid' => $this->microproyecto?->uuid,
            'proyecto_titulo'    => $this->microproyecto?->titulo,
            'microreto_id'       => $this->microproyecto?->microreto_id,
            'microreto_titulo'   => $this->microproyecto?->microreto?->titulo,
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
