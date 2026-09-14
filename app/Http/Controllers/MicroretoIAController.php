<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Microreto;
use App\Models\Modulo;
use App\Models\Empresa;
use App\Http\Requests\StoreMicroretoRequest;
use App\Http\Resources\MicroretoFichaResource;
use App\Services\MicroretoFichaService;

// Endpoint API: /microretos (sin cambios). El frontend renombró su URL a /retos y /retos/crear,
// pero el modelo, la tabla y este controlador siguen llamándose "Microreto" — no renombrado a propósito.
class MicroretoIAController extends Controller
{
    public function index(Request $request)
    {
        // Límite de seguridad: máximo 500 registros por llamada.
        // El frontend filtra en cliente, así que cargamos todo pero con techo.
        // Cuando el volumen crezca habrá que añadir filtros server-side.
        $limit = min((int) $request->query('limit', 500), 500);

        $user  = $request->user();
        $query = Microreto::with([
            'empresa.centroEducativo',
            'empresa.familias',
        ])
        ->orderByDesc('created_at')
        ->limit($limit);

        // Docentes y admins docentes solo ven microretos de su centro educativo
        if (($user->isDocente() || $user->isAdmin()) && $user->centro_educativo_id) {
            $centroId     = $user->centro_educativo_id;
            $centroNombre = $user->centroEducativo?->nombre;
            $query->whereHas('empresa', function ($q) use ($centroId, $centroNombre) {
                $q->where('centro_id', $centroId);
                if ($centroNombre) {
                    $q->orWhere('centro_educativo', $centroNombre);
                }
            });
        }

        $microretos = MicroretoFichaService::enriquecerLote($query->get());

        return response()->json($microretos);
    }

    public function show(Request $request, $id)
    {
        // Acepta UUID (formato preferido, IDOR-safe) o ID entero (legacy: sesiones antiguas
        // guardadas antes de que el frontend migrara a uuid).
        $query = Microreto::with([
            'empresa.centroEducativo',
            'empresa.familias',
        ]);

        // Docentes y admins docentes solo pueden ver microretos de su centro
        $user = $request->user();
        if (($user->isDocente() || $user->isAdmin()) && $user->centro_educativo_id) {
            $centroId     = $user->centro_educativo_id;
            $centroNombre = $user->centroEducativo?->nombre;
            $query->whereHas('empresa', function ($q) use ($centroId, $centroNombre) {
                $q->where('centro_id', $centroId);
                if ($centroNombre) {
                    $q->orWhere('centro_educativo', $centroNombre);
                }
            });
        }

        $reto = is_numeric($id)
            ? $query->findOrFail((int) $id)
            : $query->where('uuid', $id)->firstOrFail();

        return response()->json(new MicroretoFichaResource(MicroretoFichaService::enriquecer($reto)));
    }

