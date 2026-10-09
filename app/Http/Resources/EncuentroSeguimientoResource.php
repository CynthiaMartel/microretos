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
 */
class EncuentroSeguimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'encuentro' => [
                'id'               => $this->id,
                'grupo'            => $this->grupo,
                'ciclo_formativo'  => $this->ciclo_formativo,
                'curso'            => $this->curso,
                'centro_educativo' => $this->centro_educativo,
                'fecha'            => $this->fecha,
                'codigo_clase'     => $this->codigo_clase,
                'codigo_ia'        => $this->codigo_ia,
                'proyecto_titulo'  => $this->microproyecto?->titulo,
                // Agrupación familia → módulo de Mis equipos (mismos nombres que EncuentroResource)
                'familia_nombre'   => $this->microproyecto?->familia?->nombre,
                'modulos'          => $this->microproyecto?->nombresModulos() ?? [],
            ],
            'equipos' => EquipoProgresoResource::collection($this->equipos),
        ];
    }
}
