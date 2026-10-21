<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Enlace de "¿Has olvidado tu contraseña?". Encolado: los emails nunca síncronos.
class RestablecerContrasena extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param \App\Models\User $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/') . '/restablecer-contrasena?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutos = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña de DUALAB')
            ->greeting('Hola, ' . $notifiable->name)
            ->line('Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en DUALAB.')
            ->action('Crear nueva contraseña', $url)
            ->line("El enlace caduca en {$minutos} minutos.")
            ->line('Si no has sido tú, ignora este correo: tu contraseña actual sigue siendo válida.')
            ->salutation('Equipo DUALAB');
    }
}
