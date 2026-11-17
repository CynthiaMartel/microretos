<?php

namespace App\Services;

use App\Models\Encuentro;
use App\Models\EquipoMiembro;
use App\Models\RegistroHoras;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Registro de horas de prácticas (A3). Lo anotan el docente (con edición sobre el cronograma) y
 * DuaLab (superadmin), en la reunión con el tutor de empresa.
 */
class RegistroHorasService
{
    // Cronograma de un plan que el usuario puede editar (404 si no: no se revela que existe)
    public function cronogramaEditable(User $user, int $encuentroId): Encuentro
    {
        return Encuentro::editablesPara($user)->where('tipo', Encuentro::TIPO_PLAN)->findOrFail($encuentroId);
    }

    /** @param array{miembro_id: int, fecha: string, horas: float|string, descripcion?: ?string} $datos */
    public function anotar(Encuentro $cronograma, User $autor, array $datos): RegistroHoras
    {
        $miembro = EquipoMiembro::with('equipo')->findOrFail($datos['miembro_id']);
        if ((int) $miembro->equipo?->encuentro_id !== (int) $cronograma->id) {
            throw ValidationException::withMessages(['miembro_id' => 'Ese alumno/a no está en este plan.']);
        }

        $registro = new RegistroHoras([
            'fecha'       => $datos['fecha'],
            'horas'       => $datos['horas'],
            'descripcion' => $datos['descripcion'] ?? null,
        ]);
        $registro->encuentro_id      = $cronograma->id;
        $registro->equipo_miembro_id = $miembro->id;
        $registro->registrado_por_id = $autor->id;
        $registro->save();

        return $registro;
    }
}
