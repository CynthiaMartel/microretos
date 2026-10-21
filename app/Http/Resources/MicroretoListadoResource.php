<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * GET /microretos (Biblioteca, Inicio docente, Taller de Ideas, Más familias): la ficha del
 * reto más lo que esas vistas usan para filtrar y vincular — ids, fecha, visibilidad y de
 * la empresa solo su identificación, centro y familias. Antes se devolvía el modelo en
 * crudo, con CIF, teléfono, email, contacto y dirección de la empresa.
 */
/** @mixin \App\Models\Microreto */
class MicroretoListadoResource extends MicroretoFichaResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ficha = parent::toArray($request);

        return array_merge($ficha, [
            'empresa_id'      => $this->empresa_id,
            'ciclo_id'        => $this->ciclo_id,
            'created_at'      => $this->created_at,
            'visible_publico' => (bool) $this->visible_publico,
            // Diagnóstico de la ficha + identificación (Taller de Ideas precarga centro y familia)
            'empresa' => $this->whenLoaded('empresa', fn () => $this->empresa ? array_merge(
                is_array($ficha['empresa'] ?? null) ? $ficha['empresa'] : [],
                [
                    'id'               => $this->empresa->id,
                    'nombre_comercial' => $this->empresa->nombre_comercial,
                    'centro_id'        => $this->empresa->centro_id,
                    'es_simulada'      => (bool) $this->empresa->es_simulada,
                    'familias'         => $this->empresa->relationLoaded('familias')
                        ? $this->empresa->familias->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()
                        : [],
                ],
            ) : null),
        ]);
    }
}
