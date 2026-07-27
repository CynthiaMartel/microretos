<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Http\Requests\GuardarFaseEquipoRequest;
use App\Http\Requests\StoreEquipoTareaRequest;
use App\Http\Requests\UpdateEquipoTareaRequest;
use App\Http\Requests\StoreEquipoReflexionRequest;
use App\Http\Requests\StoreEquipoPrototipoRequest;
use App\Http\Requests\SugerirHallazgoRequest;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\EquipoMiembro;
use App\Models\EquipoPrototipo;
use App\Models\EquipoTarea;
use App\Models\EquipoReflexion;
use App\Models\Encuentro;
use App\Services\MicroretoFichaService;

class EquipoPublicoController extends Controller
{
    // Preguntas de síntesis predefinidas para F0.
    // El docente podrá personalizarlas en una iteración futura.
    const PREGUNTAS_F0 = [
        '¿Cuál es el problema principal que necesita resolver la empresa?',
        '¿A quién afecta este problema y de qué manera?',
        '¿Qué limitaciones o restricciones tenéis en cuenta para la solución?',
        '¿Qué recursos ya tiene disponibles la empresa?',
        '¿Qué resultado o entregable espera obtener la empresa?',
    ];

    // ── Acceso por código de clase ───────────────────────────────────────────

    /**
     * GET /api/clase/{codigo}
     * Resuelve el código de clase → devuelve el proyecto + lista de equipos con miembros.
     * El alumno elige su equipo y recibe el token de ese equipo.
     */
    public function porCodigoClase($codigo)
    {
        $encuentro = Encuentro::where('codigo_clase', strtoupper($codigo))->first();

        if (!$encuentro) {
            return response()->json(['error' => 'Código no válido.'], 404);
        }

        // Encuentro belongsTo Microproyecto (singular) — se consulta como query builder
        // sobre esa única relación, no como colección.
        $proyecto = $encuentro->microproyecto()
            ->whereIn('estado', ['propuesta', 'validado'])
            ->whereHas('equipos')
            ->with('equipos.miembros')
            ->first();

        if (!$proyecto) {
            return response()->json(['error' => 'El docente aún no ha creado los equipos. Pídele que regenere el código de acceso desde "Mis encuentros".'], 403);
        }

        return response()->json([
            'tipo'            => 'clase',
            'proyecto_titulo' => $proyecto->titulo,
            'curso'           => $proyecto->curso,
            'equipos'         => $proyecto->equipos->map(fn($e) => [
                'id'       => $e->id,
                'nombre'   => $e->nombre,
                'token'    => $e->token,
                'miembros' => $e->miembros->pluck('nombre'),
            ]),
        ]);
    }

    // ── Acceso por código corto (Kahoot) ─────────────────────────────────────

    /**
     * GET /api/equipo/unirse/{codigo}
     * Resuelve el código corto (ej. "XKM-479") al token largo y devuelve info básica.
     */
    public function unirse($codigo)
    {
        $equipo = Equipo::with('microproyecto')
            ->where('codigo_acceso', strtoupper($codigo))
            ->first();

        if (!$equipo) {
            return response()->json(['error' => 'Código no válido. Comprueba que lo has escrito bien.'], 404);
        }

        if (!in_array($equipo->microproyecto->estado, ['propuesta', 'validado'])) {
            return response()->json(['error' => 'El proyecto aún no está activo. Espera a que el docente lo publique.'], 403);
        }

        return response()->json([
            'token'           => $equipo->token,
            'nombre_equipo'   => $equipo->nombre,
            'proyecto_titulo' => $equipo->microproyecto->titulo,
            'fase_actual'     => $equipo->fase_actual,
        ]);
    }

    // ── Workspace del equipo ─────────────────────────────────────────────────

    /**
     * GET /api/equipo/{token}
     * Carga todos los datos necesarios para el workspace del equipo.
     */
    public function show($token)
    {
        $equipo = Equipo::with([
            'microproyecto.microreto',
            'miembros',
            'fases',
            'tareas',
            'reflexiones',
            'prototipos',
        ])->where('token', $token)->first();

        if (!$equipo) {
            return response()->json(['error' => 'Enlace no válido.'], 404);
        }

        if (!in_array($equipo->microproyecto->estado, ['propuesta', 'validado'])) {
            return response()->json(['error' => 'El proyecto no está activo.'], 403);
        }

        return response()->json($this->formatWorkspace($equipo));
    }

