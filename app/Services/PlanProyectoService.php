<?php

namespace App\Services;

use App\Models\Microproyecto;
use App\Models\Microreto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Plan de proyecto (etapa 2, P4): el trabajo ampliado que hace en la empresa, durante las prácticas,
 * el alumnado seleccionado. Es un microproyecto con etapa 'plan' — reutiliza asistente, validación
 * (docente o empresa), encuentros (de tipo plan, el cronograma) y workspace.
 */
class PlanProyectoService
{
    // Lo curricular y el contexto que hereda el plan de su propuesta (luego se amplía en el asistente)
    private const CAMPOS_HEREDADOS = [
        'microreto_id', 'empresa_id', 'centro_id', 'familia_id', 'ciclo_id', 'curso',
        'datos_empresa', 'datos_centro', 'equipo', 'modulos_seleccionados', 'ra_ce', 'evaluacion_oficial',
        'fundamentacion', 'diseno_reto', 'diseno_microproyecto', 'resumen', 'objetivos', 'kpis',
    ];

    /** @param array{origen_uuid?: ?string, microreto_id?: ?int, titulo?: ?string, horas_estimadas?: ?int, expectativas_empresa?: ?string} $datos */
    public function crear(User $autor, array $datos): Microproyecto
    {
        return !empty($datos['origen_uuid'])
            ? $this->desdePropuesta($autor, $datos)
            : $this->desdeReto($autor, $datos);
    }

    private function desdePropuesta(User $autor, array $datos): Microproyecto
    {
        $origen = Microproyecto::where('uuid', $datos['origen_uuid'])->firstOrFail();

        if (!$origen->esVisiblePara($autor)) {
            throw new AuthorizationException('No tienes acceso a esa propuesta.');
        }
        if ($origen->esPlan() || !in_array($origen->estado, ['validado', 'completado'], true)) {
            throw ValidationException::withMessages([
                'origen_uuid' => 'El plan se crea a partir de una propuesta del taller ya validada o completada.',
            ]);
        }

        $plan = new Microproyecto(array_merge(
            $origen->only(self::CAMPOS_HEREDADOS),
            $this->datosPropios($datos, 'Plan de proyecto: ' . $origen->titulo),
        ));
        $plan->origen_id = $origen->id;
        // Si lo crea DuaLab (superadmin), el plan queda a nombre del docente de la propuesta para que lo vea
        $plan->user_id = $autor->isSuperAdmin() ? ($origen->user_id ?? $autor->id) : $autor->id;

        return $this->guardar($plan);
    }

    private function desdeReto(User $autor, array $datos): Microproyecto
    {
        $reto = Microreto::findOrFail($datos['microreto_id']);

        if (!$autor->isSuperAdmin() && !$reto->perteneceAlCentroDe($autor)) {
            throw new AuthorizationException('Ese reto no es de tu centro.');
        }

        $plan = new Microproyecto(array_merge(
            ['microreto_id' => $reto->id, 'empresa_id' => $reto->empresa_id],
            $this->datosPropios($datos, 'Plan de proyecto: ' . $reto->titulo),
        ));
        $plan->centro_id = $autor->isSuperAdmin() ? $reto->centro_id : $autor->centro_educativo_id;
        $plan->user_id = $autor->id;

        return $this->guardar($plan);
    }

    /** @return array<string, mixed> */
    private function datosPropios(array $datos, string $tituloPorDefecto): array
    {
        return [
            'titulo'               => ($datos['titulo'] ?? null) ?: mb_substr($tituloPorDefecto, 0, 255),
            'horas_estimadas'      => $datos['horas_estimadas'] ?? null,
            'expectativas_empresa' => $datos['expectativas_empresa'] ?? null,
            'estado'               => 'en_edicion',
            'paso_actual'          => 1,
        ];
    }

    private function guardar(Microproyecto $plan): Microproyecto
    {
        $plan->etapa = Microproyecto::ETAPA_PLAN;
        $plan->save();

        return $plan;
    }
}
