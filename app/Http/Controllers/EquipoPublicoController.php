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
use App\Http\Requests\StoreImagenProyectoRequest;
use App\Http\Requests\SugerirHallazgoRequest;
use App\Http\Requests\VerificarCodigoIaRequest;
use App\Http\Resources\MicroretoFichaResource;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\EquipoMiembro;
use App\Models\EquipoPrototipo;
use App\Models\EquipoTarea;
use App\Models\EquipoReflexion;
use App\Models\Encuentro;
use App\Models\MicroproyectoRecurso;
use App\Services\MicroretoFichaService;
use App\Support\AliasGenerator;

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

        $proyecto = $encuentro->microproyecto()
            ->whereIn('estado', ['propuesta', 'validado', 'completado'])
            ->first();

        if (!$proyecto) {
            return response()->json(['error' => 'El docente aún no ha creado los equipos. Pídele que regenere el código de acceso desde "Mis encuentros".'], 403);
        }

        // Equipos de ESTE encuentro concreto, no de todos los que compartan microproyecto.
        $equipos = $encuentro->equipos()->with('miembros')->get();

        if ($equipos->isEmpty()) {
            return response()->json(['error' => 'El docente aún no ha creado los equipos. Pídele que regenere el código de acceso desde "Mis encuentros".'], 403);
        }

        return response()->json([
            'tipo'            => 'clase',
            'proyecto_titulo' => $proyecto->titulo,
            'curso'           => $proyecto->curso,
            'equipos'         => $equipos->map(fn($e) => [
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

        if (!in_array($equipo->microproyecto->estado, ['propuesta', 'validado', 'completado'])) {
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
            'microproyecto.recursos',
            'miembros',
            'fases',
            'tareas',
            'reflexiones',
            'prototipos',
        ])->where('token', $token)->first();

        if (!$equipo) {
            return response()->json(['error' => 'Enlace no válido.'], 404);
        }

        if (!in_array($equipo->microproyecto->estado, ['propuesta', 'validado', 'completado'])) {
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

        // MicroretoFichaResource: whitelist explícita de campos — este endpoint es público
        // (solo requiere el token del equipo), nunca debe devolver el modelo Empresa crudo
        // (CIF, teléfono, email, persona de contacto, dirección quedan fuera a propósito).
        return response()->json(new MicroretoFichaResource(MicroretoFichaService::enriquecer($reto)));
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

    /**
     * POST /api/equipo/{token}/fase/0/confirmar-nombres
     * Paso explícito de F0, distinto de guardar/completar la fase: a partir de aquí el
     * nombre real de cada miembro queda bloqueado (ni el equipo ni el docente pueden
     * cambiarlo desde "Editar equipo" — ver sincronizarMiembros() aquí y
     * EncuentroController::sincronizarMiembrosPorNombre()).
     */
    public function confirmarNombres($token): JsonResponse
    {
        $equipo = Equipo::where('token', $token)->firstOrFail();

        if ($equipo->miembros()->count() === 0) {
            return response()->json(['error' => 'Añade al menos un integrante antes de confirmar los nombres.'], 422);
        }

        $equipo->update(['nombres_confirmados' => true]);

        return response()->json(['ok' => true, 'nombres_confirmados' => true]);
    }

    // Secuencia genérica de tareas — proceso de trabajo (buscar, organizar, idear, elegir,
    // construir, revisar, entregar), no pasos de una solución concreta — que se precarga como
    // plantilla editable al entrar en F2. Hay dos obligatorias que el equipo no puede borrar
    // (ver destroyTarea()): elegir la solución entre las sugerencias y el QA final. El frontend
    // las ordena siempre igual: la de elegir solución al principio, QA al final (ver
    // tareasGenericas/prioridadTarea en EquipoWorkspace.vue), independientemente de este orden.
    const TAREAS_GENERICAS = [
        ['descripcion' => 'Buscar información sobre vuestra propuesta (referencias, ejemplos similares, datos que la apoyen)', 'obligatoria' => false],
        ['descripcion' => 'Organizar la información recopilada y decidir qué vais a usar', 'obligatoria' => false],
        ['descripcion' => 'Lluvia de ideas en equipo: cómo llevar a la práctica vuestra solución', 'obligatoria' => false],
        ['descripcion' => 'Elegid en equipo las soluciones que vais a llevar a cabo', 'obligatoria' => true],
        ['descripcion' => 'Convertir el prototipo inicial en una versión más detallada', 'obligatoria' => false],
        ['descripcion' => 'Revisar el diseño final: que sea claro, usable y esté bien presentado', 'obligatoria' => false],
        ['descripcion' => 'QA (control de calidad): revisad entre todo el equipo el trabajo antes de dar nada por terminado', 'obligatoria' => true],
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
     * POST /api/equipo/{token}/ia/verificar-codigo
     * Comprueba el código repartido por el docente y, si coincide, desbloquea
     * "Sugerir con IA" para todo el equipo (persiste en equipos.ia_desbloqueada).
     */
    public function verificarCodigoIa(VerificarCodigoIaRequest $request, $token): JsonResponse
    {
        $equipo = Equipo::with('encuentro')->where('token', $token)->firstOrFail();

        if (empty($equipo->encuentro?->codigo_ia)) {
            return response()->json([
                'success' => false,
                'message' => 'El docente todavía no ha generado un código para esta función.',
            ], 422);
        }

        if (strtoupper(trim($request->validated('codigo'))) !== $equipo->encuentro->codigo_ia) {
            return response()->json(['success' => false, 'message' => 'Código incorrecto.'], 401);
        }

        $equipo->update(['ia_desbloqueada' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/equipo/{token}/fase/1/sugerir-hallazgo
     * Genera UN hallazgo de ejemplo a partir del contexto del microreto,
     * para que el equipo entienda qué se espera y añada al menos 3 propios.
     * Recibe los hallazgos ya propuestos en esta sesión para no repetirlos en clics sucesivos.
     */
    public function sugerirHallazgo(SugerirHallazgoRequest $request, $token): JsonResponse
    {
        $equipo = Equipo::with('microproyecto.microreto')->where('token', $token)->firstOrFail();

        if (!$equipo->ia_desbloqueada) {
            return response()->json(['error' => 'Esta función requiere el código de desbloqueo. Pídeselo a tu docente.'], 403);
        }

        $mr = $equipo->microproyecto->microreto;

        $contexto = $this->contextoMicroreto($mr);
        if (!$contexto) {
            return response()->json(['error' => 'No hay información suficiente del reto para generar un ejemplo.'], 422);
        }

        $existentes = collect($request->validated('existentes', []))
            ->map(fn($h) => trim($h))
            ->filter();

        $sugeridasIa = collect($request->validated('sugeridas_ia', []))
            ->map(fn($h) => trim($h))
            ->filter();

        // Unimos lo que el equipo ya tiene escrito con lo que la IA ya generó en esta sesión
        // (aunque el equipo lo haya borrado de su lista): en ambos casos no queremos repetirlo.
        $noRepetir = $existentes->merge($sugeridasIa)->unique()->values();

        $coincide = fn($h) => $noRepetir->contains(fn($nr) => mb_strtolower($nr) === mb_strtolower($h));

        // Cola de hallazgos pre-generados por equipo: pedimos 4 a la IA en una sola llamada
        // y los servimos de uno en uno en clics sucesivos sin volver a llamar a OpenAI, para
        // que de cara al equipo cada clic siga pareciendo una generación nueva.
        $cacheKey = 'hallazgos_ia_cola_v1_' . $equipo->id;
        $cola = collect(Cache::get($cacheKey, []))->reject($coincide)->values();

        if ($cola->isEmpty()) {
            $prompt = $contexto;
            if ($noRepetir->isNotEmpty()) {
                $prompt .= "\n\nHallazgos ya propuestos en esta sesión, tuyos o del equipo (genera 4 DISTINTOS, no los repitas ni parafrasees):\n- "
                    . $noRepetir->implode("\n- ");
            }

            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un docente de Formación Profesional que ayuda a un equipo de alumnado a analizar el reto de una empresa. Redacta 4 hallazgos de ejemplo, DISTINTOS entre sí: cada uno es una OBSERVACIÓN sobre la situación o el problema actual de la empresa, apoyada en el contexto (quiénes son, su día a día, qué necesitan, sus dificultades) — algo que ya está pasando o que falta hoy, no algo que la empresa podría hacer en el futuro. Un hallazgo NUNCA debe mencionar una solución, tecnología, sistema o proceso a construir, ni predecir resultados de algo que todavía no existe — eso es una propuesta de solución, no un hallazgo, y se trabaja en otra fase distinta. Ejemplo de hallazgo VÁLIDO: "La empresa no lleva ningún registro histórico de cuándo florecen sus plantas cada año." Ejemplo NO VÁLIDO porque ya describe una solución: "Registrando la temperatura se podría predecir la cosecha con un 85% de precisión." No inventes cifras, porcentajes ni estadísticas que no estén explícitas en el contexto — si no hay datos numéricos, razona en términos cualitativos. Los 4 hallazgos deben ser observaciones distintas entre sí, no reformulaciones de la misma idea — son solo ejemplos para guiar al equipo, ellos añadirán más hallazgos propios. Escribe cada uno en UNA sola frase corta (máximo 25 palabras), con lenguaje sencillo y directo, fácil de entender para alumnado de FP — nada de tecnicismos ni frases largas.'],
                        ['role' => 'user',   'content' => "Contexto del reto:\n{$prompt}\nDevuelve SOLO este JSON:\n{\"hallazgos\":[\"hallazgo 1\",\"hallazgo 2\",\"hallazgo 3\",\"hallazgo 4\"]}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.7,
                ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'No se pudo generar la sugerencia. Inténtalo de nuevo.'], 502);
            }

            $resultado = json_decode($response->json()['choices'][0]['message']['content'], true);
            $cola = collect($resultado['hallazgos'] ?? [])
                ->map(fn($h) => trim($h))
                ->filter()
                ->reject($coincide)
                ->values();
        }

        if ($cola->isEmpty()) {
            return response()->json(['error' => 'No se pudo generar la sugerencia. Inténtalo de nuevo.'], 502);
        }

        $hallazgo = $cola->shift();
        Cache::put($cacheKey, $cola->values()->all(), now()->addHours(6));

        return response()->json(['hallazgo' => $hallazgo]);
    }

    /**
     * POST /api/equipo/{token}/fase/2/sugerir-tareas
     * Genera 3-4 sugerencias de posibles soluciones (tipo=detalle_solucion) a partir de la
     * propuesta inicial del equipo, las necesidades/limitaciones/pregunta del reto y el
     * prototipo elegido. Son ideas a valorar por el equipo, no tareas de trabajo — no llevan
     * estado ni responsable. Recibe las sugerencias que el equipo ya tiene para no repetirlas.
     */
    public function sugerirTareas($token): JsonResponse
    {
        $equipo = Equipo::with('microproyecto.microreto', 'fases', 'tareas')->where('token', $token)->firstOrFail();

        if (!$equipo->ia_desbloqueada) {
            return response()->json(['error' => 'Esta función requiere el código de desbloqueo. Pídeselo a tu docente.'], 403);
        }

        $mr           = $equipo->microproyecto->microreto;
        $fase2        = $equipo->fases->firstWhere('numero_fase', 2);
        $propuesta    = $fase2->datos['propuesta'] ?? null;
        $explicacion  = $fase2->datos['explicacion_propuesta'] ?? null;

        if (!$propuesta) {
            return response()->json(['error' => 'Completad primero la propuesta inicial de solución de esta fase.'], 422);
        }

        $sugerenciasExistentes = $equipo->tareas->where('tipo', 'detalle_solucion')->pluck('descripcion')->filter()->values();

        $contexto = "Propuesta inicial de solución del equipo: {$propuesta}\n";
        if ($explicacion) {
            $contexto .= "Por qué creen que responde al reto: {$explicacion}\n";
        }
        $contexto .= $this->contextoMicroreto($mr);

        $tipoPrototipo = $fase2->datos['tipo_prototipo'] ?? null;
        if ($tipoPrototipo) {
            $contexto .= "Prototipo elegido por el equipo: {$tipoPrototipo}\n";
        }

        if ($sugerenciasExistentes->isNotEmpty()) {
            $contexto .= "\n\nSugerencias de este tipo que el equipo ya tiene (no las repitas ni las parafrasees, proponed otras distintas):\n- "
                . $sugerenciasExistentes->implode("\n- ");
        }

        // La lista de sugerencias ya existentes forma parte de la clave de caché: cada sugerencia
        // añadida cambia el prompt (evita repetir), así que también cambia la clave y dispara una llamada nueva.
        // El sufijo de versión cambia cuando se retoca el prompt — evita servir de caché una
        // respuesta generada con una redacción antigua para el mismo contexto.
        $cacheKey  = 'soluciones_sugeridas_v2_' . md5($contexto);
        $resultado = Cache::remember($cacheKey, now()->addHour(), function () use ($contexto) {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => 'gpt-4o',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un docente de Formación Profesional que ayuda a un equipo de alumnado a explorar posibles soluciones para SU propuesta. A partir de la propuesta inicial, las necesidades y limitaciones de la empresa, la pregunta del reto y el prototipo elegido, genera entre 3 y 4 sugerencias de solución: cada una debe describir una ACCIÓN O ARTEFACTO CONCRETO que el equipo podría construir o hacer (una funcionalidad, un formato, un enfoque técnico o de proceso) — nunca una observación sobre cómo es la empresa o cuál es su problema, eso ya se analizó en la fase anterior y no debe repetirse aquí. Ejemplo VÁLIDO: "Crear una plantilla compartida donde el equipo de campo anote la floración semana a semana." Ejemplo NO VÁLIDO porque es un hallazgo, no una solución: "La empresa no tiene ningún sistema para registrar la floración." No son tareas de trabajo ni pasos a ejecutar y marcar como hechos, son ideas concretas que el equipo debe valorar con criterio propio, sin llegar a diseñar la solución técnica al detalle ni proponer una arquitectura o especificación completa. No incluyas nada relativo a QA ni a la entrega final, eso ya está cubierto aparte.'],
                        ['role' => 'user',   'content' => "Contexto:\n{$contexto}\nDevuelve SOLO este JSON:\n{\"sugerencias\":[\"sugerencia concreta de posible solución\"]}"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature'     => 0.6,
                ]);

            if (!$response->successful()) return null;
            return json_decode($response->json()['choices'][0]['message']['content'], true);
        });

        if (!$resultado || empty($resultado['sugerencias'])) {
            return response()->json(['error' => 'No se pudo generar la sugerencia. Inténtalo de nuevo.'], 502);
        }

        $orden   = (int) (EquipoTarea::where('equipo_id', $equipo->id)->max('orden') ?? -1);
        $creadas = collect($resultado['sugerencias'])->map(function ($descripcion) use ($equipo, &$orden) {
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
        if ($mr->limitaciones)  $contexto .= "Limitaciones: " . implode('; ', $mr->limitaciones) . "\n";

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

    // ── Banco de imágenes del proyecto (compartido con el docente, no privado del equipo) ──

    /**
     * POST /api/equipo/{token}/imagenes
     * El alumnado sube imágenes al mismo banco que gestiona el docente desde el panel
     * (microproyecto_recursos, tipo 'imagen') — no es una galería privada del equipo.
     */
    public function storeImagen(StoreImagenProyectoRequest $request, $token): JsonResponse
    {
        $equipo   = Equipo::with('microproyecto')->where('token', $token)->firstOrFail();
        $proyecto = $equipo->microproyecto;

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');
        $folder    = config('services.cloudinary.folder', 'dualab/recursos');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return response()->json(['error' => 'Cloudinary no configurado.'], 503);
        }

        $file = $request->file('file');
        $timestamp    = time();
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash('sha1', $paramsToSign . $apiSecret);

        $response = Http::attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error al subir la imagen a Cloudinary.'], 502);
        }

        $data = $response->json();

        $recurso = MicroproyectoRecurso::create([
            'microproyecto_id' => $proyecto->id,
            'tipo'             => 'imagen',
            'label'            => $request->input('label') ?: null,
            'filename'         => $file->getClientOriginalName(),
            'url'              => $data['secure_url'],
            'public_id'        => $data['public_id'],
            'resource_type'    => 'image',
            'mime'             => $file->getMimeType(),
            'size'             => $data['bytes'] ?? null,
        ]);

        if ($proyecto->imagen_portada_id === null) {
            $proyecto->update(['imagen_portada_id' => $recurso->id]);
        }

        return response()->json([
            'id'        => $recurso->id,
            'url'       => $recurso->url,
            'public_id' => $recurso->public_id,
            'filename'  => $recurso->filename,
            'label'     => $recurso->label ?? '',
            'size'      => $recurso->size,
        ], 201);
    }

    /**
     * DELETE /api/equipo/{token}/imagenes/{id}
     */
    public function destroyImagen($token, int $id): JsonResponse
    {
        $equipo  = Equipo::with('microproyecto')->where('token', $token)->firstOrFail();
        $recurso = MicroproyectoRecurso::where('id', $id)
            ->where('microproyecto_id', $equipo->microproyecto_id)
            ->where('tipo', 'imagen')
            ->firstOrFail();

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        $publicId = $recurso->public_id;
        $recurso->delete(); // portada se limpia sola (FK nullOnDelete) si era la elegida

        if ($cloudName && $apiKey && $apiSecret) {
            $timestamp    = time();
            $paramsToSign = "public_id={$publicId}&timestamp={$timestamp}";
            $signature    = hash('sha1', $paramsToSign . $apiSecret);

            Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * PUT /api/equipo/{token}/imagenes/{id}/portada
     */
    public function marcarPortadaImagen($token, int $id): JsonResponse
    {
        $equipo   = Equipo::with('microproyecto')->where('token', $token)->firstOrFail();
        $recurso  = MicroproyectoRecurso::where('id', $id)
            ->where('microproyecto_id', $equipo->microproyecto_id)
            ->where('tipo', 'imagen')
            ->firstOrFail();

        $equipo->microproyecto->update(['imagen_portada_id' => $recurso->id]);

        return response()->json(['ok' => true, 'imagen_portada_id' => $recurso->id]);
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
                'nombres_confirmados' => $equipo->nombres_confirmados,
                'ia_desbloqueada' => $equipo->ia_desbloqueada,
                'miembros'      => $equipo->miembros->map(fn($m) => [
                    'id'         => $m->id,
                    'nombre'     => $m->nombre,
                    'alias'      => $m->alias,
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
                'imagenes'          => $mp->recursos->where('tipo', 'imagen')->map(fn($r) => [
                    'id'    => $r->id,
                    'url'   => $r->url,
                    'label' => $r->label ?? '',
                ])->values(),
                'imagen_portada_id' => $mp->imagen_portada_id,
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
                'tipo'        => $t->tipo,
                'obligatoria' => $t->obligatoria,
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

    // Upsert, no destructivo: F0 ya se precarga con los miembros que el docente dio de alta
    // al crear el encuentro (ver EncuentroController::crearCodigo()). Un delete()+create()
    // completo aquí borraría ese reparto en cuanto el equipo guardara F0 la primera vez.
    //
    // Nombre bloqueado una vez el equipo pulsa "Confirmar nombres" en su F0 — a partir de
    // ahí ni el propio equipo ni el docente (ver EncuentroController) pueden cambiarlo.
    private function sincronizarMiembros(Equipo $equipo, array $miembros): void
    {
        $nombreBloqueado = $equipo->nombres_confirmados;
        $existentes  = $equipo->miembros()->pluck('id')->all();
        $conservados = [];

        foreach ($miembros as $posicion => $m) {
            if (empty($m['nombre'])) {
                continue;
            }

            $alias = trim((string) ($m['alias'] ?? ''));
            if ($alias === '') {
                $alias = AliasGenerator::generar($m['nombre'], $posicion);
            }

            $datos = [
                'nombre'        => $m['nombre'],
                'alias'         => mb_substr($alias, 0, 255),
                'rol'           => $m['rol'] ?? null,
                'fortalezas'    => $m['fortalezas'] ?? [],
                'puntos_mejora' => $m['puntos_mejora'] ?? [],
            ];

            if (!empty($m['id']) && in_array($m['id'], $existentes, true)) {
                if ($nombreBloqueado) {
                    unset($datos['nombre']);
                }
                $equipo->miembros()->whereKey($m['id'])->update($datos);
                $conservados[] = $m['id'];
            } else {
                $conservados[] = $equipo->miembros()->create($datos)->id;
            }
        }

        // Solo se borran los miembros que el equipo quitó explícitamente de su lista.
        $equipo->miembros()->whereNotIn('id', $conservados)->delete();
    }
}