    /**
     * GET /api/equipo/{token}/reto
     * Ficha completa del reto asociado al equipo — misma forma de datos que
     * MicroretoIAController::show(), para reutilizar MicroretoModal.vue en el workspace público.
     */
    public function verReto($token)
    {
        $equipo = Equipo::with([
            'microproyecto.microreto.empresa.centroEducativo',
            'microproyecto.microreto.empresa.familias',
        ])->where('token', $token)->first();

        if (!$equipo) {
            return response()->json(['error' => 'Enlace no válido.'], 404);
        }

        $reto = $equipo->microproyecto->microreto;
        if (!$reto) {
            return response()->json(['error' => 'Este proyecto no tiene un reto asociado.'], 404);
        }

        return response()->json(MicroretoFichaService::enriquecer($reto));
    }

    // ── Guardar datos de fase ────────────────────────────────────────────────

    /**
     * PUT /api/equipo/{token}/fase/{fase}
     * Guarda el JSON de datos de una fase (auto-crea el registro si no existe).
     */
    public function guardarFase(GuardarFaseEquipoRequest $request, $token, int $numeroFase)
    {
        if (!in_array($numeroFase, [0, 1, 2, 3, 4])) {
            return response()->json(['error' => 'Fase no válida.'], 422);
        }

        $equipo = Equipo::where('token', $token)->firstOrFail();

        $validated = $request->validated();

        $fase = EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            ['datos' => $validated['datos']]
        );

        // Sincronizar miembros si vienen en F0
        if ($numeroFase === 0 && isset($validated['datos']['miembros'])) {
            $this->sincronizarMiembros($equipo, $validated['datos']['miembros']);
        }

