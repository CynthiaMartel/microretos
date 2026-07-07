<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMicroproyectoRequest;
use Illuminate\Http\Request;
use App\Models\Microproyecto;

class MicroproyectoController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Microproyecto::with(['empresa', 'centroEducativo', 'cicloFormativo', 'microreto'])
            ->orderByDesc('updated_at');

        if (!$user->isSuperAdmin()) {
            $query->where('centro_id', $user->centro_educativo_id);
        }

        $proyectos = $query->get()->map(fn($p) => $this->formatProyecto($p));

        return response()->json($proyectos);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo'       => 'required|string|max:255',
            'microreto_id' => 'required|exists:microretos,id',
            'empresa_id'   => 'nullable|exists:empresas,id',
            'centro_id'    => 'nullable|exists:centros_educativos,id',
            'familia_id'   => 'nullable|exists:familias,id',
            'ciclo_id'     => 'nullable|exists:ciclos_formativos,id',
            'curso'        => 'nullable|string',
        ]);

        if (!$request->user()->isSuperAdmin()) {
            $data['centro_id'] = $request->user()->centro_educativo_id;
        }

        $proyecto = Microproyecto::create($data);

        return response()->json($this->formatProyecto($proyecto->fresh()), 201);
    }

    public function show($uuid)
    {
        $proyecto = Microproyecto::with(['empresa', 'centroEducativo', 'cicloFormativo', 'microreto'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $proyecto);

        return response()->json($this->formatProyecto($proyecto));
    }

    public function update(UpdateMicroproyectoRequest $request, $uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $proyecto);

        $sesionId = $request->input('sesion_id');

        $data = $request->validated();

        // Si el proyecto está validado y se modifica un campo relevante, se invalida toda validación
        if ($proyecto->empresa_validado || $proyecto->docente_validado) {
            $camposCriticos = [
                'titulo', 'empresa_id', 'datos_empresa', 'datos_centro', 'equipo',
                'modulos_seleccionados', 'ra_ce', 'fundamentacion', 'diseno_reto',
                'diseno_microproyecto', 'resumen', 'objetivos', 'kpis',
            ];

            foreach ($camposCriticos as $campo) {
                if (!array_key_exists($campo, $data)) continue;

                $anterior = is_array($proyecto->$campo)
                    ? json_encode($proyecto->$campo)
                    : (string) ($proyecto->$campo ?? '');
                $nuevo = is_array($data[$campo])
                    ? json_encode($data[$campo])
                    : (string) ($data[$campo] ?? '');

                if ($anterior !== $nuevo) {
                    $data['empresa_validado']   = false;
                    $data['docente_validado']   = false;
                    $data['validacion_empresa'] = null;
                    if (!array_key_exists('estado', $data)) {
                        $data['estado'] = 'propuesta';
                    }
                    break;
                }
            }
        }

        $proyecto->update($data);

        if ($sesionId) {
            \App\Models\Sesion::where('id', $sesionId)
                ->update(['microproyecto_id' => $proyecto->id]);
        }

        return response()->json($this->formatProyecto($proyecto->fresh()));
    }

    public function destroy($uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $proyecto);

        $proyecto->delete();

        return response()->json(['ok' => true]);
    }

    // --- Validación pública empresa (acceso por token) ---

    public function showByToken($token)
    {
        $proyecto = Microproyecto::with(['recursos', 'microreto'])
            ->where('token_empresa', $token)
            ->whereIn('estado', ['propuesta', 'validado'])
            ->firstOrFail();

        $formato = fn($r) => [
            'url'           => $r->url,
            'public_id'     => $r->public_id,
            'resource_type' => $r->resource_type,
            'filename'      => $r->filename,
            'label'         => $r->label ?? '',
        ];

        $mr = $proyecto->microreto;

        return response()->json([
            'uuid'                   => $proyecto->uuid,
            'titulo'                 => $proyecto->titulo,
            'datos_empresa'          => $proyecto->datos_empresa,
            'datos_centro'           => $proyecto->datos_centro,
            'fundamentacion'         => $proyecto->fundamentacion,
            'diseno_reto'            => $proyecto->diseno_reto,
            'diseno_microproyecto'   => $proyecto->diseno_microproyecto,
            'objetivos'              => $proyecto->objetivos,
            'kpis'                   => $proyecto->kpis,
            'equipo'                 => $proyecto->equipo,
            'modulos_seleccionados'  => $proyecto->modulos_seleccionados,
            'ra_ce'                  => $proyecto->ra_ce,
            'resumen'                => $proyecto->resumen,
            'recursos'               => [
                'videos'     => $proyecto->recursos->where('tipo', 'video')->map($formato)->values(),
                'documentos' => $proyecto->recursos->where('tipo', 'documento')->map($formato)->values(),
            ],
            'reto_origen'            => $mr ? [
                'titulo'        => $mr->titulo,
                'quien_es'      => $mr->quien_es,
                'dia_a_dia'     => $mr->dia_a_dia,
                'que_necesitan' => $mr->que_necesitan,
                'dificultades'  => $mr->dificultades,
            ] : null,
            'empresa_validado'      => $proyecto->empresa_validado,
            'empresa_no_valida_aun' => $proyecto->empresa_no_valida_aun,
            'validacion_empresa'    => $proyecto->validacion_empresa,
        ]);
    }

    public function validarEmpresa(Request $request, $token)
    {
        $proyecto = Microproyecto::where('token_empresa', $token)
            ->whereIn('estado', ['propuesta', 'validado'])
            ->firstOrFail();

        $data = $request->validate([
            'decision'   => 'required|in:validar,no_validar_aun',
            'respuestas' => 'required|array',
            'comentarios'=> 'nullable|string|max:2000',
        ]);

        if ($data['decision'] === 'validar') {
            // La empresa valida: guardar respuestas, marcar validado, avanzar estado
            $proyecto->update([
                'estado'                => 'validado',
                'validacion_empresa'    => ['respuestas' => $data['respuestas'], 'comentarios' => $data['comentarios'] ?? null],
                'empresa_validado'      => true,
                'empresa_no_valida_aun' => false,
            ]);
        } else {
            // La empresa responde "no validar aún": guardar respuestas, mantener en propuesta
            $proyecto->update([
                'estado'                => 'propuesta',
                'validacion_empresa'    => ['respuestas' => $data['respuestas'], 'comentarios' => $data['comentarios'] ?? null],
                'empresa_validado'      => false,
                'empresa_no_valida_aun' => true,
            ]);
        }

        return response()->json(['ok' => true, 'decision' => $data['decision']]);
    }

    // --- Validación docente ---

    public function validarDocente(Request $request, $uuid)
    {
        $proyecto = Microproyecto::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $proyecto);

        if (!in_array($proyecto->estado, ['propuesta', 'validado'])) {
            return response()->json(['error' => 'Solo se puede validar un proyecto en estado propuesta o validado'], 422);
        }

        $data = $request->validate([
            'decision' => 'required|in:validar,desvalidar',
        ]);

        if ($data['decision'] === 'validar') {
            $proyecto->update([
                'docente_validado' => true,
                'estado'           => 'validado',
            ]);
        } else {
            // Desvalidar docente: si empresa también validó, mantener 'validado'; si no, volver a 'propuesta'
            $nuevoEstado = $proyecto->empresa_validado ? 'validado' : 'propuesta';
            $proyecto->update([
                'docente_validado' => false,
                'estado'           => $nuevoEstado,
            ]);
        }

        return response()->json(['ok' => true, 'decision' => $data['decision']]);
    }

    // --- IA: sugerencia de RA/CE ---

    public function sugerirRaCe(Request $request)
    {
        $request->validate([
            'modulo_ids'   => 'required|array|min:1',
            'modulo_ids.*' => 'integer|exists:modulos,id',
        ]);

        $modulos = \App\Models\Modulo::with(['ras.criteriosEvaluacion'])
            ->whereIn('id', $request->modulo_ids)
            ->get();

        if ($modulos->isEmpty()) {
            return response()->json(['error' => 'No se encontraron módulos'], 404);
        }

        $curriculo = '';
        foreach ($modulos as $modulo) {
            $curriculo .= "[MÓDULO]: {$modulo->nombre}\n";
            foreach ($modulo->ras as $ra) {
                $curriculo .= "  RA: {$ra->ra}\n";
                foreach ($ra->criteriosEvaluacion as $ce) {
                    $curriculo .= "    CE: {$ce->ce}\n";
                }
            }
            $curriculo .= "\n";
        }

        $contexto = '';
        if ($request->filled('titulo'))        $contexto .= "Título: {$request->titulo}\n";
        if ($request->filled('pregunta_reto')) $contexto .= "Reto: {$request->pregunta_reto}\n";
        if ($request->filled('descripcion'))   $contexto .= "Descripción: {$request->descripcion}\n";
        if ($request->filled('contexto'))      $contexto .= "Contexto empresa: {$request->contexto}\n";

        if (!$contexto) $contexto = "Sin contexto adicional.\n";

        $systemPrompt = "Eres un experto en currículum de Formación Profesional española. Selecciona los Resultados de Aprendizaje (RA) y Criterios de Evaluación (CE) más relevantes para el microproyecto descrito. Elige SOLO los que se trabajan directamente en el microproyecto. Usa los textos EXACTOS del catálogo.";

        $userPrompt = "Microproyecto:\n{$contexto}\nCatálogo RA/CE de los módulos seleccionados:\n{$curriculo}\nDevuelve SOLO este JSON:\n{\"seleccion\":[{\"modulo\":\"Nombre exacto del módulo\",\"ra\":\"Texto exacto del RA\",\"ce\":[\"Texto exacto del CE\"]}]}";

        $response = \Illuminate\Support\Facades\Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o',
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.2,
            ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Error al contactar con la IA'], 500);
        }

        $data      = json_decode($response->json()['choices'][0]['message']['content'], true);
        $seleccion = $data['seleccion'] ?? [];

        $texto = collect($seleccion)->map(function ($item) {
            $ces = collect($item['ce'] ?? [])->map(fn($c) => "  • {$c}")->join("\n");
            return "[{$item['modulo']}]\nRA: {$item['ra']}\nCE:\n{$ces}";
        })->join("\n\n");

        return response()->json([
            'seleccion'   => $seleccion,
            'ra_ce_texto' => $texto,
        ]);
    }

    public function sugerirKpis(Request $request)
    {
        $request->validate([
            'titulo'        => 'nullable|string|max:300',
            'pregunta_reto' => 'nullable|string|max:1000',
            'descripcion'   => 'nullable|string|max:2000',
            'entregables'   => 'nullable|string|max:1000',
            'objetivos'     => 'nullable|array|max:20',
            'objetivos.*'   => 'string|max:300',
            'ra_ce'         => 'nullable|string|max:5000',
        ]);

        $contexto = '';
        if ($request->filled('titulo'))        $contexto .= "Título del proyecto: {$request->titulo}\n";
        if ($request->filled('pregunta_reto')) $contexto .= "Pregunta reto: {$request->pregunta_reto}\n";
        if ($request->filled('descripcion'))   $contexto .= "Descripción: {$request->descripcion}\n";
        if ($request->filled('entregables'))   $contexto .= "Entregables: {$request->entregables}\n";
        if ($request->filled('objetivos')) {
            $lista = collect($request->objetivos)->map(fn($o) => "  - {$o}")->join("\n");
            $contexto .= "Objetivos de aprendizaje:\n{$lista}\n";
        }
        if ($request->filled('ra_ce'))         $contexto .= "RA/CE trabajados:\n{$request->ra_ce}\n";

        if (!$contexto) {
            return response()->json(['error' => 'Proporciona al menos un campo de contexto'], 422);
        }

        $cacheKey = 'kpis_' . md5($contexto);
        $resultado = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($contexto) {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => 'gpt-4o',
                    'messages'        => [
                        ['role' => 'system', 'content' => 'Eres un experto en evaluación de proyectos de Formación Profesional española. Propón KPIs (indicadores clave de rendimiento) que una empresa puede usar para evaluar si el equipo de alumnado ha resuelto correctamente el reto planteado. Los KPIs deben ser concretos, medibles y relevantes para el contexto del proyecto.'],
                        ['role' => 'user',   'content' => "Contexto del microproyecto:\n{$contexto}\n\nDevuelve SOLO este JSON con entre 4 y 8 KPIs:\n{\"kpis\":[\"KPI concreto y medible\"]}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.4,
                ]);

            if (!$response->successful()) return null;

            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado) {
            return response()->json(['error' => 'Error al contactar con la IA'], 500);
        }

        return response()->json(['kpis' => $resultado['kpis'] ?? []]);
    }

    // --- Helper ---

    private function formatProyecto(Microproyecto $p): array
    {
        return [
            'id'               => $p->id,
            'uuid'             => $p->uuid,
            'titulo'           => $p->titulo,
            'curso'            => $p->curso,
            'estado'           => $p->estado,
            'paso_actual'      => $p->paso_actual,
            'empresa_validado'      => $p->empresa_validado,
            'empresa_no_valida_aun' => $p->empresa_no_valida_aun,
            'enviado_a_empresa_mail'=> $p->enviado_a_empresa_mail,
            'docente_validado'      => $p->docente_validado,
            'token_empresa'         => $p->token_empresa,
            'empresa_id'       => $p->empresa_id,
            'empresa_nombre'   => $p->empresa?->nombre_comercial,
            'centro_id'        => $p->centro_id,
            'centro_nombre'    => $p->centroEducativo?->nombre,
            'ciclo_id'         => $p->ciclo_id,
            'ciclo_nombre'     => $p->cicloFormativo?->nombre,
            'familia_id'       => $p->familia_id,
            'microreto_id'     => $p->microreto_id,
            'microreto_titulo' => $p->microreto?->titulo,
            'sesion_id'        => $p->sesiones()->value('id'),
            'datos_empresa'    => $p->datos_empresa,
            'datos_centro'     => $p->datos_centro,
            'equipo'           => $p->equipo,
            'modulos_seleccionados' => $p->modulos_seleccionados,
            'ra_ce'            => $p->ra_ce,
            'fundamentacion'   => $p->fundamentacion,
            'diseno_reto'      => $p->diseno_reto,
            'diseno_microproyecto' => $p->diseno_microproyecto,
            'resumen'          => $p->resumen,
            'objetivos'        => $p->objetivos,
            'kpis'             => $p->kpis,
            'validacion_empresa' => $p->validacion_empresa,
            'created_at'       => $p->created_at,
            'updated_at'       => $p->updated_at,
        ];
    }
}
