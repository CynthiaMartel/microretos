<?php

namespace App\Notifications;

use App\Models\Encuentro;

// La empresa ha valorado la propuesta trabajada en un encuentro creativo (valoración final, P3)
class EmpresaValoroPropuesta extends NotificacionDocente
{
    public function __construct(private Encuentro $encuentro) {}

    protected function tipo(): string
    {
        return 'propuesta_valorada';
    }

    protected function titulo(): string
    {
        return 'La empresa ha valorado el reto ajustado';
    }

    protected function mensaje(): string
    {
        $proyecto = $this->encuentro->microproyecto;
        $empresa  = $proyecto?->empresa?->nombre_comercial ?? 'La empresa';
        $titulo   = $proyecto?->titulo ?: 'el reto ajustado';

        return "{$empresa} ha valorado «{$titulo}» tras el trabajo de tus grupos. Consulta su valoración en el encuentro.";
    }

    protected function ruta(): ?string
    {
        return '/encuentros';
    }
}