        return response()->json(['ok' => true, 'fase' => $this->formatFase($fase)]);
    }

    /**
     * POST /api/equipo/{token}/fase/{fase}/completar
     * Marca la fase como completada y avanza fase_actual del equipo.
     */
    public function completarFase(Request $request, $token, int $numeroFase)
    {
        if (!in_array($numeroFase, [0, 1, 2, 3, 4])) {
            return response()->json(['error' => 'Fase no válida.'], 422);
        }

        $equipo = Equipo::where('token', $token)->firstOrFail();

        EquipoFase::updateOrCreate(
            ['equipo_id' => $equipo->id, 'numero_fase' => $numeroFase],
            ['completada' => true, 'fecha_completada' => now()]
        );

        // Al pasar de Análisis (F1) a Diseño de solución y desarrollo (F2), se precarga
        // la secuencia genérica de tareas de la ficha del reto (solo si el equipo
        // no tiene ninguna tarea propia todavía).
        if ($numeroFase === 1) {
            $this->seedTareasGenericas($equipo);
        }

        if ($equipo->fase_actual <= $numeroFase) {
            $equipo->update(['fase_actual' => min($numeroFase + 1, 4)]);
        }

        return response()->json(['ok' => true, 'fase_actual' => $equipo->fresh()->fase_actual]);
    }

    // Secuencia genérica de tareas — proceso de trabajo (buscar, organizar, idear,
    // construir, revisar, entregar), no pasos de una solución concreta — que se
    // precarga como plantilla editable al entrar en F2. El QA interno es obligatoria:
    // el equipo no puede borrarla (ver destroyTarea()).
    const TAREAS_GENERICAS = [
        ['descripcion' => 'Buscar información sobre vuestra propuesta (referencias, ejemplos similares, datos que la apoyen)', 'obligatoria' => false],
        ['descripcion' => 'Organizar la información recopilada y decidir qué vais a usar', 'obligatoria' => false],
        ['descripcion' => 'Lluvia de ideas en equipo: cómo llevar a la práctica vuestra solución', 'obligatoria' => false],
        ['descripcion' => 'Convertir el prototipo inicial en una versión más detallada', 'obligatoria' => false],
        ['descripcion' => 'Revisar el diseño final: que sea claro, usable y esté bien presentado', 'obligatoria' => false],
        ['descripcion' => 'QA interno: revisar entre todo el equipo antes de dar nada por terminado', 'obligatoria' => true],
        ['descripcion' => 'Preparar la entrega final (versión editable + versión para compartir)', 'obligatoria' => false],
    ];

    private function seedTareasGenericas(Equipo $equipo): void
    {
        if (EquipoTarea::where('equipo_id', $equipo->id)->exists()) {
            return;
        }

        $this->crearTareasGenericasFaltantes($equipo);
    }

    /**
     * POST /api/equipo/{token}/fase/2/restablecer-tareas-genericas
     * Vuelve a añadir las tareas genéricas precargadas que falten (p.ej. si el equipo borró
     * alguna por error). No duplica las que ya existan ni toca las tareas más complejas.
     */
    public function restablecerTareasGenericas($token): JsonResponse
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();
        $creadas = $this->crearTareasGenericasFaltantes($equipo);

        return response()->json($creadas->values(), 201);
    }

    private function crearTareasGenericasFaltantes(Equipo $equipo): \Illuminate\Support\Collection
    {
        $existentes = EquipoTarea::where('equipo_id', $equipo->id)
            ->where('tipo', 'proceso')
            ->pluck('descripcion')
            ->all();

        $orden = (int) (EquipoTarea::where('equipo_id', $equipo->id)->max('orden') ?? -1);

        $creadas = collect();
        foreach (self::TAREAS_GENERICAS as $item) {
            if (in_array($item['descripcion'], $existentes, true)) {
                continue;
            }
            $orden++;
            $creadas->push(EquipoTarea::create([
                'equipo_id'   => $equipo->id,
                'descripcion' => $item['descripcion'],
                'tipo'        => 'proceso',
                'obligatoria' => $item['obligatoria'],
                'orden'       => $orden,
            ]));
        }

        return $creadas;
    }

    // ── Sugerencias IA ───────────────────────────────────────────────────────

    /**
     * POST /api/equipo/{token}/fase/1/sugerir-hallazgo
     * Genera UN hallazgo de ejemplo a partir del contexto del microreto,
     * para que el equipo entienda qué se espera y añada al menos 3 propios.
     * Recibe los hallazgos ya propuestos en esta sesión para no repetirlos en clics sucesivos.
     */
    public function sugerirHallazgo(SugerirHallazgoRequest $request, $token): JsonResponse
    {
        $equipo = Equipo::with('microproyecto.microreto')->where('token', $token)->firstOrFail();
        $mr = $equipo->microproyecto->microreto;

        $contexto = $this->contextoMicroreto($mr);
        if (!$contexto) {
            return response()->json(['error' => 'No hay información suficiente del reto para generar un ejemplo.'], 422);
        }

        $existentes = collect($request->validated('existentes', []))
            ->map(fn($h) => trim($h))
            ->filter()
            ->values();

        $prompt = $contexto;
        if ($existentes->isNotEmpty()) {
            $prompt .= "\n\nHallazgos ya propuestos en esta sesión (genera uno DISTINTO, no los repitas ni parafrasees):\n- "
                . $existentes->implode("\n- ");
        }

        // La lista de hallazgos ya propuestos forma parte de la clave de caché: cada clic sucesivo
        // cambia el prompt (evita repetir), así que también cambia la clave y dispara una llamada nueva.
        $cacheKey  = 'hallazgo_ejemplo_' . md5($prompt);
        $resultado = Cache::remember($cacheKey, now()->addHours(6), function () use ($prompt) {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un docente de Formación Profesional que ayuda a un equipo de alumnado a analizar el reto de una empresa. A partir del contexto, redacta UN hallazgo de ejemplo: un dato o conclusión concreta (no genérica) que justificaría una propuesta de solución. Es solo un ejemplo para guiar al equipo — ellos añadirán más hallazgos propios. Escríbelo en UNA sola frase corta (máximo 25 palabras), con lenguaje sencillo y directo, fácil de entender para alumnado de FP — nada de tecnicismos ni frases largas.'],
                        ['role' => 'user',   'content' => "Contexto del reto:\n{$prompt}\nDevuelve SOLO este JSON:\n{\"hallazgo\":\"texto del hallazgo de ejemplo\"}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.9,
                ]);

            if (!$response->successful()) return null;
            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado || empty($resultado['hallazgo'])) {
            return response()->json(['error' => 'No se pudo generar la sugerencia. Inténtalo de nuevo.'], 502);
        }

        return response()->json(['hallazgo' => $resultado['hallazgo']]);
    }

    /**
     * POST /api/equipo/{token}/fase/2/sugerir-tareas
     * Genera 3-4 tareas para la sección "tareas más complejas" (tipo=detalle_solucion):
     * un poco más concretas que las genéricas precargadas, detallando la propuesta de F1
     * sin llegar a diseñar la solución técnica al completo. Recibe las tareas de este tipo
     * que el equipo ya tiene para no repetirlas.
     */
    public function sugerirTareas($token): JsonResponse
    {
        $equipo = Equipo::with('microproyecto.microreto', 'fases', 'tareas')->where('token', $token)->firstOrFail();
        $mr        = $equipo->microproyecto->microreto;
        $fase1     = $equipo->fases->firstWhere('numero_fase', 1);
        $propuesta = $fase1->datos['propuesta'] ?? null;

        if (!$propuesta) {
            return response()->json(['error' => 'Completad primero la propuesta de solución en la fase de Análisis del reto.'], 422);
        }

        $tareasExistentes = $equipo->tareas->where('tipo', 'detalle_solucion')->pluck('descripcion')->filter()->values();

        $contexto = "Propuesta de solución del equipo: {$propuesta}\n" . $this->contextoMicroreto($mr);
        if ($tareasExistentes->isNotEmpty()) {
            $contexto .= "\n\nTareas de este tipo que el equipo ya tiene (no las repitas ni las parafrasees, proponed otras distintas):\n- "
                . $tareasExistentes->implode("\n- ");
        }

        // La lista de tareas ya existentes forma parte de la clave de caché: cada tarea añadida
        // cambia el prompt (evita repetir), así que también cambia la clave y dispara una llamada nueva.
        $cacheKey  = 'tareas_sugeridas_' . md5($contexto);
        $resultado = Cache::remember($cacheKey, now()->addHour(), function () use ($contexto) {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un docente de Formación Profesional que ayuda a un equipo de alumnado a detallar y construir SU propuesta de solución. A partir de la propuesta y el contexto del reto, genera entre 3 y 4 tareas algo más concretas y complejas que las tareas genéricas de trabajo (buscar información, organizar, lluvia de ideas) que el equipo ya tiene — pueden mencionar aspectos concretos de la propuesta (qué construir, qué contenidos o elementos incluir), pero SIN diseñar la solución técnica al detalle ni proponer una arquitectura o especificación completa: siguen siendo tareas de trabajo para el equipo, no una solución hecha por ti. No incluyas tareas de QA ni de entrega final, esas ya están cubiertas aparte.'],
                        ['role' => 'user',   'content' => "Contexto:\n{$contexto}\nDevuelve SOLO este JSON:\n{\"tareas\":[\"tarea concreta sobre la propuesta\"]}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.6,
                ]);

            if (!$response->successful()) return null;
            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado || empty($resultado['tareas'])) {
            return response()->json(['error' => 'No se pudo generar la sugerencia. Inténtalo de nuevo.'], 502);
        }

        $orden   = (int) (EquipoTarea::where('equipo_id', $equipo->id)->max('orden') ?? -1);
        $creadas = collect($resultado['tareas'])->map(function ($descripcion) use ($equipo, &$orden) {
            $orden++;
            return EquipoTarea::create([
                'equipo_id'   => $equipo->id,
                'descripcion' => $descripcion,
                'tipo'        => 'detalle_solucion',
                'orden'       => $orden,
            ]);
        });

        return response()->json($creadas->values(), 201);
    }

    private function contextoMicroreto(?\App\Models\Microreto $mr): string
    {
        if (!$mr) return '';

        $contexto = '';
        if ($mr->quien_es)      $contexto .= "Quiénes son: {$mr->quien_es}\n";
        if ($mr->dia_a_dia)     $contexto .= "Su día a día: {$mr->dia_a_dia}\n";
        if ($mr->pregunta_reto) $contexto .= "Pregunta del reto: {$mr->pregunta_reto}\n";
        if ($mr->que_necesitan) $contexto .= "Qué necesitan: " . implode('; ', $mr->que_necesitan) . "\n";
        if ($mr->dificultades)  $contexto .= "Dificultades: " . implode('; ', $mr->dificultades) . "\n";

        return $contexto;
    }

    // ── Tareas F2 ────────────────────────────────────────────────────────────

    public function storeTarea(StoreEquipoTareaRequest $request, $token)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();

        $data = $request->validated();

        $data['equipo_id'] = $equipo->id;
        $data['orden']     = EquipoTarea::where('equipo_id', $equipo->id)->count();

        $tarea = EquipoTarea::create($data);
        return response()->json($tarea, 201);
    }

    public function updateTarea(UpdateEquipoTareaRequest $request, $token, int $tareaId)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();
        $tarea  = EquipoTarea::where('id', $tareaId)->where('equipo_id', $equipo->id)->firstOrFail();

        $data = $request->validated();

        $tarea->update($data);
        return response()->json($tarea);
    }

    public function destroyTarea($token, int $tareaId)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();
        $tarea  = EquipoTarea::where('id', $tareaId)->where('equipo_id', $equipo->id)->firstOrFail();

        if ($tarea->obligatoria) {
            return response()->json(['error' => 'Esta tarea es obligatoria y no se puede eliminar.'], 422);
        }

        $tarea->delete();
        return response()->json(['ok' => true]);
    }

    // ── Reflexiones F4 ───────────────────────────────────────────────────────

    public function storeReflexion(StoreEquipoReflexionRequest $request, $token)
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();

        $data = $request->validated();

        // Solo una reflexión grupal por equipo
        if ($data['tipo'] === 'grupal') {
            EquipoReflexion::where('equipo_id', $equipo->id)->where('tipo', 'grupal')->delete();
        }

        $reflexion = EquipoReflexion::create([
            'equipo_id'    => $equipo->id,
            'tipo'         => $data['tipo'],
            'autor_nombre' => $data['autor_nombre'] ?? null,
            'respuestas'   => $data['respuestas'],
        ]);

        return response()->json($reflexion, 201);
    }

    // ── Prototipos — archivos subidos a Cloudinary ───────────────────────────

    /**
     * POST /api/equipo/{token}/prototipos
     * Sube un archivo a Cloudinary y guarda sus metadatos en equipo_prototipos.
     */
    public function storePrototipo(StoreEquipoPrototipoRequest $request, $token): JsonResponse
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');
        $folder    = config('services.cloudinary.folder', 'dualab/recursos');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return response()->json(['error' => 'Cloudinary no configurado.'], 503);
        }

        $file = $request->file('file');
        $mime = $file->getMimeType() ?? '';

        $resourceType = match(true) {
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'image/') => 'image',
            default                           => 'raw',
        };

        $timestamp    = time();
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash('sha1', $paramsToSign . $apiSecret);

        $response = Http::attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error al subir el archivo a Cloudinary.'], 502);
        }

        $data = $response->json();

        $prototipo = EquipoPrototipo::create([
            'equipo_id'     => $equipo->id,
            'contexto'      => $request->validated('contexto', 'prototipo'),
            'filename'      => $file->getClientOriginalName(),
            'url'           => $data['secure_url'],
            'public_id'     => $data['public_id'],
            'resource_type' => $resourceType,
            'mime'          => $mime,
            'size'          => $data['bytes'] ?? 0,
            'label'         => $request->input('label'),
        ]);

        return response()->json([
            'id'            => $prototipo->id,
            'contexto'      => $prototipo->contexto,
            'url'           => $prototipo->url,
            'public_id'     => $prototipo->public_id,
            'resource_type' => $prototipo->resource_type,
            'filename'      => $prototipo->filename,
            'label'         => $prototipo->label,
            'size'          => $prototipo->size,
            'mime'          => $prototipo->mime,
        ], 201);
    }

    /**
     * DELETE /api/equipo/{token}/prototipos/{id}
     * Elimina el archivo de Cloudinary y su registro en BD.
     */
    public function destroyPrototipo($token, int $id): JsonResponse
    {
        $equipo    = Equipo::where('token', $token)->firstOrFail();
        $prototipo = EquipoPrototipo::where('id', $id)
            ->where('equipo_id', $equipo->id)
            ->firstOrFail();

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        // Eliminar de BD primero; si falla Cloudinary el registro no queda huérfano
        $publicId     = $prototipo->public_id;
        $resourceType = $prototipo->resource_type;
        $prototipo->delete();

        if ($cloudName && $apiKey && $apiSecret) {
            $timestamp    = time();
            $paramsToSign = "public_id={$publicId}&timestamp={$timestamp}";
            $signature    = hash('sha1', $paramsToSign . $apiSecret);

            Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    private function formatWorkspace(Equipo $equipo): array
    {
        $mp = $equipo->microproyecto;
        $mr = $mp->microreto;

        return [
            'equipo' => [
                'id'            => $equipo->id,
                'nombre'        => $equipo->nombre,
                'codigo_acceso' => $equipo->codigo_acceso,
                'fase_actual'   => $equipo->fase_actual,
                'miembros'      => $equipo->miembros->map(fn($m) => [
                    'id'         => $m->id,
                    'nombre'     => $m->nombre,
                    'rol'        => $m->rol,
                    'fortalezas'    => $m->fortalezas,
                    'puntos_mejora' => $m->puntos_mejora,
                ]),
            ],
            'proyecto' => [
                'titulo'        => $mp->titulo,
                'curso'         => $mp->curso,
                'empresa_nombre' => $mp->datos_empresa['nombre'] ?? null,
                'centro_nombre'  => $mp->datos_centro['nombre'] ?? null,
                'docente_nombre' => $mp->datos_centro['docente_nombre'] ?? null,
                'objetivos'     => $mp->objetivos['lista'] ?? [],
                'kpis'          => $mp->kpis['lista'] ?? [],
                'diseno_microproyecto' => $mp->diseno_microproyecto,
            ],
            // Diagnóstico de empresa del microreto origen (solo lectura en F0)
            'diagnostico' => $mr ? [
                'quien_es'      => $mr->quien_es,
                'dia_a_dia'     => $mr->dia_a_dia,
                'pregunta_reto' => $mr->pregunta_reto,
                'que_necesitan' => $mr->que_necesitan,
                'dificultades'  => $mr->dificultades,
                'limitaciones'  => $mr->limitaciones,
                'prototipos'    => $mr->prototipos,
            ] : null,
            // Preguntas de síntesis para que el equipo responda en F0
            'preguntas_f0' => self::PREGUNTAS_F0,
            // Estado de cada fase
            'fases' => collect(range(0, 4))->map(function ($n) use ($equipo) {
                $fase = $equipo->fases->firstWhere('numero_fase', $n);
                return $this->formatFase($fase, $n);
            }),
            // Tareas F2
            'tareas' => $equipo->tareas->map(fn($t) => [
                'id'          => $t->id,
                'descripcion' => $t->descripcion,
                'responsable' => $t->responsable,
                'estado'      => $t->estado,
            ]),
            // Reflexiones F4
            'reflexiones' => $equipo->reflexiones->map(fn($r) => [
                'id'           => $r->id,
                'tipo'         => $r->tipo,
                'autor_nombre' => $r->autor_nombre,
                'respuestas'   => $r->respuestas,
                'created_at'   => $r->created_at,
            ]),
            // Archivos de prototipo subidos a Cloudinary (F1)
            'prototipos' => $equipo->prototipos->map(fn($p) => [
                'id'            => $p->id,
                'contexto'      => $p->contexto,
                'url'           => $p->url,
                'public_id'     => $p->public_id,
                'resource_type' => $p->resource_type,
                'filename'      => $p->filename,
                'label'         => $p->label,
                'size'          => $p->size,
                'mime'          => $p->mime,
            ]),
        ];
    }

    private function formatFase(?EquipoFase $fase, int $numero = 0): array
    {
        if (!$fase) {
            return [
                'numero_fase'              => $numero,
                'datos'                    => null,
                'completada'               => false,
                'fecha_completada'         => null,
                'validado_docente'         => false,
                'fecha_validacion_docente' => null,
                'nota_docente'             => null,
                'observaciones_docente'    => null,
            ];
        }

        return [
            'numero_fase'              => $fase->numero_fase,
            'datos'                    => $fase->datos,
            'completada'               => $fase->completada,
            'fecha_completada'         => $fase->fecha_completada,
            'validado_docente'         => $fase->validado_docente,
            'fecha_validacion_docente' => $fase->fecha_validacion_docente,
            'nota_docente'             => $fase->nota_docente,
            'observaciones_docente'    => $fase->observaciones_docente,
        ];
    }

    private function sincronizarMiembros(Equipo $equipo, array $miembros): void
    {
        $equipo->miembros()->delete();
        foreach ($miembros as $m) {
            if (!empty($m['nombre'])) {
                $equipo->miembros()->create([
                    'nombre'        => $m['nombre'],
                    'rol'           => $m['rol'] ?? null,
                    'fortalezas'    => $m['fortalezas'] ?? [],
                    'puntos_mejora' => $m['puntos_mejora'] ?? [],
                ]);
            }
        }
    }
}
