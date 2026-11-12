<?php

namespace App\Notifications;

use App\Models\EmpresaCaptacion;
use App\Models\User;
use App\Services\CaptacionEmpresaService;

// Ha llegado una empresa (entrevista firmada por un comercial o formulario web): hay que validarla y colocarla en centros (superadmin)
class EmpresaCaptadaPendienteValidacion extends NotificacionDocente
{
    public function __construct(private EmpresaCaptacion $captacion, private ?User $comercial) {}

    protected function tipo(): string
    {
        return 'empresa_captada';
    }

    protected function titulo(): string
    {
        return 'Empresa nueva pendiente de validar';
    }

    protected function mensaje(): string
    {
        // Nombre de la empresa y del comercial (cuenta interna), nunca datos del firmante
        $quien = $this->comercial ? "{$this->comercial->name} ha entrevistado a" : 'Ha llegado por el formulario web';
        $mensaje = "{$quien} «{$this->captacion->nombre_comercial}». Valídala y elige en qué centros queda disponible.";
        $faltan = CaptacionEmpresaService::datosIncompletos($this->captacion);
        return $faltan ? $mensaje . ' Datos incompletos: falta ' . implode(', ', $faltan) . '.' : $mensaje;
    }

    protected function ruta(): ?string
    {
        return '/notificaciones?apartado=captacion';
    }
}
