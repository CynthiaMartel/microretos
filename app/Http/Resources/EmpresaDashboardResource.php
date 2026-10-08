<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Empresa para el dashboard de Base de datos (superadmin): lo mismo que EmpresaResource
 * más los campos de gestión que solo usa esa vista.
 */
class EmpresaDashboardResource extends EmpresaResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'fecha_cita'       => $this->fecha_cita,
            // Usado por BaseDatosDashboard, CentroEducativoModal y EliminarEmpresaModal
            'familias_nombres' => $this->whenLoaded('familias', fn () => $this->familias->pluck('nombre')->unique()->values()),
            // Alguna familia con módulos y RA/CE: las plantillas del catálogo sin ella se ocultan a los centros
            'disponible_para_retos' => $this->whenHas('disponible_para_retos', fn () => (bool) $this->disponible_para_retos),
        ];
    }
}
