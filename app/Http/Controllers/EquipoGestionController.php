<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ValidarFaseEquipoRequest;
use App\Http\Requests\RechazarFaseEquipoRequest;
use App\Http\Requests\EvaluarEquipoRequest;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\Microproyecto;

class EquipoGestionController extends Controller
{
    // ── Validación docente de fases ───────────────────────────────────────────

    /**
     * PATCH /api/startup/equipos/{id}/fase/{fase}/validar
     * El docente valida la fase de un equipo, opcionalmente con nota y observaciones.
     */
    public function validarFase(ValidarFaseEquipoRequest $request, int $equipoId, int $numeroFase)
    {
        if (!in_array($numeroFase, [0, 1, 2, 3, 4])) {
            return response()->json(['error' => 'Fase no válida.'], 422);
        }

        $equipo = $this->equipoDeMiEncuentro($request, $equipoId);

        $data = $request->validated();

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            array_merge($data, [
                'validado_docente'         => true,
                'fecha_validacion_docente' => now(),
                'completada'               => true,
                'fecha_completada'         => now(),
            ])
        );

        return response()->json(['ok' => true, 'fase' => $fase]);
    }

    /**
     * PATCH /api/startup/equipos/{id}/fase/{fase}/rechazar
     * El docente devuelve la fase para que el equipo la revise.
     */
    public function rechazarFase(RechazarFaseEquipoRequest $request, int $equipoId, int $numeroFase)
    {
        $equipo = $this->equipoDeMiEncuentro($request, $equipoId);

        $data = $request->validated();

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            array_merge($data, [
                'validado_docente' => false,
                'completada'       => false,
            ])
        );

        // Retroceder fase_actual del equipo si estaba más adelante
        if ($equipo->fase_actual > $numeroFase) {
            $equipo->update(['fase_actual' => $numeroFase]);
        }

        return response()->json(['ok' => true, 'fase' => $fase]);
    }

    /**
     * PATCH /api/startup/equipos/{id}/evaluar
     * Evaluación final del docente en F4: RA + niveles + nota global.
     * Guarda en equipo_fases.datos de la fase 4.
     */
    public function evaluar(EvaluarEquipoRequest $request, int $equipoId)
    {
        $equipo = $this->equipoDeMiEncuentro($request, $equipoId);

        $data = $request->validated();

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => 4],
            [
                'datos'                    => ['evaluacion_docente' => $data['evaluacion']],
                'validado_docente'         => true,
                'fecha_validacion_docente' => now(),
                'nota_docente'             => $data['nota_docente'] ?? null,
                'observaciones_docente'    => $data['observaciones_docente'] ?? null,
                'completada'               => true,
                'fecha_completada'         => now(),
            ]
        );

        return response()->json(['ok' => true, 'fase' => $fase]);
    }

    /**
     * DELETE /api/startup/equipos/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $this->equipoDeMiEncuentro($request, $id)->delete();
        return response()->json(['ok' => true]);
    }

    // ── Proyectar pantalla de acceso ─────────────────────────────────────────

    /**
     * GET /api/startup/proyectos/{uuid}/pantalla-acceso
     * Devuelve la lista de equipos con códigos y tokens para generar QRs en el docente.
     */
    public function pantallaAcceso(Request $request, $uuid)
    {
        $proyecto = $this->proyectoDeMiEncuentro($request, $uuid);

        $equipos = $proyecto->equipos()->get()->map(fn($e) => [
            'id'            => $e->id,
            'nombre'        => $e->nombre,
            'codigo_acceso' => $e->codigo_acceso,
            'token'         => $e->token,
        ]);

        return response()->json([
            'proyecto_titulo' => $proyecto->titulo,
            'equipos'         => $equipos,
        ]);
    }

    // ── Helpers de autorización ──────────────────────────────────────────────
    // Un docente solo puede gestionar equipos/proyectos de sus propios encuentros.
    // Admin y superadmin ven todos (EnsureIsDocente ya deja pasar a ambos roles).

    private function equipoDeMiEncuentro(Request $request, int $equipoId): Equipo
    {
        $query = Equipo::where('id', $equipoId);

        if ($request->user()->isDocente()) {
            $query->whereHas('encuentro', fn($q) => $q->where('user_id', $request->user()->id));
        }

        return $query->firstOrFail();
    }

    private function proyectoDeMiEncuentro(Request $request, string $uuid): Microproyecto
    {
        $query = Microproyecto::where('uuid', $uuid);

        if ($request->user()->isDocente()) {
            $query->whereHas('encuentros', fn($q) => $q->where('user_id', $request->user()->id));
        }

        return $query->firstOrFail();
    }
}
