<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Participación de un alumno (EquipoMiembro) en un encuentro: su equipo, el proyecto que
 * trabaja, el avance por fases y la nota final. Espera las relaciones que precarga
 * AlumnadoService (equipo con encuentro, microproyecto, miembros, fases, reflexiones, tareas).
 *
 * Deliberadamente sin token ni código de acceso del equipo, ni contenido de las fases:
 * eso vive en Mis grupos / detalle del encuentro, a donde enlaza la vista.
 */
class AlumnoParticipacionResource extends JsonResource
{
    // Comparación tolerante entre el nombre/alias del miembro y los textos libres que
    // escribe el alumnado (autor de reflexión, responsable de tarea).
    private static function normalizar(?string $texto): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $texto)));
    }

    public function toArray(Request $request): array
    {
        $equipo    = $this->equipo;
        $encuentro = $equipo->encuentro;
        $proyecto  = $equipo->microproyecto ?? $encuentro->microproyecto;

        $identidades = collect([$this->nombre, $this->alias])
            ->map(fn ($t) => self::normalizar($t))->filter()->unique();
        $esSuyo = fn (?string $texto) => $identidades->contains(self::normalizar($texto));

        $fasesCompletas = $equipo->fases->filter(fn ($f) => $f->completada)->count();
        $fase4          = $equipo->getFase(4);
        $evaluacion     = $fase4?->datos['evaluacion_docente'] ?? null;
        $notaFinal      = $fase4?->nota_docente ?? ($evaluacion['nota_opcional'] ?? null);
        $tareas         = $equipo->tareas->filter(fn ($t) => $esSuyo($t->responsable));

        return [
            'id'            => $this->id,
            'nombre'        => $this->nombre,
            'alias'         => $this->alias,
            'rol'           => $this->rol,
            'fortalezas'    => $this->fortalezas ?? [],
            'puntos_mejora' => $this->puntos_mejora ?? [],

            'equipo' => [
                'id'              => $equipo->id,
                'nombre'          => $equipo->nombre,
                'numero_equipo'   => $equipo->numero_equipo,
                'fase_actual'     => $equipo->fase_actual,
                'fases_completas' => $fasesCompletas,
                'companeros'      => $equipo->miembros
                    ->reject(fn ($m) => $m->id === $this->id)
                    ->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'rol' => $m->rol])
                    ->values(),
                'fases' => collect(range(0, 4))->map(function ($n) use ($equipo) {
                    $fase = $equipo->getFase($n);
                    return [
                        'numero_fase'      => $n,
                        'completada'       => $fase?->completada ?? false,
                        'validado_docente' => $fase?->validado_docente ?? false,
                        'nota_docente'     => $fase?->nota_docente,
                        'fecha_completada' => $fase?->fecha_completada,
                    ];
                }),
                'nota_final'            => $notaFinal !== null ? (float) $notaFinal : null,
                'observaciones_docente' => $fase4?->observaciones_docente,
                // Nº de RA por nivel en la evaluación curricular final (no el detalle)
                'niveles_ra' => collect($evaluacion['ras'] ?? [])->countBy('nivel'),
                'tiene_diagnostico' => !empty($equipo->diagnostico_final),
            ],

            'encuentro' => [
                'id'               => $encuentro->id,
                'fecha'            => $encuentro->fecha?->format('Y-m-d'),
                'fecha_fin'        => $encuentro->fecha_fin?->format('Y-m-d'),
                'grupo'            => $encuentro->grupo,
                'curso'            => $encuentro->curso,
                'ciclo_formativo'  => $encuentro->ciclo_formativo,
                'centro_educativo' => $encuentro->centro_educativo,
            ],

            'proyecto' => $proyecto ? [
                'uuid'    => $proyecto->uuid,
                'titulo'  => $proyecto->titulo,
                'estado'  => $proyecto->estado,
                'familia' => $proyecto->familia?->nombre,
            ] : null,

            'reflexion_individual' => $equipo->reflexiones
                ->contains(fn ($r) => $r->tipo === 'individual' && $esSuyo($r->autor_nombre)),
            'tareas' => [
                'asignadas'  => $tareas->count(),
                'realizadas' => $tareas->where('estado', 'realizado')->count(),
            ],
        ];
    }
}
