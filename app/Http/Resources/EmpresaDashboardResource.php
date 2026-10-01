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
            'familias_nombres' => $this->whenLoaded('familias', fn () => $this->familias->pluck('nombre')->values()),
        ];
    }
}
