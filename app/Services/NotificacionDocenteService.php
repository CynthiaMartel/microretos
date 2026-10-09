<?php

namespace App\Services;

use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\Microproyecto;
use App\Models\User;
use App\Notifications\EmpresaRespondioPropuesta;
use App\Notifications\EquipoCompletoFase;
use App\Notifications\InvitacionEncuentro;
use App\Notifications\ProyectoPendienteValidacion;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Decide QUIÉN recibe cada aviso in-app. Los controllers solo llaman al método del evento.
 * Un fallo al notificar nunca rompe la acción que lo dispara (se registra y sigue):
 * varios eventos llegan por rutas públicas (empresa, alumnado).
 */
class NotificacionDocenteService
{
    public function empresaRespondio(Microproyecto $proyecto, bool $validada): void
    {
        $this->enviar($this->duenoProyecto($proyecto), new EmpresaRespondioPropuesta($proyecto, $validada));
    }

    public function proyectoPendienteValidacion(Microproyecto $proyecto, ?User $autor = null): void
    {
        // Quien edita no necesita que le avisen de su propio cambio
        $destinatarios = $this->duenoProyecto($proyecto)
            ->reject(fn (User $u) => $autor && $u->id === $autor->id);

        $this->enviar($destinatarios, new ProyectoPendienteValidacion($proyecto));
    }

    public function equipoCompletoFase(Equipo $equipo, int $fase): void
    {
        $encuentro = $equipo->encuentro;
        if (!$encuentro) return;

        // Propietario del encuentro + colaboradores (con o sin edición: todos siguen al equipo)
        $destinatarios = collect([$encuentro->docente])
            ->merge($encuentro->colaboradores)
            ->filter()
            ->unique('id');

        $this->enviar($destinatarios, new EquipoCompletoFase($equipo, $fase));
    }

    public function invitacionEncuentro(Encuentro $encuentro, User $invitado, User $invitador, bool $puedeEditar): void
    {
        $this->enviar(collect([$invitado]), new InvitacionEncuentro($encuentro, $invitador, $puedeEditar));
    }

    private function duenoProyecto(Microproyecto $proyecto): Collection
    {
        // user_id es nullable (proyectos antiguos) — sin dueño no hay a quién avisar
        return collect([$proyecto->docente])->filter();
    }

    private function enviar(Collection $destinatarios, BaseNotification $notificacion): void
    {
        if ($destinatarios->isEmpty()) return;

        try {
            Notification::send($destinatarios, $notificacion);
        } catch (\Throwable $e) {
            // Solo la clase y el mensaje de error: sin datos personales
            Log::warning('No se pudo registrar la notificación ' . class_basename($notificacion) . ': ' . $e->getMessage());
        }
    }
}
