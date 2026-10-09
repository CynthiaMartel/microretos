<?php

namespace App\Notifications;

use App\Models\Equipo;

// Un equipo de alumnado marca una fase como completada en su workspace. En los textos
// para el docente el equipo se llama "grupo" y la letra de clase, "Clase".
class EquipoCompletoFase extends NotificacionDocente
{
    // Mismos nombres que frontend-microretos/src/config/fasesProyecto.js
    private const FASES = [
        0 => 'Inicio del equipo',
        1 => 'Análisis del reto',
        2 => 'Diseño de solución y desarrollo',
        3 => 'Entrega de la solución',
        4 => 'Presentación',
    ];

    public function __construct(private Equipo $equipo, private int $fase) {}

    protected function tipo(): string
    {
        return 'equipo_fase';
    }

    protected function titulo(): string
    {
        return 'Un grupo ha completado una fase';
    }

    protected function mensaje(): string
    {
        // Nombre por defecto "Equipo N" → "Grupo N" (mismo criterio que utils/nombreGrupo.js)
        $nombre = trim((string) $this->equipo->nombre);
        $grupo  = preg_match('/^equipo\s+(\d+)$/i', $nombre, $m)
            ? "Grupo {$m[1]}"
            : ($nombre ?: 'Grupo ' . ($this->equipo->numero_equipo ?? ''));
        $clase  = $this->equipo->encuentro?->grupo;
        $fase   = self::FASES[$this->fase] ?? "Fase {$this->fase}";

        return trim($grupo . ($clase ? " (Clase {$clase})" : '') . " ha completado la fase «{$fase}». Revísala y valídala.");
    }

    protected function ruta(): ?string
    {
        return $this->equipo->encuentro_id ? "/mis-grupos/{$this->equipo->encuentro_id}" : '/mis-grupos';
    }
}
