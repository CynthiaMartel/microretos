<?php

namespace App\Services;

use App\Models\Encuentro;
use App\Models\EquipoMiembro;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Listado de alumnado participante (vista "Listado de alumnado"): una entrada por cada
 * miembro de equipo de los encuentros que el usuario puede ver, con su equipo, encuentro
 * y proyecto ya cargados para que AlumnoParticipacionResource no dispare queries por fila.
 *
 * No hay entidad "alumno" en BD: el alumnado existe solo como equipo_miembros (nombre
 * cifrado), así que la búsqueda por nombre y la agrupación por alumno se hacen en el
 * frontend sobre estas participaciones — en SQL el nombre cifrado no se puede filtrar.
 */
class AlumnadoService
{
    /**
     * Mismo techo que EncuentroController::MAX_MIS_GRUPOS: cada encuentro arrastra equipos,
     * miembros, fases, reflexiones y tareas (y un encuentro admite hasta 200 alumnos).
     */
    private const MAX_ENCUENTROS = 150;

    /** @return Collection<int, EquipoMiembro> */
    public function participaciones(User $user): Collection
    {
        $encuentros = Encuentro::with([
            'microproyecto:id,uuid,titulo,estado,familia_id',
            'microproyecto.familia:id,nombre',
            'equipos.microproyecto:id,uuid,titulo,estado,familia_id',
            'equipos.microproyecto.familia:id,nombre',
            'equipos.miembros',
            'equipos.fases',
            'equipos.reflexiones:id,equipo_id,tipo,autor_nombre,created_at',
            'equipos.tareas:id,equipo_id,responsable,estado,orden',
        ])->whereHas('equipos')->visiblesPara($user)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->take(self::MAX_ENCUENTROS)->get();

        // Relaciones inversas a mano: el Resource navega miembro → equipo → encuentro
        // sobre los modelos ya cargados, sin volver a consultar.
        return $encuentros->flatMap(fn (Encuentro $encuentro) => $encuentro->equipos->flatMap(function ($equipo) use ($encuentro) {
            $equipo->setRelation('encuentro', $encuentro);

            return $equipo->miembros->each(fn (EquipoMiembro $m) => $m->setRelation('equipo', $equipo));
        }))->values();
    }
}
