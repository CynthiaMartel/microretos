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

        $data = $request->validated();

        // Un proyecto sin reto vinculado no puede avanzar más allá del paso 1 del
        // wizard (el reto es la fuente de contexto real de toda la propuesta).
        $pasoDestino = $data['paso_actual'] ?? $proyecto->paso_actual;
        if (!$proyecto->microreto_id && $pasoDestino > 1) {
            return response()->json([
                'message' => 'Este proyecto no tiene un reto vinculado. Vincula uno antes de continuar.',
            ], 422);
        }

        $encuentroId = $request->input('encuentro_id');

        // evaluacion_oficial es ahora la fuente de verdad — ra_ce se deriva siempre a
        // partir de ella para que las vistas que todavía leen texto libre (landing de
        // empresa, detalle de proyecto, export a PDF) sigan funcionando sin cambios.
        if (array_key_exists('evaluacion_oficial', $data) && is_array($data['evaluacion_oficial'])) {
            $data['ra_ce'] = app(\App\Services\RaCeCatalogoService::class)->serializarATexto($data['evaluacion_oficial']);
        }

        // Si el proyecto está validado y se modifica un campo relevante, se invalida toda validación
        if ($proyecto->empresa_validado || $proyecto->docente_validado) {
            $camposCriticos = [
                'titulo', 'empresa_id', 'datos_empresa', 'datos_centro', 'equipo',
                'modulos_seleccionados', 'ra_ce', 'evaluacion_oficial', 'fundamentacion', 'diseno_reto',
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

        if ($encuentroId) {
            \App\Models\Encuentro::where('id', $encuentroId)
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
            'reto_origen'  => 'nullable|string|max:3000',
        ]);

        $modulos = \App\Models\Modulo::with(['ras.criteriosEvaluacion'])
            ->whereIn('id', $request->modulo_ids)
            ->get();

        if ($modulos->isEmpty()) {
            return response()->json(['error' => 'No se encontraron módulos'], 404);
        }

        // Lógica compartida con MicroretoIAController::generar() — mismo enfoque
        // closed-book: la IA solo elige ra_id/ce_ids de un currículo cerrado, nunca
        // redacta el texto (ver RaCeCatalogoService).
        $raCeCatalogo = app(\App\Services\RaCeCatalogoService::class);
        [$raIndex, $curriculo, $hayCurriculumDisponible] = $raCeCatalogo->construirIndiceYTexto($modulos);

        if (!$hayCurriculumDisponible) {
            return response()->json(['seleccion' => []]);
        }

        $contexto = '';
        if ($request->filled('titulo'))        $contexto .= "Título: {$request->titulo}\n";
        if ($request->filled('pregunta_reto')) $contexto .= "Reto: {$request->pregunta_reto}\n";
        if ($request->filled('descripcion'))   $contexto .= "Descripción: {$request->descripcion}\n";
        if ($request->filled('contexto'))      $contexto .= "Contexto empresa: {$request->contexto}\n";
        if ($request->filled('reto_origen'))   $contexto .= "Reto original de referencia (empresa colaboradora):\n{$request->reto_origen}\n";

        if (!$contexto) $contexto = "Sin contexto adicional.\n";

        $systemPrompt = "Eres un experto en currículum de Formación Profesional española. SELECCIONA únicamente ids de RA y CE que aparezcan literalmente en el currículo proporcionado (marcados como [RA id=...] y [CE id=...]) y sean más relevantes para el microproyecto descrito. NUNCA inventes un id ni redactes tú el texto del RA o el CE — el sistema recupera el texto real de la base de datos a partir del id que elijas. Elige SOLO los que se trabajan directamente en el microproyecto.";

        $userPrompt = "Microproyecto:\n{$contexto}\nCurrículo de los módulos seleccionados:\n{$curriculo}\nDevuelve SOLO este JSON:\n{\"seleccion\":[{\"ra_id\": 123, \"ce_ids\": [45, 46]}]}";

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

        $data           = json_decode($response->json()['choices'][0]['message']['content'], true);
        $seleccionCruda = $data['seleccion'] ?? [];

        $seleccion = $raCeCatalogo->resolver($seleccionCruda, $raIndex);

        return response()->json(['seleccion' => $seleccion]);
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
            'reto_origen'   => 'nullable|string|max:3000',
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
        if ($request->filled('reto_origen'))   $contexto .= "Reto original de referencia (empresa colaboradora):\n{$request->reto_origen}\n";

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

    public function sugerirObjetivos(Request $request)
    {
        $request->validate([
            'titulo'        => 'nullable|string|max:300',
            'pregunta_reto' => 'nullable|string|max:1000',
            'descripcion'   => 'nullable|string|max:2000',
            'entregables'   => 'nullable|string|max:1000',
            'ra_ce'         => 'nullable|string|max:5000',
            'reto_origen'   => 'nullable|string|max:3000',
        ]);

        $contexto = '';
        if ($request->filled('titulo'))        $contexto .= "Título del proyecto: {$request->titulo}\n";
        if ($request->filled('pregunta_reto')) $contexto .= "Pregunta reto: {$request->pregunta_reto}\n";
        if ($request->filled('descripcion'))   $contexto .= "Descripción: {$request->descripcion}\n";
        if ($request->filled('entregables'))   $contexto .= "Entregables: {$request->entregables}\n";
        if ($request->filled('ra_ce'))         $contexto .= "RA/CE trabajados:\n{$request->ra_ce}\n";
        if ($request->filled('reto_origen'))   $contexto .= "Reto original de referencia (empresa colaboradora):\n{$request->reto_origen}\n";

        if (!$contexto) {
            return response()->json(['error' => 'Proporciona al menos un campo de contexto'], 422);
        }

        $cacheKey = 'objetivos_' . md5($contexto);
        $resultado = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($contexto) {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => 'gpt-4o',
                    'messages'        => [
                        ['role' => 'system', 'content' => 'Eres un experto en diseño instruccional de Formación Profesional española (Aprendizaje Basado en Retos). Propón objetivos de aprendizaje que el equipo de alumnado debe alcanzar al resolver el reto. Deben ser concretos, orientados a competencias y coherentes con el reto planteado por la empresa colaboradora.'],
                        ['role' => 'user',   'content' => "Contexto del microproyecto:\n{$contexto}\n\nDevuelve SOLO este JSON con entre 3 y 6 objetivos:\n{\"objetivos\":[\"Objetivo concreto y orientado a competencias\"]}"],
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

        return response()->json(['objetivos' => $resultado['objetivos'] ?? []]);
    }

    public function sugerirFundamentacion(Request $request)
    {
        $request->validate([
            'titulo'        => 'nullable|string|max:300',
            'pregunta_reto' => 'nullable|string|max:1000',
            'descripcion'   => 'nullable|string|max:2000',
            'contexto'      => 'nullable|string|max:2000',
            'ra_ce'         => 'nullable|string|max:5000',
            'reto_origen'   => 'nullable|string|max:3000',
        ]);

        $contexto = '';
        if ($request->filled('titulo'))        $contexto .= "Título del reto: {$request->titulo}\n";
        if ($request->filled('pregunta_reto')) $contexto .= "Pregunta reto: {$request->pregunta_reto}\n";
        if ($request->filled('descripcion'))   $contexto .= "Descripción del reto: {$request->descripcion}\n";
        if ($request->filled('contexto'))      $contexto .= "Contexto/situación de partida: {$request->contexto}\n";
        if ($request->filled('ra_ce'))         $contexto .= "RA/CE trabajados:\n{$request->ra_ce}\n";
        if ($request->filled('reto_origen'))   $contexto .= "Reto original de referencia (empresa colaboradora):\n{$request->reto_origen}\n";

        if (!$contexto) {
            return response()->json(['error' => 'Proporciona al menos un campo de contexto'], 422);
        }

        $cacheKey  = 'fundamentacion_' . md5($contexto);
        $resultado = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($contexto) {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => 'gpt-4o',
                    'messages'        => [
                        ['role' => 'system', 'content' => 'Eres un experto en diseño instruccional y metodologías de Aprendizaje Basado en Retos (ABR) para Formación Profesional española. A partir del contexto del reto, redactas la justificación pedagógica (por qué el reto es relevante para el aprendizaje del alumnado, qué aporta frente a un enfoque tradicional) y el elemento innovador (qué hace distinto o novedoso a este proyecto). Sé concreto y evita relleno genérico.'],
                        ['role' => 'user',   'content' => "Contexto del reto:\n{$contexto}\n\nDevuelve SOLO este JSON:\n{\"justificacion\":\"Justificación pedagógica en 2-4 frases\",\"innovacion\":\"Elemento innovador en 1-3 frases\"}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.5,
                ]);

            if (!$response->successful()) return null;

            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado) {
            return response()->json(['error' => 'Error al contactar con la IA'], 500);
        }

        return response()->json([
            'justificacion' => $resultado['justificacion'] ?? '',
            'innovacion'    => $resultado['innovacion'] ?? '',
        ]);
    }

    public function sugerirMetodologia(Request $request)
    {
        $request->validate([
            'titulo'        => 'nullable|string|max:300',
            'pregunta_reto' => 'nullable|string|max:1000',
            'descripcion'   => 'nullable|string|max:2000',
            'fases'         => 'nullable|string|max:500',
            'ciclo'         => 'nullable|string|max:255',
            'curso'         => 'nullable|string|max:10',
            'empresa'       => 'nullable|string|max:500',
            'modulos'       => 'nullable|string|max:1000',
            'reto_origen'   => 'nullable|string|max:3000',
        ]);

        $contexto = '';
        if ($request->filled('titulo'))        $contexto .= "Título del reto: {$request->titulo}\n";
        if ($request->filled('ciclo'))          $contexto .= "Ciclo formativo: {$request->ciclo}\n";
        if ($request->filled('curso'))          $contexto .= "Curso: {$request->curso}\n";
        if ($request->filled('modulos'))        $contexto .= "Módulos implicados: {$request->modulos}\n";
        if ($request->filled('pregunta_reto')) $contexto .= "Pregunta reto: {$request->pregunta_reto}\n";
        if ($request->filled('descripcion'))   $contexto .= "Descripción del reto: {$request->descripcion}\n";
        if ($request->filled('empresa'))        $contexto .= "Empresa colaboradora: {$request->empresa}\n";
        if ($request->filled('fases'))         $contexto .= "Fases del proyecto: {$request->fases}\n";
        if ($request->filled('reto_origen'))    $contexto .= "Reto original de referencia (empresa colaboradora):\n{$request->reto_origen}\n";

        if (!$contexto) {
            return response()->json(['error' => 'Proporciona al menos un campo de contexto'], 422);
        }

        $cacheKey  = 'metodologia_' . md5($contexto);
        $resultado = \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours(6), function () use ($contexto) {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => 'gpt-4o',
                    'messages'        => [
                        ['role' => 'system', 'content' => 'Eres un experto en metodologías didácticas de Aprendizaje Basado en Retos para Formación Profesional española (ciclos formativos). A partir del contexto de la propuesta, redactas: (1) la metodología de trabajo docente en el aula (cómo se organizará el trabajo del equipo/alumnado en este ciclo formativo, qué rol tiene el docente) y (2) un resumen ejecutivo breve de la propuesta para compartir con la empresa colaboradora. Sé concreto y evita relleno genérico.'],
                        ['role' => 'user',   'content' => "Contexto de la propuesta:\n{$contexto}\n\nDevuelve SOLO este JSON:\n{\"metodologia\":\"Metodología docente en 2-4 frases\",\"resumen\":\"Resumen ejecutivo en 3-4 frases\"}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.5,
                ]);

            if (!$response->successful()) return null;

            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado) {
            return response()->json(['error' => 'Error al contactar con la IA'], 500);
        }

        return response()->json([
            'metodologia' => $resultado['metodologia'] ?? '',
            'resumen'     => $resultado['resumen'] ?? '',
        ]);
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
            'encuentro_id'     => $p->encuentros()->value('id'),
            'datos_empresa'    => $p->datos_empresa,
            'datos_centro'     => $p->datos_centro,
            'equipo'           => $p->equipo,
            'modulos_seleccionados' => $p->modulos_seleccionados,
            'ra_ce'            => $p->ra_ce,
            'evaluacion_oficial' => $this->evaluacionOficialDeProyecto($p),
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

    // Proyectos creados antes de la columna `evaluacion_oficial` solo tienen el texto
    // libre `ra_ce` — se parsea al vuelo (sin ids, ra_id null) para que el wizard tenga
    // algo que mostrar/editar. En cuanto el docente guarde desde el wizard, se persiste
    // ya estructurado y este fallback deja de usarse para ese proyecto.
    private function evaluacionOficialDeProyecto(Microproyecto $p): array
    {
        if (is_array($p->evaluacion_oficial) && count($p->evaluacion_oficial)) {
            return $p->evaluacion_oficial;
        }

        return app(\App\Services\RaCeCatalogoService::class)->parsearTextoLegacy($p->ra_ce);
    }
}
