<?php

namespace App\Notifications;

use App\Models\Microproyecto;

// Un proyecto ya validado se ha modificado y vuelve a necesitar validación
class ProyectoPendienteValidacion extends NotificacionDocente
{
    public function __construct(private Microproyecto $proyecto) {}

    protected function tipo(): string
    {
        return 'pendiente_validar';
    }

    protected function titulo(): string
    {
        return 'Proyecto pendiente de validar';
    }

    protected function mensaje(): string
    {
        $titulo = $this->proyecto->titulo ?: 'Un proyecto';
        return "«{$titulo}» ha cambiado y necesita validarse de nuevo (empresa y docente).";
    }

    protected function ruta(): ?string
    {
        return "/proyectos/{$this->proyecto->uuid}";
    }
}
