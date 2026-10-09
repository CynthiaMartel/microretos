<?php

namespace App\Notifications;

use App\Models\Encuentro;
use App\Models\User;

// Otro docente comparte contigo uno de sus encuentros
class InvitacionEncuentro extends NotificacionDocente
{
    public function __construct(private Encuentro $encuentro, private User $invitador, private bool $puedeEditar) {}

    protected function tipo(): string
    {
        return 'invitacion_encuentro';
    }

    protected function titulo(): string
    {
        return 'Te han invitado a un encuentro';
    }

    protected function mensaje(): string
    {
        $grupo  = $this->encuentro->grupo ? " de la clase {$this->encuentro->grupo}" : '';
        $acceso = $this->puedeEditar ? 'con permiso de edición' : 'en modo lectura';

        return "{$this->invitador->name} ha compartido contigo un encuentro{$grupo} {$acceso}.";
    }

    protected function ruta(): ?string
    {
        return "/mis-grupos/{$this->encuentro->id}";
    }
}
