<?php

namespace App\Notifications;

use App\Models\CentroCaptacion;
use App\Models\User;

// Ha llegado un centro educativo (entrevista del comercial o formulario web): hay que validarlo (superadmin)
class CentroCaptadoPendienteValidacion extends NotificacionDocente
{
    public function __construct(private CentroCaptacion $captacion, private ?User $comercial) {}

    protected function tipo(): string
    {
        return 'centro_captado';
    }

    protected function titulo(): string
    {
        return 'Centro nuevo pendiente de validar';
    }

    protected function mensaje(): string
    {
        // Nombre del centro y de quien lo captó, nunca los datos de la persona de contacto
        $quien = $this->comercial ? "{$this->comercial->name} ha entrevistado a" : 'Ha llegado por el formulario web';
        return "{$quien} «{$this->captacion->centro_nombre}». Valídalo para darlo de alta en DuaLab.";
    }

    protected function ruta(): ?string
    {
        return '/notificaciones?apartado=captacion';
    }
}
