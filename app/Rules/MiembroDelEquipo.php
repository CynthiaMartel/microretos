<?php

namespace App\Rules;

use App\Models\Equipo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El valor tiene que ser el nombre de un miembro del propio equipo (rutas públicas
 * /equipo/{token}/…): responsable de una tarea o autor de una reflexión individual.
 * EquipoWorkspace.vue los elige en un desplegable con los miembros; aquí se impide
 * que llegue cualquier otro texto. Vacío = sin valor (lo decide required/nullable).
 *
 * La comparación se hace en PHP: equipo_miembros.nombre está cifrado y no se puede
 * filtrar en SQL.
 */
class MiembroDelEquipo implements ValidationRule
{
    public function __construct(private ?string $tokenEquipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $equipo = $this->tokenEquipo
            ? Equipo::with('miembros')->where('token', $this->tokenEquipo)->first()
            : null;

        // Sin equipo, el controller devolverá 404: aquí no se adelanta ese error
        if (!$equipo) {
            return;
        }

        $valor = trim((string) $value);
        if (!$equipo->miembros->contains(fn ($m) => trim((string) $m->nombre) === $valor)) {
            $fail('Elige un miembro del equipo.');
        }
    }
}
