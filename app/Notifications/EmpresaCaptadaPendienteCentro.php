<?php

namespace App\Notifications;

use App\Models\Empresa;
use App\Models\User;
use App\Services\CaptacionEmpresaService;

// Un comercial ha firmado la entrevista de una empresa nueva: hay que colocarla en centros (superadmin)
class EmpresaCaptadaPendienteCentro extends NotificacionDocente
{
    public function __construct(private Empresa $empresa, private User $comercial) {}

    protected function tipo(): string
    {
        return 'empresa_captada';
    }

    protected function titulo(): string
    {
        return 'Empresa nueva pendiente de colocar en centro';
    }

    protected function mensaje(): string
    {
        // Nombre de la empresa y del comercial (cuenta interna), nunca datos del firmante
        $mensaje = "{$this->comercial->name} ha entrevistado a «{$this->empresa->nombre_comercial}». Elige en qué centros queda disponible.";
        $faltan = CaptacionEmpresaService::datosIncompletos($this->empresa);
        return $faltan ? $mensaje . ' Datos incompletos: falta ' . implode(', ', $faltan) . '.' : $mensaje;
    }

    protected function ruta(): ?string
    {
        return '/notificaciones?apartado=captacion';
    }
}
