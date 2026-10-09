<?php

namespace App\Notifications;

use App\Models\Microproyecto;

// La empresa responde a la propuesta desde su enlace: la valida o pide esperar
class EmpresaRespondioPropuesta extends NotificacionDocente
{
    public function __construct(private Microproyecto $proyecto, private bool $validada) {}

    protected function tipo(): string
    {
        return $this->validada ? 'propuesta_validada' : 'propuesta_no_validada_aun';
    }

    protected function titulo(): string
    {
        return $this->validada ? 'La empresa ha validado tu propuesta' : 'La empresa pide esperar para validar';
    }

    protected function mensaje(): string
    {
        $empresa = $this->proyecto->empresa?->nombre ?? 'La empresa';
        $titulo  = $this->proyecto->titulo ?: 'tu proyecto';

        if (!$this->validada) {
            return "{$empresa} ha respondido «no validar aún» a «{$titulo}». Revisa sus comentarios.";
        }
        return $this->proyecto->docente_validado
            ? "{$empresa} ha validado «{$titulo}»."
            : "{$empresa} ha validado «{$titulo}». Falta tu validación docente.";
    }

    protected function ruta(): ?string
    {
        return "/proyectos/{$this->proyecto->uuid}";
    }
}