    public function simularInfoEmpresa(Request $request)
    {
        $request->validate([
            'empresaNombre'    => 'required|string',
            'empresaSector'    => 'required|string',
            'empresaTamano'    => 'nullable|string',
            'empresaUbicacion' => 'nullable|string',
        ]);

        $contextoEmpresa = "EMPRESA: {$request->empresaNombre} (Sector: {$request->empresaSector})";
        if ($request->filled('empresaTamano'))    $contextoEmpresa .= ", Tamaño: {$request->empresaTamano}";
        if ($request->filled('empresaUbicacion')) $contextoEmpresa .= ", Ubicación: {$request->empresaUbicacion}";

        $limitacionesOpciones  = ['Presupuesto Cero/Muy Bajo', 'Equipos obsoletos', 'Internet inestable', 'Software cerrado', 'Resistencia al cambio', 'Espacio reducido', 'Falta de tiempo', 'Normativa RGPD'];
        $consecuenciasOpciones = ['Errores frecuentes', 'Costes innecesarios', 'Pérdida de tiempo', 'Insatisfacción del cliente', 'Riesgos de seguridad', 'Desperdicio de materiales', 'Falta de comunicación interna'];

        $limitacionesStr  = implode('", "', $limitacionesOpciones);
        $consecuenciasStr = implode('", "', $consecuenciasOpciones);

        $systemPrompt = "Eres un responsable o empleado de la empresa '{$request->empresaNombre}' del sector '{$request->empresaSector}'. Conoces perfectamente la operativa diaria, los problemas internos, las limitaciones reales y los objetivos de mejora de tu empresa. Describes situaciones concretas, creíbles y propias del sector, sin inventar soluciones.";

        $userPrompt = "Rellena el siguiente formulario de diagnóstico empresarial como si fueras un representante de {$contextoEmpresa}.

FORMULARIO (responde en español, con detalle y realismo):
- P1 (diaANormal): Describe brevemente el día a día de tu empresa y cómo funciona vuestro proceso o servicio principal (máx. 900 caracteres).
- P2 (friccionArea): Nombra el área o proceso concreto que más trabajo extra genera actualmente (máx. 380 caracteres).
- P2b (friccionProblema): Explica con detalle qué ocurre hoy en ese proceso y por qué genera problemas (máx. 1100 caracteres).
- P3 (restricciones): De esta lista, devuelve SOLO los textos que aplican realmente a tu empresa: [\"{$limitacionesStr}\"]. Devuelve exactamente los textos tal como aparecen. Puede ser un array vacío.
- P3b (otraLimitacion): Si tenéis alguna limitación adicional no incluida en la lista, descríbela brevemente (máx. 500 caracteres, puede estar vacío).
- P3b2 (loQueNoQuieren): Describe qué tipo de soluciones no queréis bajo ningún concepto (máx. 450 caracteres).
- P4 (consecuencias): De esta lista, devuelve SOLO los textos que describen consecuencias reales del problema en tu empresa: [\"{$consecuenciasStr}\"]. Devuelve exactamente los textos tal como aparecen. Puede ser un array vacío.
- P4b (otraConsecuencia): Si hay alguna consecuencia adicional no incluida en la lista, descríbela (máx. 280 caracteres, puede estar vacío).
- P5 (expectativasAlumno): ¿Qué esperáis que investigue o proponga el alumno de FP para ayudaros? (máx. 750 caracteres).

Responde ÚNICAMENTE con este JSON exacto, sin texto adicional:
{
  \"diaANormal\": \"...\",
  \"friccionArea\": \"...\",
  \"friccionProblema\": \"...\",
  \"restricciones\": [],
  \"otraLimitacion\": \"\",
  \"loQueNoQuieren\": \"...\",
  \"consecuencias\": [],
  \"otraConsecuencia\": \"\",
  \"expectativasAlumno\": \"...\"
}";

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->post("https://api.openai.com/v1/chat/completions", [
                "model"           => "gpt-4o",
                "messages"        => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user",   "content" => $userPrompt],
                ],
                "response_format" => ["type" => "json_object"],
                "temperature"     => 0.8,
            ]);

        if ($response->successful()) {
            return response()->json(json_decode($response->json()['choices'][0]['message']['content'], true));
        }

        return response()->json(['error' => 'Error al contactar con la IA'], 500);
    }

    public function generar(Request $request)
    {
        // El frontend manda empresaId (camelCase), normalizamos antes de validar
        $request->merge([
            'empresa_id' => $request->empresa_id ?? $request->empresaId,
        ]);

        $request->validate([
            'empresa_id'       => 'required|integer|exists:empresas,id',
            'empresaNombre'    => 'required|string',
            'empresaSector'    => 'required|string',
            'friccionProblema' => 'required|string',
            'ciclo_nombre'     => 'required|string',
            'ciclo_id'         => 'required',
            'nivelGrupo'       => 'required|string',
            'cursoSeleccionado'=> 'required|in:1,2,ambos_cursos',
            'modulo_id'        => 'nullable|array',
            'cantidad'         => 'required|integer|min:1|max:5',
            'familia'          => 'nullable|string',
        ]);

        // Docentes y admin de centro solo pueden generar retos con empresas de su centro
        $user = $request->user();
        if (($user->isDocente() || $user->isAdmin())) {
            $empresa = Empresa::find($request->empresa_id);
            if (!$empresa || !$empresa->perteneceAlCentroDe($user)) {
                return response()->json(['error' => 'No autorizado: la empresa no pertenece a tu centro educativo.'], 403);
            }
        }

        $consecuencias = implode(", ", $request->consecuencias ?? []);

        // Escenario B: "ambos cursos" — el reto cruza módulos de 1º y 2º a la vez.
        // Se puede forzar módulos concretos de ambos cursos (modulo_id), o dejar que la
        // IA use el currículo completo de los dos cursos si no se fuerza ninguno.
        $esAmbosCursos   = $request->cursoSeleccionado === 'ambos_cursos';
        $moduloIdForzado = $request->filled('modulo_id') && is_array($request->modulo_id) && count($request->modulo_id) > 0
            ? $request->modulo_id
            : null;

        $query = Modulo::with(['ras.criteriosEvaluacion']);

        if ($esAmbosCursos) {
            $query->where('idcicloformativo', $request->ciclo_id);
            $moduloIdForzado
                ? $query->whereIn('id', $moduloIdForzado)
                : $query->whereIn('curso', [1, 2]);
        } elseif ($moduloIdForzado) {
            $query->whereIn('id', $moduloIdForzado);
        } else {
            $query->where('idcicloformativo', $request->ciclo_id)
                  ->where('curso', $request->cursoSeleccionado);
        }
        $modulos = $query->get();

        // Defensa: en "Ambos Cursos" con módulos forzados, nunca confiar en el cliente —
        // exigir que la selección resultante cubra realmente curso 1 y curso 2.
        if ($esAmbosCursos && $moduloIdForzado) {
            $cursosCubiertos = $modulos->pluck('curso')->unique();
            if (!$cursosCubiertos->contains(1) || !$cursosCubiertos->contains(2)) {
                return response()->json([
                    'error' => 'En modo "Ambos Cursos" hay que forzar al menos un módulo de 1º y uno de 2º.',
                ], 422);
            }
        }

        // Índice ra_id -> {ra, modulo} + texto de currículo con ids reales embebidos.
        // Lógica compartida con MicroproyectoController::sugerirRaCe() — mismo
        // enfoque closed-book en ambos flujos (ver RaCeCatalogoService).
        $raCeCatalogo = app(\App\Services\RaCeCatalogoService::class);
        [$raIndex, $curriculumCuerpo, $hayCurriculumDisponible] = $raCeCatalogo->construirIndiceYTexto($modulos);

        // Sin esta regla la IA cumple el esquema con una sola entrada en
        // evaluacion_oficial y listo — "Ambos Cursos"/"Multi-módulo" solo amplían
        // qué currículo VE la IA, pero nada la obliga a usar más de un módulo.
        //
        // Mínimo de módulos a cubrir: si se han forzado módulos concretos, se exige
        // cubrirlos TODOS (así, forzar solo 2 pide 2, nunca se inventa un tercero);
        // si no se ha forzado ninguno (la IA decide libremente), se pide un mínimo
        // de 3 para que el "curado" resulte realmente representativo del currículo.
        // Nunca se pide más módulos de los que el currículo realmente tiene.
        $minModulos = $moduloIdForzado ? $modulos->count() : 3;
        $minModulos = min($minModulos, $modulos->count());

        $totalEntradasObjetivo = $minModulos * 2;

        if ($esAmbosCursos) {
            $nombresCurso1  = $modulos->where('curso', 1)->pluck('nombre')->implode(', ');
            $nombresCurso2  = $modulos->where('curso', 2)->pluck('nombre')->implode(', ');
            $reglaCobertura = "COBERTURA OBLIGATORIA (Ambos Cursos) — sigue este checklist al elegir los módulos, EN ESTE ORDEN:\n"
                . "  1. Elige un TOTAL de al menos {$minModulos} módulos DISTINTOS.\n"
                . "  2. De esos módulos, AL MENOS UNO tiene que ser de 1º (elige entre: {$nombresCurso1}).\n"
                . "  3. Y AL MENOS OTRO (distinto del anterior) tiene que ser de 2º (elige entre: {$nombresCurso2}).\n"
                . "  4. El resto de módulos, si los hay, puede ser indistintamente de 1º o de 2º.\n"
                . "  5. PROHIBIDO que los {$minModulos}+ módulos sean todos del mismo curso — NO es válido \"todos de 1º\" ni \"todos de 2º\". Antes de responder, revisa tu lista final de módulos y confirma que hay representación real de AMBOS cursos; si no la hay, corrige la selección.\n"
                . "Además, por CADA módulo que cubras incluye 2 entradas distintas (2 ra_id diferentes de ESE módulo, cada una con 2 ce_ids), salvo que ese módulo no tenga 2 RA con criterios disponibles en el currículo, en cuyo caso incluye solo los que tenga. El array \"evaluacion_oficial\" completo debe tener por tanto, orientativamente, {$totalEntradasObjetivo} entradas en total (2 por cada uno de los {$minModulos} módulos).";
        } elseif ($minModulos > 1) {
            $nombresModulos = $modulos->pluck('nombre')->unique()->implode(', ');
            $reglaCobertura = "COBERTURA OBLIGATORIA (Multi-módulo): cubre al menos {$minModulos} módulos distintos entre: {$nombresModulos} — nunca limites la selección a un único módulo. Además, por CADA módulo que cubras incluye 2 entradas distintas (2 ra_id diferentes de ESE módulo, cada una con 2 ce_ids), salvo que ese módulo no tenga 2 RA con criterios disponibles en el currículo, en cuyo caso incluye solo los que tenga. El array \"evaluacion_oficial\" completo debe tener por tanto, orientativamente, {$totalEntradasObjetivo} entradas en total (2 por cada uno de los {$minModulos} módulos).";
        } else {
            $reglaCobertura = "";
        }

        $cursoLabelIA = $esAmbosCursos
            ? '1º y 2º curso (contenidos transversales de ambos años)'
            : "{$request->cursoSeleccionado}º curso";

        $curriculumTexto = "--- INICIO CURRÍCULO DE {$cursoLabelIA} ---\n"
            . $curriculumCuerpo
            . "\n--- FIN CURRÍCULO ---";

        $esBasica    = ($request->nivelGrupo === 'Bajo');
        $reglaExtra  = $esBasica
            ? "REGLA: Nivel Básico (FP Básica). Reto eminentemente manual, paso a paso y muy guiado."
            : "REGLA: Nivel {$request->nivelGrupo}. Adapta la complejidad técnica al nivel indicado.";
        $reglaExtra .= " TEN EN CUENTA QUE ES PARA ALUMNADO DE {$cursoLabelIA}. Adapta el prototipo a sus conocimientos.";

        $familia = $request->filled('familia') ? $request->familia : null;

        $contextoEmpresa = "EMPRESA: {$request->empresaNombre} (Sector: {$request->empresaSector}). ";
        if ($request->filled('empresaTamano'))    $contextoEmpresa .= "Tamaño: {$request->empresaTamano}. ";
        if ($request->filled('empresaUbicacion')) $contextoEmpresa .= "Ubicación: {$request->empresaUbicacion}. ";

        $contextoFormativo  = "CICLO FORMATIVO: {$request->ciclo_nombre} ({$cursoLabelIA}).\n";
        if ($familia) {
            $contextoFormativo .= "FAMILIA PROFESIONAL: {$familia}.\n";
            $contextoFormativo .= "IMPORTANTE: Todos los prototipos, soluciones sugeridas y terminología deben ser específicos de la Familia Profesional «{$familia}». Usa herramientas, técnicas, documentos y procesos propios de ese perfil profesional. No propongas entregables genéricos.\n";
        }

        $contextoFriccion  = "OPERATIVA Y OFERTA (P1): {$request->diaANormal}\n";
        $contextoFriccion .= "PROCESO QUE DA TRABAJO EXTRA (P2): {$request->friccionArea}\n";
        $contextoFriccion .= "DETALLE DEL PROBLEMA (P2b): {$request->friccionProblema}\n";
        $contextoFriccion .= "OBJETIVOS DE MEJORA / CONSECUENCIAS (P4): {$consecuencias}\n";
        if ($request->filled('expectativasAlumno')) {
            $contextoFriccion .= "EXPECTATIVA DE LO QUE DEBE HACER EL ALUMNO (P5): {$request->expectativasAlumno}\n";
        }

        $familiaRegla = $familia
            ? "4. Los prototipos, las necesidades y la terminología de cada microreto DEBEN ser propios de la Familia Profesional «{$familia}». Adapta cada entregable al perfil real del alumnado: usa las herramientas, los procesos y los documentos habituales en esa familia profesional. Nunca propongas entregables genéricos que no encajen con el perfil."
            : "4. Adapta los prototipos y necesidades al perfil profesional del alumnado según el ciclo formativo indicado.";

        $systemPrompt = "Eres un consultor de innovación y diseñador instruccional experto en formación profesional y metodologías ágiles (Design Thinking).
        REGLAS ESTRICTAS:
        1. NO proponer soluciones cerradas. Puedes sugerir el tipo de prototipo a entregar. El alumno debe idear la solución final.
        2. Genera EXACTAMENTE {$request->cantidad} microreto(s) totalmente distintos entre sí para la misma empresa.
        3. Para \"evaluacion_oficial\": SELECCIONA únicamente ids de RA y CE que aparezcan literalmente en el currículo proporcionado (marcados como [RA id=...] y [CE id=...]). NUNCA inventes un id ni redactes tú el texto del RA o el CE — el sistema recupera el texto real de la base de datos a partir del id que elijas. Si el currículo proporcionado no tiene RA/CE disponibles, devuelve evaluacion_oficial como array vacío []. Para lograr variedad entre retos, elige distintos RA/CE de la lista para cada uno. {$reglaCobertura}
        {$familiaRegla}";

        $prototiposHint = $familia
            ? "Entregable específico de la Familia Profesional «{$familia}» (usa herramientas, documentos y técnicas habituales en ese perfil, NO entregables genéricos)"
            : "Entregable concreto adaptado al ciclo formativo (ej: Diagrama de flujo, Guion de entrevista)";

        $queNecesitanHint = $familia
            ? "Necesidad técnica expresada en términos propios de la Familia Profesional «{$familia}»"
            : "Necesidad técnica o organizativa concreta";

        $userPrompt = "
        {$contextoEmpresa}
        {$contextoFormativo}
        {$contextoFriccion}
        LIMITACIONES TÉCNICAS Y LOGÍSTICAS (P3): {$request->restricciones}.
        LO QUE NO QUIEREN (P3b): {$request->loQueNoQuieren}.
        DURACIÓN: {$request->duracion}.

        {$curriculumTexto}
        {$reglaExtra}

        Basándote en los módulos del currículo, DEVUELVE ESTE JSON EXACTO CON UN ARRAY DE EXACTAMENTE {$request->cantidad} MICRORETO(S) (el array \"evaluacion_oficial\" puede tener 1 o más entradas según las reglas anteriores; se muestran 2 solo como ejemplo de formato):
        {
            \"microretos\": [
                {
                    \"titulo\": \"Título corto y directo del reto\",
                    \"subtitulo\": \"Descripción de 1 línea del desafío (sin revelar la solución)\",
                    \"empresa_nombre\": \"{$request->empresaNombre}\",
                    \"quien_es\": \"1-2 frases sobre la actividad de la empresa basándote en su sector y operativa.\",
                    \"dia_a_dia\": \"1 frase clara sobre cómo operan y dónde falla el proceso actualmente.\",
                    \"dificultades\": [\"Fallo 1\", \"Fallo 2\"],
                    \"pregunta_reto\": \"Formula el desafío en forma de pregunta abierta empezando por ¿Cómo podríamos...?\",
                    \"que_necesitan\": [\"{$queNecesitanHint} 1\", \"{$queNecesitanHint} 2\"],
                    \"limitaciones\": [\"Restricción 1\", \"Restricción 2\"],
                    \"prototipos\": [\"{$prototiposHint} 1\", \"{$prototiposHint} 2\"],
                    \"ods_sugeridos\": [\"ODS X: Nombre completo del ODS\"],
                    \"evaluacion_oficial\": [
                        {
                            \"ra_id\": 123,
                            \"ce_ids\": [45, 46],
                            \"aplicacion\": \"Breve frase explicando cómo se aterriza este aprendizaje en el contexto de la Familia Profesional.\"
                        },
                        {
                            \"ra_id\": 789,
                            \"ce_ids\": [12],
                            \"aplicacion\": \"Breve frase explicando cómo se aterriza este otro aprendizaje.\"
                        }
                    ],
                    \"variantes\": [
                        \"Nombre de la Variante: Descripción de una modificación del reto adaptada a la Familia Profesional.\"
                    ],
                    \"tips_profesorado\": [
                        \"Gestión de Aula: [Instrucciones sobre dinámicas o roles propios de la Familia Profesional].\"
                    ]
                }
            ]
        }";

        // Mapa nombre de módulo -> curso, para comprobar después si la IA realmente mezcló
        // 1º y 2º (o cubrió el mínimo de módulos) — el prompt es solo una petición, la IA
        // puede incumplirlo. Nunca se reintenta la llamada: un reintento duplica el gasto de
        // tokens de un prompt ya largo y puede disparar el rate-limit de OpenAI (tokens por
        // minuto), lo que arruina la experiencia con un 500. En su lugar, si no se cumple,
        // se avisa en el propio reto para que el profesorado lo revise manualmente.
        $moduloCursoPorNombre = $modulos->pluck('curso', 'nombre')->all();
        $cumpleCobertura = function (array $reto) use ($esAmbosCursos, $minModulos, $moduloCursoPorNombre) {
            $modulosCubiertos = collect($reto['evaluacion_oficial'] ?? [])->pluck('modulo')->filter()->unique();
            if ($modulosCubiertos->count() < $minModulos) {
                return false;
            }
            if ($esAmbosCursos) {
                $cursosCubiertos = $modulosCubiertos->map(fn ($m) => $moduloCursoPorNombre[$m] ?? null);
                if (!$cursosCubiertos->contains(1) || !$cursosCubiertos->contains(2)) {
                    return false;
                }
            }
            return true;
        };

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post("https://api.openai.com/v1/chat/completions", [
                "model"           => "gpt-4o",
                "messages"        => [
                    ["role" => "system", "content" => $systemPrompt],
                    ["role" => "user",   "content" => $userPrompt],
                ],
                "response_format" => ["type" => "json_object"],
                "temperature"     => 0.9,
            ]);

        if (!$response->successful()) {
            // Sin esto, un fallo de OpenAI (rate-limit, timeout, cuota agotada, prompt
            // rechazado...) llega al frontend como un 500 sin ninguna pista de la causa.
            Log::error('Fallo al contactar con OpenAI en generación de microreto.', [
                'status'     => $response->status(),
                'body'       => substr($response->body(), 0, 2000),
                'empresa_id' => $request->empresa_id,
                'ciclo_id'   => $request->ciclo_id,
            ]);
            return response()->json(['error' => 'Error al contactar con la IA'], 500);
        }

        $contenido = $response->json('choices.0.message.content');
        $data      = json_decode((string) $contenido, true);
        if (!isset($data['microretos']) || !is_array($data['microretos'])) {
            Log::error('Respuesta de OpenAI sin el formato esperado en generación de microreto.', [
                'contenido_bruto' => substr((string) $contenido, 0, 2000),
                'empresa_id'      => $request->empresa_id,
                'ciclo_id'        => $request->ciclo_id,
            ]);
            return response()->json($data);
        }

        foreach ($data['microretos'] as &$reto) {
            $reto['evaluacion_oficial'] = $raCeCatalogo->resolver(
                $reto['evaluacion_oficial'] ?? [],
                $raIndex
            );

            if (!empty($reglaCobertura) && !$cumpleCobertura($reto)) {
                $reto['aviso_cobertura_incompleta'] = true;
                Log::warning('Microreto generado sin cumplir la cobertura de módulos/cursos.', [
                    'empresa_id'   => $request->empresa_id,
                    'ciclo_id'     => $request->ciclo_id,
                    'ambos_cursos' => $esAmbosCursos,
                    'min_modulos'  => $minModulos,
                    'modulos_cubiertos' => collect($reto['evaluacion_oficial'])->pluck('modulo')->unique()->values()->all(),
                ]);
            }
        }
        unset($reto);

        return response()->json($data);
    }

    public function guardarEnBD(StoreMicroretoRequest $request)
    {
        // Docentes y admin de centro solo pueden guardar microretos de empresas de su centro
        $user = $request->user();
        if (($user->isDocente() || $user->isAdmin()) && !empty($request->empresa_id)) {
            $empresa = Empresa::find($request->empresa_id);
            if (!$empresa || !$empresa->perteneceAlCentroDe($user)) {
                return response()->json(['error' => 'No autorizado: la empresa no pertenece a tu centro educativo.'], 403);
            }
        }

        try {
            $datos = $request->validated();

            // Derivar y persistir el curso a partir del módulo y ciclo guardados
            if (empty($datos['curso'])) {
                $cicloId   = isset($datos['ciclo_id']) ? (int) $datos['ciclo_id'] : null;
                $cicloNom  = $datos['ciclo']  ?? null;
                $moduloNom = $datos['modulo'] ?? null;
                $datos['curso'] = MicroretoFichaService::derivarCurso($cicloId, $cicloNom, $moduloNom);
            }

            $microreto = Microreto::create($datos);
            return response()->json(['mensaje' => 'Micro-reto archivado', 'reto' => $microreto], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al guardar en BD: ' . $e->getMessage()], 500);
        }
    }

    public function guardarLote(Request $request)
    {
        $validated = $request->validate([
            'microretos'                        => 'required|array|max:50',
            'microretos.*.demo_id'              => 'nullable|integer|exists:demos,id',
            'microretos.*.empresa_id'           => 'nullable|integer|exists:empresas,id',
            'microretos.*.empresa_nombre'       => 'nullable|string|max:255',
            'microretos.*.titulo'               => 'nullable|string|max:500',
            'microretos.*.subtitulo'            => 'nullable|string|max:500',
            'microretos.*.quien_es'             => 'nullable|string|max:5000',
            'microretos.*.dia_a_dia'            => 'nullable|string|max:5000',
            'microretos.*.pregunta_reto'        => 'nullable|string|max:5000',
            'microretos.*.dificultades'         => 'nullable|array',
            'microretos.*.dificultades.*'       => 'nullable|string|max:1000',
            'microretos.*.que_necesitan'        => 'nullable|array',
            'microretos.*.que_necesitan.*'      => 'nullable|string|max:1000',
            'microretos.*.limitaciones'         => 'nullable|array',
            'microretos.*.limitaciones.*'       => 'nullable|string|max:1000',
            'microretos.*.prototipos'           => 'nullable|array',
            'microretos.*.prototipos.*'         => 'nullable|string|max:1000',
            'microretos.*.ods_sugeridos'        => 'nullable|array',
            'microretos.*.ods_sugeridos.*'      => 'nullable|string|max:255',
            'microretos.*.soft_skills'          => 'nullable|array',
            'microretos.*.soft_skills.*'        => 'nullable|string|max:255',
            'microretos.*.evaluacion_oficial'              => 'nullable|array',
            'microretos.*.evaluacion_oficial.*.modulo'     => 'nullable|string|max:255',
            'microretos.*.evaluacion_oficial.*.ra_id'      => 'nullable|integer|exists:resultados_aprendizaje,id',
            'microretos.*.evaluacion_oficial.*.ra'         => 'nullable|string|max:2000',
            'microretos.*.evaluacion_oficial.*.ce_ids'     => 'nullable|array',
            'microretos.*.evaluacion_oficial.*.ce_ids.*'   => 'integer|exists:criterios_evaluacion,id',
            'microretos.*.evaluacion_oficial.*.ce'         => 'nullable|array',
            'microretos.*.evaluacion_oficial.*.ce.*'       => 'nullable|string|max:1000',
            'microretos.*.evaluacion_oficial.*.aplicacion' => 'nullable|string|max:1000',
            'microretos.*.tips_profesorado'     => 'nullable|array',
            'microretos.*.tips_profesorado.*'   => 'nullable|string|max:2000',
            'microretos.*.variantes'            => 'nullable|array',
            'microretos.*.variantes.*'          => 'nullable|string|max:2000',
            'microretos.*.nivel_grupo'          => 'nullable|string|max:100',
            'microretos.*.curso'                => 'nullable|integer',
            'microretos.*.ciclo_id'             => 'nullable|integer|exists:ciclos_formativos,id',
            'microretos.*.ciclo'                => 'nullable|string|max:255',
            'microretos.*.modulo'               => 'nullable|string|max:255',
            'microretos.*.multimodulo'          => 'nullable|boolean',
            'microretos.*.duracion'             => 'nullable|string|max:100',
            'microretos.*.es_simulado'          => 'nullable|boolean',
        ]);

        $textFields = ['empresa_nombre', 'titulo', 'subtitulo', 'quien_es', 'dia_a_dia', 'pregunta_reto',
                       'ciclo', 'modulo', 'duracion', 'nivel_grupo'];
        $arrayFields = ['dificultades', 'que_necesitan', 'limitaciones', 'prototipos',
                        'ods_sugeridos', 'soft_skills', 'evaluacion_oficial', 'tips_profesorado', 'variantes'];

        // Docentes y admin de centro solo pueden guardar microretos de empresas de su centro
        $user = $request->user();
        if ($user->isDocente() || $user->isAdmin()) {
            foreach ($validated['microretos'] as $retoData) {
                $empresaId = $retoData['empresa_id'] ?? null;
                if ($empresaId) {
                    $empresa = Empresa::find($empresaId);
                    if (!$empresa || !$empresa->perteneceAlCentroDe($user)) {
                        return response()->json(['error' => 'No autorizado: alguna empresa no pertenece a tu centro educativo.'], 403);
                    }
                }
            }
        }

        try {
            $insertados = [];
            foreach ($validated['microretos'] as $retoData) {
                foreach ($textFields as $field) {
                    if (isset($retoData[$field]) && is_string($retoData[$field])) {
                        $retoData[$field] = strip_tags($retoData[$field]);
                    }
                }
                foreach ($arrayFields as $field) {
                    if (isset($retoData[$field]) && is_array($retoData[$field])) {
                        $retoData[$field] = $this->sanitizeRecursively($retoData[$field]);
                    }
                }

                if (empty($retoData['curso'])) {
                    $cicloId   = isset($retoData['ciclo_id']) ? (int) $retoData['ciclo_id'] : null;
                    $cicloNom  = $retoData['ciclo']  ?? null;
                    $moduloNom = $retoData['modulo'] ?? null;
                    $retoData['curso'] = MicroretoFichaService::derivarCurso($cicloId, $cicloNom, $moduloNom);
                }

                $insertados[] = Microreto::create($retoData);
            }
            return response()->json(['mensaje' => count($insertados) . ' Micro-retos archivados en lote con éxito'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al guardar el lote en BD: ' . $e->getMessage()], 500);
        }
    }

    /**
     * evaluacion_oficial contiene objetos anidados (modulo, ra, ce[], aplicacion),
     * a diferencia del resto de campos array que son listas planas de strings.
     */
    private function sanitizeRecursively(mixed $value): mixed
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizeRecursively($item), $value);
        }

        return $value;
    }

    public function destroy(Request $request, $id)
    {
        try {
            $microreto = Microreto::with('empresa')->findOrFail($id);
            $user      = $request->user();

            if (!$user->isSuperAdmin() && ($user->isDocente() || $user->isAdmin())) {
                if (!$microreto->empresa || !$microreto->empresa->perteneceAlCentroDe($user)) {
                    return response()->json(['error' => 'No autorizado: este micro-reto no pertenece a tu centro educativo.'], 403);
                }
            }

            $microreto->delete();
            return response()->json(['mensaje' => 'Micro-reto eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
}
