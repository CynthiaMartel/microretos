<?php

namespace App\Services;

use App\Models\ActividadCentral;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Historial de DuaLab Central: cada acción que cambia algo importante deja aquí qué se hizo, quién y
 * cuándo. Las críticas (validar o descartar empresas, papelera, publicar retos, cuentas…) van marcadas
 * para verlas de un vistazo. Nunca se guardan correos ni contraseñas en la descripción.
 */
class ActividadCentralService
{
    public function registrar(?User $usuario, string $seccion, string $accion, string $descripcion, ?object $entidad = null, bool $critica = false): void
    {
        $registro = new ActividadCentral();
        $registro->forceFill([
            'user_id'        => $usuario?->id,
            'usuario_nombre' => $usuario?->name ?? 'Sistema',
            'usuario_rol'    => $usuario?->roleName() ?? '',
            'seccion'        => $seccion,
            'accion'         => $accion,
            'descripcion'    => Str::limit($descripcion, 495),
            'entidad_tipo'   => $entidad ? Str::snake(class_basename($entidad)) : null,
            'entidad_id'     => $entidad?->id ?? null,
            'critica'        => $critica,
        ])->save();
    }
}
