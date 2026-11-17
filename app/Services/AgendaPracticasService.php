<?php

namespace App\Services;

use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agenda de prácticas (A1/A2): los cronogramas de los planes de proyecto con sus hitos fechados y,
 * por grupo, en qué fase va frente a la que toca por fecha. La usan el docente (sus planes, app
 * DuaLab) y DuaLab (todos, Central): la visibilidad la decide Encuentro::visiblesPara.
 */
class AgendaPracticasService
{
    private const MAX_CRONOGRAMAS = 200;

    /** @return Collection<int, array<string, mixed>> */
    public function cronogramas(User $user, ?Carbon $hoy = null): Collection
    {
        $hoy ??= now()->startOfDay();

        return Encuentro::visiblesPara($user)
            ->where('tipo', Encuentro::TIPO_PLAN)
            ->with(['docente:id,name', 'colaboradores', 'microproyecto.empresa', 'equipos.fases',
                    // Horas de prácticas por alumno/a (A3): suma en la misma consulta, sin N+1
                    'equipos.miembros' => fn ($q) => $q->withSum('registrosHoras', 'horas')])
            ->orderBy('fecha')
            ->take(self::MAX_CRONOGRAMAS)
            ->get()
            ->map(fn (Encuentro $e) => $this->cronograma($e, $hoy) + [
                // Anotar horas: solo quien puede editar el cronograma (no un colaborador de solo lectura)
                'puede_editar' => $e->esEditablePor($user),
            ]);
    }

    /** @return array<string, mixed> */
    private function cronograma(Encuentro $e, Carbon $hoy): array
    {
        $hitos = collect($e->sesiones())->values()->map(fn ($h, $i) => [
            'numero' => $i + 1,
            'fecha'  => $h['fecha'] ?? null,
            'fases'  => array_values($h['fases'] ?? []),
        ]);

        // Hitos ya alcanzados por fecha → fase más avanzada que debería estar trabajándose
        $alcanzados = $hitos->filter(fn ($h) => $h['fecha'] && Carbon::parse($h['fecha'])->lte($hoy));
        $faseEsperada = $alcanzados->isEmpty() ? null : max(array_merge([0], ...$alcanzados->pluck('fases')->all()));
        $siguiente = $hitos->first(fn ($h) => $h['fecha'] && Carbon::parse($h['fecha'])->gt($hoy));

        $mp = $e->microproyecto;
        $estadoGeneral = match (true) {
            $e->fecha && Carbon::parse($e->fecha)->gt($hoy)              => 'por_empezar',
            $e->fecha_fin && Carbon::parse($e->fecha_fin)->lt($hoy)      => 'terminado',
            default                                                       => 'en_curso',
        };

        return [
            'id'              => $e->id,
            'plan'            => [
                'uuid'            => $mp?->uuid,
                'titulo'          => $mp?->titulo,
                'empresa'         => $mp?->empresa?->nombre_comercial ?? ($mp?->datos_empresa['nombre'] ?? null),
                'horas_estimadas' => $mp?->horas_estimadas,
            ],
            'docente'         => $e->docente?->name,
            'centro'          => $e->centro_educativo,
            'fecha'           => $e->fecha?->format('Y-m-d'),
            'fecha_fin'       => $e->fecha_fin?->format('Y-m-d'),
            'estado'          => $estadoGeneral,
            'hitos'           => $hitos->all(),
            'hito_actual'     => $alcanzados->last()['numero'] ?? null,
            'siguiente_hito'  => $siguiente,
            'fase_esperada'   => $faseEsperada,
            'grupos'          => $e->equipos->map(fn (Equipo $g) => $this->grupo($g, $faseEsperada))->values()->all(),
            'horas_total'     => round((float) $e->equipos->flatMap->miembros->sum('registros_horas_sum_horas'), 2),
            // Reparto antes de generar los grupos (solo alias)
            'alumnado_pendiente' => $e->equipos->isEmpty() ? collect((array) $e->alumnados)->pluck('alias')->filter()->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function grupo(Equipo $g, ?int $faseEsperada): array
    {
        $completadas = $g->fases->where('completada', true)->count();

        $estado = match (true) {
            $completadas >= 5                                              => 'terminado',
            $faseEsperada === null                                         => 'al_dia',
            (int) $g->fase_actual < $faseEsperada                          => 'retrasado',
            default                                                        => 'al_dia',
        };

        return [
            'id'                => $g->id,
            'nombre'            => $g->numero_equipo ? "Grupo {$g->numero_equipo}" : ($g->nombre ?: 'Grupo'),
            'fase_actual'       => (int) $g->fase_actual,
            'fases_completadas' => $completadas,
            'progreso'          => (int) round($completadas / 5 * 100),
            'estado'            => $estado,
            'miembros'          => $g->miembros->map(fn ($m) => [
                'id'        => $m->id,
                'alias'     => $m->alias,
                'destacado' => $m->esDestacado(),
                'horas'     => round((float) ($m->registros_horas_sum_horas ?? 0), 2),
            ])->values()->all(),
        ];
    }
}
