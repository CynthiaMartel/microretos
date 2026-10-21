<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encuentro con sus equipos y progreso — GET /encuentros/mis-grupos (Mis equipos y
 * Biblioteca de diagnósticos). Forma: { encuentro: {...}, equipos: [...] }.
 *
 * Requiere cargadas: microproyecto.familia y las relaciones de EquipoProgresoResource
 * en equipos.
 *
 * @mixin \App\Models\Encuentro
 */
class EncuentroSeguimientoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $puedeEditar = $this->resource->esEditablePor($request->user());
        // EquipoProgresoResource decide con el encuentro de cada equipo si entrega sus códigos
        $this->equipos->each(fn ($e) => $e->setRelation('encuentro', $this->resource));

        return [
            'encuentro' => [
                'id'               => $this->id,
                'grupo'            => $this->grupo,
                'ciclo_formativo'  => $this->ciclo_formativo,
                'curso'            => $this->curso,
                'centro_educativo' => $this->centro_educativo,
                'fecha'            => $this->fecha,
                // Códigos de acceso solo para quien puede editar (no a colaboradores de solo lectura)
                'codigo_clase'     => $puedeEditar ? $this->codigo_clase : null,
                'codigo_ia'        => $puedeEditar ? $this->codigo_ia : null,
                'puede_editar'     => $puedeEditar,
                'es_propietario'   => (int) $this->user_id === (int) $request->user()?->id,
                'propietario_nombre' => $this->docente?->name,
                'proyecto_titulo'  => $this->microproyecto?->titulo,
                // Agrupación familia → módulo de Mis equipos (mismos nombres que EncuentroResource)
                'familia_nombre'   => $this->microproyecto?->familia?->nombre,
                'modulos'          => $this->microproyecto?->nombresModulos() ?? [],
            ],
            'equipos' => EquipoProgresoResource::collection($this->equipos),
        ];
    }
}
