<?php

namespace App\Services;

use App\Models\Encuentro;
use App\Models\EquipoMiembro;
use App\Models\Microproyecto;
use App\Models\User;
use App\Support\AliasGenerator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Cronograma del plan de proyecto (P4b): un encuentro de tipo plan_practicas_etapa2 cuyas sesiones
 * son los hitos de las prácticas. Su alumnado sale de uno o varios encuentros (normalmente, el
 * destacado por DuaLab): se copian los alias al reparto del encuentro y, como en cualquier otro,
 * el docente genera después los grupos y sus códigos (EncuentroController::crearCodigo).
 */
class CronogramaPlanService
{
    /** @param array{fecha: string, fecha_fin?: ?string, num_equipos: int, miembros: array<int, array{id: int, equipo_num: int}>, planificacion_sesiones?: ?array} $datos */
    public function crear(Microproyecto $plan, User $autor, array $datos): Encuentro
    {
        if (!$plan->esPlan()) {
            throw ValidationException::withMessages(['plan' => 'El cronograma solo se crea en un plan de proyecto.']);
        }
        if (!$plan->esEditablePara($autor)) {
            throw new AuthorizationException('No puedes editar este plan.');
        }
        if (!in_array($plan->estado, ['validado', 'completado'], true)) {
            throw ValidationException::withMessages(['plan' => 'Valida el plan (tú o la empresa) antes de crear su cronograma.']);
        }

        $ids = collect($datos['miembros'])->pluck('id');
        $miembros = EquipoMiembro::with('equipo.encuentro')->whereIn('id', $ids)->get()->keyBy('id');

        // Solo alumnado de encuentros que el autor puede ver (DuaLab, todos)
        $visibles = Encuentro::visiblesPara($autor)
            ->whereIn('id', $miembros->pluck('equipo.encuentro_id')->filter()->unique())
            ->pluck('id');
        $ajeno = $miembros->first(fn ($m) => !$visibles->contains($m->equipo?->encuentro_id));
        if ($ajeno) {
            throw new AuthorizationException('Parte del alumnado elegido es de encuentros a los que no tienes acceso.');
        }

        // Reparto (solo alias): sin repetir alias aunque vengan de encuentros distintos
        $usados = [];
        $alumnados = collect($datos['miembros'])->map(function ($sel) use ($miembros, &$usados) {
            $alias = AliasGenerator::libre($miembros[$sel['id']]->alias, $usados);
            $usados[] = $alias;

            return ['alias' => $alias, 'equipo_num' => (int) $sel['equipo_num'], 'rol' => null];
        })->values()->all();

        $base = $miembros->first()->equipo->encuentro;

        $encuentro = new Encuentro([
            'microproyecto_id'       => $plan->id,
            'fecha'                  => $datos['fecha'],
            'planificacion_sesiones' => $datos['planificacion_sesiones'] ?? null,
            'centro_educativo'       => $base->centro_educativo,
            'centro_educativo_id'    => $base->centro_educativo_id,
            'ciclo_formativo'        => $base->ciclo_formativo,
            'curso'                  => $base->curso,
            'grupo'                  => $base->grupo,
            'num_equipos'            => $datos['num_equipos'],
            'num_alumnos'            => count($alumnados),
            'alumnados'              => $alumnados,
        ]);
        $encuentro->tipo = Encuentro::TIPO_PLAN;
        // Responsable: el docente del plan (si lo crea DuaLab, sigue siendo del docente)
        $encuentro->user_id = $plan->user_id ?? $autor->id;
        $encuentro->fecha_fin = $datos['fecha_fin']
            ?? $encuentro->fechaFinSugerida(Carbon::parse($datos['fecha']))?->toDateString();
        $encuentro->save();

        return $encuentro;
    }
}
