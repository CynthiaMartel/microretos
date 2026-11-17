<?php

namespace App\Services;

use App\Models\Encuentro;
use App\Models\EncuentroValoracionEmpresa;

/**
 * Valoración final de la empresa sobre la propuesta trabajada en un encuentro creativo (P3).
 * El docente la pide cuando quiere (normalmente, cuando los grupos han entregado su solución en
 * F3): se crea un enlace con token que caduca y la empresa responde una sola vez.
 */
class ValoracionEmpresaService
{
    public function __construct(private NotificacionDocenteService $notificaciones) {}

    // Crea la valoración del encuentro o, si su enlace caducó sin respuesta, lo renueva
    public function solicitar(Encuentro $encuentro): EncuentroValoracionEmpresa
    {
        $valoracion = $encuentro->valoracionEmpresa ?? $encuentro->valoracionEmpresa()->make();

        if ($valoracion->exists && $valoracion->caducado() && !$valoracion->respondida()) {
            $valoracion->renovarToken();
            $valoracion->enviado_en = null;
        }

        $valoracion->save();

        return $valoracion;
    }

    public function marcarEnviada(EncuentroValoracionEmpresa $valoracion): void
    {
        $valoracion->enviado_en = now();
        $valoracion->save();
    }

    /** @param array{valoracion_global: int, respuestas: array<string, string>, comentarios?: ?string} $datos */
    public function responder(EncuentroValoracionEmpresa $valoracion, array $datos): void
    {
        $valoracion->fill($datos);
        $valoracion->respondido_en = now();
        $valoracion->save();

        $this->notificaciones->empresaValoroPropuesta($valoracion->encuentro);
    }
}
