<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base de los avisos in-app del docente (canal 'database', vista /notificaciones).
 * Solo inserta una fila: no se encola. Si en el futuro se añade 'mail', la subclase
 * debe implementar ShouldQueue (los emails nunca síncronos).
 *
 * Los datos guardados son de presentación (títulos y rutas internas): nunca emails,
 * nombres de alumnado ni tokens públicos.
 */
abstract class NotificacionDocente extends Notification
{
    abstract protected function tipo(): string;
    abstract protected function titulo(): string;
    abstract protected function mensaje(): string;
    abstract protected function ruta(): ?string;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo'    => $this->tipo(),
            'titulo'  => $this->titulo(),
            'mensaje' => $this->mensaje(),
            'ruta'    => $this->ruta(),
        ];
    }
}
