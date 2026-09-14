<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ValidarFaseEquipoRequest;
use App\Http\Requests\RechazarFaseEquipoRequest;
use App\Http\Requests\EvaluarEquipoRequest;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\Microproyecto;
use App\Services\DiagnosticoFinalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * POST /api/startup/equipos/{id}/diagnostico-final
     * Diagnóstico resumen generado por IA a partir de todo el workspace del equipo
     * (proyecto/reto, RA-CE, contenido de las 5 fases, evaluación curricular y nota
     * opcional del docente, reflexiones). Solo disponible cuando el equipo ha
     * completado sus 5 fases. Se persiste en el equipo para no repetir la llamada
     * cada vez que se reabre — un cambio posterior en el contenido (p. ej. el
     * docente corrige una observación) cambia el prompt y por tanto el resultado.
     */
    public function diagnosticoFinal(Request $request, int $equipoId)
    {
        $equipo = $this->equipoDeMiEncuentro($request, $equipoId);
        $equipo->load(['encuentro', 'microproyecto.microreto', 'miembros', 'fases', 'reflexiones']);

        $fasesCompletas = $equipo->fases->filter(fn($f) => $f->completada)->count();
        if ($fasesCompletas < 5) {
            return response()->json(['error' => 'El equipo debe completar las 5 fases antes de generar el diagnóstico final.'], 422);
        }

        $contexto = DiagnosticoFinalService::contexto($equipo);

        $cacheKey  = 'diagnostico_final_v1_' . md5($contexto);
        $resultado = Cache::remember($cacheKey, now()->addDay(), function () use ($contexto) {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un docente de Formación Profesional española que redacta el diagnóstico final de un equipo de alumnado al terminar un microproyecto tipo reto de empresa (Aprendizaje Basado en Retos). Básate ÚNICAMENTE en la información proporcionada, nunca inventes datos que no estén en el contexto. Ten en cuenta el nivel de completitud y la calidad del contenido registrado en cada fase, la evaluación curricular ya realizada por el docente (niveles de RA alcanzados) y la nota opcional si existe. No repitas literalmente los datos identificativos (ciclo, curso, grupo, nombres de los participantes) en el texto — el lector ya los tiene delante, céntrate en analizar el proceso y el aprendizaje. Redacta en español, tono profesional y constructivo, sin tecnicismos innecesarios.'],
                        ['role' => 'user',   'content' => "Contexto del equipo:\n{$contexto}\nDevuelve SOLO este JSON:\n{\"resumen\":\"resumen general del desempeño del equipo a lo largo del proyecto (2-4 frases)\",\"fortalezas\":[\"punto fuerte concreto\"],\"areas_mejora\":[\"área de mejora concreta\"],\"valoracion_ra_ce\":\"cómo el trabajo del equipo evidencia (o no) los RA/CE trabajados, apoyándote en la evaluación curricular del docente si existe\",\"conclusion\":\"valoración final breve (1-2 frases)\"}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.5,
                ]);

            if (!$response->successful()) {
                Log::error('Error generando diagnóstico final de equipo', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 2000),
                ]);
                return null;
            }

            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado || empty($resultado['resumen'])) {
            return response()->json(['error' => 'No se pudo generar el diagnóstico. Inténtalo de nuevo.'], 502);
        }

        $equipo->update([
            'diagnostico_final'       => $resultado,
            'diagnostico_generado_en' => now(),
        ]);

        return response()->json([
            'diagnostico'  => $resultado,
            'generado_en'  => $equipo->diagnostico_generado_en,
        ]);
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

    // pantallaAcceso solo lee (visiblesPara); validarFase/rechazarFase/evaluar/destroy mutan
    // datos del equipo, así que exigen editablesPara (un colaborador de solo lectura no pasa).

    private function equipoDeMiEncuentro(Request $request, int $equipoId): Equipo
    {
        return Equipo::where('id', $equipoId)
            ->whereHas('encuentro', fn($q) => $q->editablesPara($request->user()))
            ->firstOrFail();
    }

    private function proyectoDeMiEncuentro(Request $request, string $uuid): Microproyecto
    {
        return Microproyecto::where('uuid', $uuid)
            ->whereHas('encuentros', fn($q) => $q->visiblesPara($request->user()))
            ->firstOrFail();
    }
}
