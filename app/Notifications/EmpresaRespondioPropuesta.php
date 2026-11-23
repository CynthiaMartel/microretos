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
        return $this->validada ? 'La empresa ha validado tu reto ajustado' : 'La empresa pide esperar para validar';
    }

    protected function mensaje(): string
    {
        // La columna es nombre_comercial: con ->nombre el aviso decía siempre «La empresa»
        $empresa = $this->proyecto->empresa->nombre_comercial ?? 'La empresa';
        $titulo  = $this->proyecto->titulo ?: 'tu reto ajustado';

        if (!$this->validada) {
            return "{$empresa} ha respondido «no validar aún» a «{$titulo}». Revisa sus comentarios.";
        }
        return "{$empresa} ha validado «{$titulo}». Ya puedes llevarlo al aula en un encuentro.";
    }

    protected function ruta(): ?string
    {
        return "/proyectos/{$this->proyecto->uuid}";
    }
}
