<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InvocaControladoresReales;
use App\Http\Controllers\EquipoPublicoController;
use App\Http\Requests\GuardarFaseEquipoRequest;
use App\Http\Requests\StoreEquipoPrototipoRequest;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\Microreto;
use App\Models\Microproyecto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Genera el ENTREGABLE FINAL (fase F3 "Entrega de la solución") de una selección
 * curada a mano de 15 equipos ya completados y demo (5 por familia: Informática y
 * Comunicaciones, Administración y Gestión, Comercio y Marketing), para que el
 * escaparate público ("Resolución del alumnado") tenga un adjunto real en vez del F3
 * vacío que deja demo:generar-encuentros-equipos (url_entregable = '').
 *
 * Selección y estilo de imagen decididos y validados con Cynthia antes de escribir
 * este comando — ver SELECCION[] más abajo. 9 de los 15 llevan además una imagen
 * mockup (captura de pantalla, prototipo de etiquetado, plano técnico o diagrama de
 * flujo — NUNCA foto realista de oficina/personas/espacios), los otros 6 se quedan
 * solo con el contenido de texto del PDF.
 *
 * Pipeline por equipo:
 *  1) Contexto real (reto + empresa + síntesis F1/propuesta F2 ya generadas por el
 *     propio equipo vía demo:generar-encuentros-equipos — se reutiliza, no se
 *     reinventa).
 *  2) OpenAI texto (gpt-4o, JSON) → documento ejecutivo + diagrama Mermaid (texto) +
 *     prototipo_visual (solo si esta entrada tiene categoría de imagen) + anexo de
 *     implementación.
 *  3) OpenAI imágenes (gpt-image-1, solo si toca) → PNG en base64, incrustado
 *     directamente en el PDF (no se sube aparte a Cloudinary como recurso propio).
 *  4) Render Blade → PDF (dompdf).
 *  5) Subida del PDF a Cloudinary reutilizando EquipoPublicoController::storePrototipo
 *     (el mismo endpoint que usa la subida real de alumnado) con un UploadedFile
 *     construido en memoria — no se duplica la lógica de firma/subida de Cloudinary.
 *  6) equipo_fases (F3).datos se actualiza con descripcion_entregable + url_entregable,
 *     reutilizando EquipoPublicoController::guardarFase.
 *
 * Dry-run por defecto (sin --commit no se llama a la IA ni se escribe nada, igual que
 * el resto de comandos demo:*). Idempotente: salta equipos que ya tienen
 * url_entregable salvo --force.
 */
class DemoGenerarEntregablesF3 extends Command
{
    use InvocaControladoresReales;

    protected $signature = 'demo:generar-entregables-f3
                            {--force : Regenera equipos que ya tienen url_entregable.}
                            {--limit=0 : Tope de equipos a procesar en esta ejecución (0 = los 15 seleccionados).}
                            {--equipos= : Lista de equipo_id separados por coma para procesar solo esos (subconjunto de SELECCION). Vacío = los 15.}
                            {--commit : Llama a la IA real (texto + imágenes) y persiste en BD/Cloudinary. Sin esta opción es un dry-run sin coste.}';

    protected $description = 'Genera el entregable final (PDF + imagen mockup opcional) de F3 para los 15 equipos curados del escaparate público.';

    private const MAX_INTENTOS_TEXTO = 3;
    private const ESPERA_TEXTO       = 15;

    // categoria: null (sin imagen) | 1 captura de pantalla (con 'interfaz' propia por
    // equipo, para que los 6 que la usan no salgan todos con la misma pinta de
    // "dashboard") | 2 prototipado de etiquetado | 3 prototipado de un plano |
    // 4 propuesta de flujo de trabajo.
    // esquema: 'mermaid' (diagrama en bloque de código) | 'pasos' (lista numerada) |
    // 'tabla' (fase/qué se hace/herramienta) — repartido a propósito para que los 15
    // PDFs no muestren siempre el mismo bloque de código Mermaid.
    // nivel: 'alto'|'medio'|'bajo' — nivel de logro del equipo, solo afecta al tono/
    // profundidad del prompt; no existe columna en BD para esto, es puramente narrativo.
    private const SELECCION = [
        // Informática y Comunicaciones
        98  => ['categoria' => 1, 'interfaz' => 'un repositorio de documentos en el navegador: lista de carpetas y archivos con etiquetas de estado', 'esquema' => 'mermaid', 'nivel' => 'alto'],   // #116 Unificación de Documentación de Proyectos
        122 => ['categoria' => 1, 'interfaz' => 'un tablero Kanban de tareas con columnas Por hacer / En curso / Hecho',                            'esquema' => 'pasos',   'nivel' => 'medio'],  // #165 Optimización de Gestión de Proyectos en TecnoSoluciones
        139 => ['categoria' => 1, 'interfaz' => 'una bandeja de mensajes de un chat de equipo tipo intranet',                                       'esquema' => 'tabla',   'nivel' => 'bajo'],   // #250 Desarrollo de Herramientas de Comunicación Interna
        134 => ['categoria' => null, 'esquema' => 'mermaid', 'nivel' => 'medio'],  // #242 Automatización de Documentación de Proyectos
        152 => ['categoria' => null, 'esquema' => 'pasos',   'nivel' => 'alto'],   // #270 Implementación de Seguridad en la Gestión de Proyectos
        // Administración y Gestión
        159 => ['categoria' => 2, 'esquema' => 'tabla',   'nivel' => 'alto'],   // #279 Etiquetado Inteligente
        176 => ['categoria' => 1, 'interfaz' => 'un panel de control con tarjetas de aplicaciones/herramientas ya conectadas entre sí',             'esquema' => 'mermaid', 'nivel' => 'medio'],  // #300 Integración de Herramientas de Gestión de Proyectos
        175 => ['categoria' => 4, 'esquema' => 'pasos',   'nivel' => 'bajo'],   // #299 Optimización del Proceso de Gestión Documental
        124 => ['categoria' => null, 'esquema' => 'tabla',   'nivel' => 'medio'],  // #182 Estrategia de comunicación interna en la gestión documental
        167 => ['categoria' => null, 'esquema' => 'mermaid', 'nivel' => 'alto'],   // #289 Automatización de Documentación Jurídica
        // Comercio y Marketing
        168 => ['categoria' => 1, 'interfaz' => 'un calendario de publicaciones programadas en redes sociales',                                    'esquema' => 'pasos',   'nivel' => 'alto'],   // #290 Automatización de Campañas en MarketExito
        118 => ['categoria' => 3, 'esquema' => 'tabla',   'nivel' => 'medio'],  // #162 Diseño de Espacios Comerciales Eficientes
        192 => ['categoria' => 1, 'interfaz' => 'un panel de indicadores (KPIs) con gráficas de rendimiento de campañas',                          'esquema' => 'mermaid', 'nivel' => 'bajo'],   // #322 Integración de Herramientas Analíticas
        188 => ['categoria' => null, 'esquema' => 'pasos',   'nivel' => 'medio'],  // #320 Informe de Rendimiento de Campañas
        174 => ['categoria' => null, 'esquema' => 'tabla',   'nivel' => 'alto'],   // #293 Mejora de la Comunicación Interna en MarketExito
    ];

    // Instrucción común a las 4 categorías: nunca fotorrealista, nunca personas/oficinas
    // reales, y — a propósito, por feedback de que salía "genérico en inglés" — SIEMPRE
    // con los textos/etiquetas de la interfaz en ESPAÑOL y con contenido de ejemplo
    // creíble para el proyecto concreto (nunca "Lorem ipsum" ni palabras sueltas en
    // inglés), y con estética de mockup hecho por estudiantes (tipo Figma/Canva
    // sencillo), no de producto SaaS profesional pulido — así varía más entre entregas
    // y se nota menos que sale siempre de la misma plantilla.
    private const REGLA_IMAGEN_COMUN = 'Todos los textos, etiquetas y contenido de ejemplo visibles en la imagen '
        . 'DEBEN estar en ESPAÑOL y ser creíbles para este proyecto y empresa concretos (nunca "Lorem ipsum" ni '
        . 'palabras sueltas en inglés). Estética de mockup sencillo hecho por un equipo de estudiantes (tipo '
        . 'Figma/Canva), no de producto SaaS corporativo pulido — con pequeñas imperfecciones o asimetrías que lo '
        . 'hagan sentir hecho a mano, no generado en serie.';

    private const ESTILOS_IMAGEN = [
        1 => "CAPTURA DE PANTALLA: 'Clean UI mockup screenshot in a web browser of {INTERFAZ}, [elementos concretos del proyecto], flat design --ar 16:9'.",
        2 => "PROTOTIPADO DE ETIQUETADO (sistema físico de etiquetas/señalética): 'Flat design mockup sheet of a label/sticker system for [qué se etiqueta], 3-4 label variants with color-coding and icons, clean product design presentation, no photo, no people --ar 16:9'.",
        3 => "PROTOTIPADO DE UN PLANO (distribución de un espacio): 'Clean 2D architectural blueprint / floor plan diagram of [espacio], schematic top-down technical drawing style, no 3D render, no photorealism, no people --ar 16:9'.",
        4 => "PROPUESTA DE FLUJO DE TRABAJO (proceso/checklist): 'Clean flowchart diagram mockup showing workflow steps for [proceso], flat infographic style, boxes and arrows, no people, no photo --ar 16:9'.",
    ];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $forzar = (bool) $this->option('force');
        $limite = (int) $this->option('limit');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se llama a la IA ni se escribe nada. Relanza con --commit para generar de verdad.');
        }

        $equipoIds = array_keys(self::SELECCION);

        $filtroEquipos = $this->option('equipos');
        if ($filtroEquipos) {
            $solicitados = array_map('intval', explode(',', $filtroEquipos));
            $equipoIds   = array_values(array_intersect($equipoIds, $solicitados));
        }

        if ($limite > 0) {
            $equipoIds = array_slice($equipoIds, 0, $limite);
        }

        $equipos = Equipo::whereIn('id', $equipoIds)
            ->with(['microproyecto.microreto', 'microproyecto.empresa', 'microproyecto.familia', 'fases', 'prototipos'])
            ->get()
            ->keyBy('id');

        $procesados = 0;
        $conImagen  = 0;
        $pineables  = []; // equipoId de los que quedan con entregable válido al final (nuevos o ya existentes)

        foreach ($equipoIds as $equipoId) {
            $equipo = $equipos->get($equipoId);
            $config = self::SELECCION[$equipoId];

            if (!$equipo) {
                $this->error("  ✗ Equipo #{$equipoId} no encontrado — omitido (¿ha cambiado la BD desde la selección?).");
                continue;
            }

            $proyecto  = $equipo->microproyecto;
            $microreto = $proyecto?->microreto;
            $empresa   = $proyecto?->empresa;
            $f3        = $equipo->fases->firstWhere('numero_fase', 3);
            $yaTiene   = trim($f3->datos['url_entregable'] ?? '') !== '';

            $this->line("Equipo #{$equipo->id} \"{$proyecto?->titulo}\" | imagen: " . ($config['categoria'] ? "categoría {$config['categoria']}" : 'ninguna') . " | nivel: {$config['nivel']}" . ($yaTiene ? ' | YA TIENE ENTREGABLE' : ''));

            if ($yaTiene && !$forzar) {
                $this->comment('  … ya tiene entregable, se omite (usa --force para regenerar).');
                $pineables[] = $equipoId;
                continue;
            }

            if (!$proyecto || !$microreto || !$empresa || !$f3) {
                $this->error('  ✗ Faltan datos de proyecto/reto/empresa/fase F3 — omitido.');
                continue;
            }

            if (!$commit) {
                $procesados++;
                if ($config['categoria']) {
                    $conImagen++;
                }
                continue;
            }

            try {
                $this->generarEntregable($equipo, $proyecto, $microreto, $empresa, $f3, $config);
                $procesados++;
                $pineables[] = $equipoId;
                if ($config['categoria']) {
                    $conImagen++;
                }
                $this->info('  ✓ Entregable generado y adjuntado.');
            } catch (\Throwable $e) {
                $this->error("  ✗ Error generando entregable del equipo #{$equipo->id}: " . $e->getMessage());
            }
        }

        if ($commit && !empty($pineables)) {
            $this->pinearOrden($equipos, $pineables);
            $this->comment('  … proyectos reordenados al principio del filtro "completados" (backoffice y escaparate público).');
        }

        $this->newLine();
        if ($commit) {
            $this->info("Entregables generados en esta ejecución: {$procesados} (con imagen: {$conImagen}).");
        } else {
            $this->comment("Dry-run: {$procesados} equipos procesarían entregable (con imagen: {$conImagen}). Relanza con --commit para generar de verdad.");
        }

        return self::SUCCESS;
    }

    /**
     * Tanto el listado del backoffice (MicroproyectoController::index) como el
     * escaparate público (PublicMicroproyectoCatalogoController::index) ordenan por
     * `updated_at desc` — pedido explícito de Cynthia: que estos 15 proyectos queden
     * excepcionalmente los primeros en el filtro "completados" para localizarlos fácil
     * en una demo. Se reaprovecha ese mismo orden en vez de añadir una columna nueva
     * (menos invasivo). Orden determinista: el primero de SELECCION (posición 0) queda
     * el "más reciente" de los 15, el último el "menos reciente" — pero todos por
     * delante de cualquier proyecto real que no se toque aquí.
     *
     * No es un anclaje permanente: si en el futuro se completa un proyecto real más
     * tarde que esto, o se relanza este comando, el orden se puede recalcular sin más
     * (solo toca `updated_at`, no hay flag de "destacado" en BD).
     */
    private function pinearOrden($equipos, array $equipoIds): void
    {
        $vistos = [];
        foreach (array_values($equipoIds) as $posicion => $equipoId) {
            $proyecto = $equipos->get($equipoId)?->microproyecto;
            if (!$proyecto || isset($vistos[$proyecto->id])) {
                continue;
            }
            $vistos[$proyecto->id] = true;

            // OJO: ->update(['updated_at' => ...]) NO funciona aquí — 'updated_at' no
            // está en $fillable de Microproyecto, fill() lo descarta en silencio y el
            // auto-touch de Eloquent respeta el valor "no tocado" (no hay isDirty).
            // Asignación directa del atributo + save() sí lo persiste de verdad.
            $proyecto->updated_at = now()->subSeconds($posicion);
            $proyecto->save();
        }
    }

    private function generarEntregable(Equipo $equipo, Microproyecto $proyecto, Microreto $microreto, Empresa $empresa, $f3, array $config): void
    {
        $familiaNombre = $proyecto->familia?->nombre ?? 'Formación Profesional';
        $sintesisF1    = $equipo->fases->firstWhere('numero_fase', 1)?->datos ?? [];
        $propuestaF2   = $equipo->fases->firstWhere('numero_fase', 2)?->datos ?? [];

        $contenido = $this->llamarOpenAiTexto($familiaNombre, $config, $microreto, $empresa, $sintesisF1, $propuestaF2);

        $imagenBase64 = null;
        $imagenTitulo = null;
        if ($config['categoria'] && !empty($contenido['prototipo_visual']['prompt_imagen_en'])) {
            $imagenTitulo = $contenido['prototipo_visual']['titulo'] ?? null;
            $imagenBase64 = $this->llamarOpenAiImagen($contenido['prototipo_visual']['prompt_imagen_en']);
        }

        $doc    = $contenido['documento_ejecutivo'] ?? [];
        $titulo = $doc['titulo'] ?? $proyecto->titulo;

        $pdf = Pdf::loadView('pdf.entregable-f3', [
            'titulo'           => $titulo,
            'introduccion'     => $doc['introduccion'] ?? '',
            'indice'           => $doc['indice'] ?? [],
            'ejemploTitulo'    => $doc['ejemplo_solucion']['titulo'] ?? null,
            'ejemploContenido' => $doc['ejemplo_solucion']['contenido'] ?? null,
            'esquema'          => $contenido['esquema_proceso'] ?? null,
            'imagenBase64'     => $imagenBase64,
            'imagenTitulo'     => $imagenTitulo,
            'pasos'            => $contenido['anexo_implementacion']['pasos'] ?? [],
            'herramientas'     => $contenido['anexo_implementacion']['herramientas'] ?? [],
            'meta'             => [
                'equipo'  => $equipo->nombre,
                'empresa' => $empresa->nombre_comercial,
                'ciclo'   => $familiaNombre,
                'fecha'   => now()->format('d/m/Y'),
            ],
        ])->setPaper('a4');

        $pdfBinario = $pdf->output();
        $rutaTmp    = tempnam(sys_get_temp_dir(), 'entregable_') . '.pdf';
        file_put_contents($rutaTmp, $pdfBinario);

        // Al regenerar (--force) el equipo puede ya tener un entregable anterior
        // (contexto='entregable') colgado en equipo_prototipos — se borra ANTES de
        // subir el nuevo, reutilizando el endpoint real de borrado (Cloudinary + BD),
        // para no dejar adjuntos duplicados/obsoletos que la ficha también listaría.
        foreach ($equipo->prototipos->where('contexto', 'entregable') as $antiguo) {
            app(EquipoPublicoController::class)->destroyPrototipo($equipo->token, $antiguo->id);
        }

        try {
            $nombreArchivo = Str::slug($titulo) . '.pdf';
            $file          = new UploadedFile($rutaTmp, $nombreArchivo, 'application/pdf', null, true);

            $reqPrototipo = StoreEquipoPrototipoRequest::create('/', 'POST', [
                'label'    => Str::limit($titulo, 190, ''),
                'contexto' => 'entregable',
            ], [], ['file' => $file]);
            $reqPrototipo->setContainer(app());
            $reqPrototipo->setRedirector(app('redirect'));
            $reqPrototipo->validateResolved();

            $respuesta = app(EquipoPublicoController::class)->storePrototipo($reqPrototipo, $equipo->token);
            $data      = json_decode($respuesta->getContent(), true);

            if (!isset($data['url'])) {
                throw new \RuntimeException('Cloudinary no devolvió URL (¿configurado en .env?): ' . json_encode($data));
            }
        } finally {
            @unlink($rutaTmp);
        }

        // guardarFase() reemplaza el array `datos` entero (no hace merge), así que hay
        // que partir del actual y solo pisar descripcion_entregable/url_entregable.
        $datosF3 = array_merge($f3->datos ?? [], [
            'descripcion_entregable' => $doc['introduccion'] ?? ($f3->datos['descripcion_entregable'] ?? ''),
            'url_entregable'         => $data['url'],
        ]);

        $reqFase = $this->peticion(GuardarFaseEquipoRequest::class, ['datos' => $datosF3]);
        app(EquipoPublicoController::class)->guardarFase($reqFase, $equipo->token, 3);
    }

    private function llamarOpenAiTexto(string $familia, array $config, Microreto $microreto, Empresa $empresa, array $sintesisF1, array $propuestaF2): array
    {
        $nivel      = $config['nivel'];
        $categoria  = $config['categoria'];
        $interfaz   = $config['interfaz'] ?? null;
        $esquemaTipo = $config['esquema'];

        $nivelDescripcion = [
            'bajo'  => 'trabajo correcto pero sencillo, limitado por el poco tiempo disponible; se nota que han priorizado lo esencial y dejado detalles sin pulir.',
            'medio' => 'trabajo sólido, bien ejecutado, cumple lo pedido con buen criterio.',
            'alto'  => 'trabajo excelente, va más allá de lo pedido, con detalles que muestran iniciativa propia del equipo.',
        ][$nivel];

        $sintesisTexto = collect($sintesisF1['sintesis'] ?? [])
            ->map(fn ($s) => ($s['pregunta'] ?? '') . ': ' . ($s['respuesta'] ?? ''))->implode("\n");
        $propuestaTexto = trim(($propuestaF2['propuesta'] ?? '') . ' ' . ($propuestaF2['solucion_final'] ?? ''));

        $dificultades = collect($microreto->dificultades ?? [])->implode('; ');
        $limitaciones = collect($microreto->limitaciones ?? [])->implode('; ');
        $queNecesitan = collect($microreto->que_necesitan ?? [])->implode('; ');
        $prototipos   = collect($microreto->prototipos ?? [])->implode('; ');

        $systemPrompt = "Eres un equipo de 3-5 estudiantes de Formación Profesional de grado superior en España "
            . "({$familia}), entregando el ENTREGABLE FINAL de un reto real propuesto por una empresa colaboradora, "
            . "que ya habéis investigado, resuelto y vais a presentar. Escribes en primera persona de equipo "
            . "(\"hemos diseñado...\", \"nuestra propuesta...\"), con naturalidad de estudiante, nunca como consultor "
            . "externo.\n\nNivel de logro de este equipo: {$nivel} — {$nivelDescripcion}\n\n"
            . "Te basas ÚNICAMENTE en el contexto real que se te da del reto, la empresa y la propia resolución del "
            . "equipo — nunca inventas datos que lo contradigan. NUNCA mencionas que la información es ficticia, de "
            . "ejemplo, de prueba o generada automáticamente: para ti este proyecto es completamente real. Devuelves "
            . "ÚNICAMENTE JSON válido, sin texto fuera del JSON.";

        $prototipoInstruccion = '';
        if ($categoria) {
            $estilo = str_replace('{INTERFAZ}', $interfaz ?? 'una interfaz digital relevante para el proyecto', self::ESTILOS_IMAGEN[$categoria]);
            $prototipoInstruccion = "\"prototipo_visual\": {\"titulo\": \"string\", \"prompt_imagen_en\": \"descripción en "
                . 'inglés siguiendo EXACTAMENTE este estilo — ' . $estilo . ' ' . self::REGLA_IMAGEN_COMUN . "\"},\n  ";
        }

        $esquemaInstruccion = match ($esquemaTipo) {
            'pasos' => '"esquema_proceso": {"tipo": "pasos", "contenido": ["string: un paso del proceso con su breve '
                . 'descripción", "..."]} — entre 4 y 6 pasos, en el orden en que ocurren.',
            'tabla' => '"esquema_proceso": {"tipo": "tabla", "contenido": [{"fase": "string", "que_se_hace": '
                . '"string", "herramienta": "string: herramienta o responsable"}, "..."]} — entre 4 y 6 filas.',
            default => '"esquema_proceso": {"tipo": "mermaid", "contenido": "sintaxis Mermaid.js completa y válida '
                . '(flowchart o gantt, la que mejor encaje con el proceso concreto)"}',
        };

        $userPrompt = "CONTEXTO DEL RETO Y LA EMPRESA:\n"
            . "Empresa: {$empresa->nombre_comercial} ({$empresa->sector} — {$empresa->actividad})\n"
            . "Pregunta del reto: {$microreto->pregunta_reto}\n"
            . "Quién es la empresa / su día a día: {$microreto->quien_es} {$microreto->dia_a_dia}\n"
            . "Dificultades que afrontaba: {$dificultades}\n"
            . "Limitaciones reales de la empresa: {$limitaciones}\n"
            . "Qué necesitaban: {$queNecesitan}\n"
            . "Entregable/prototipo pedido originalmente: {$prototipos}\n\n"
            . "RESOLUCIÓN YA TRABAJADA POR EL EQUIPO (básate en esto, no lo contradigas):\n"
            . "{$sintesisTexto}\n{$propuestaTexto}\n\n"
            . "Genera el ENTREGABLE FINAL en este JSON exacto:\n"
            . "{\n"
            . "  \"documento_ejecutivo\": {\n"
            . "    \"titulo\": \"string\",\n"
            . "    \"introduccion\": \"3-5 frases dirigidas a la empresa, tono profesional-estudiante, mencionando "
            . "cómo habéis adaptado la solución a sus limitaciones reales\",\n"
            . "    \"indice\": [\"string\", \"...\"],\n"
            . "    \"ejemplo_solucion\": {\"titulo\": \"string\", \"contenido\": \"el ejemplo real y completo, "
            . "redactado, no un resumen (plantilla/copy/fragmento de política según la familia). TEXTO PLANO: nunca "
            . "sintaxis Markdown (nada de ##, **, -, numeración con puntos); si necesitas apartados o campos, sepáralos "
            . "con saltos de línea y mayúsculas o dos puntos, como si fuera un documento de Word ya maquetado.\"}\n"
            . "  },\n"
            . "  {$esquemaInstruccion},\n"
            . "  {$prototipoInstruccion}"
            . "  \"anexo_implementacion\": {\n"
            . "    \"pasos\": [\"string, checklist accionable, 5-8 pasos\"],\n"
            . "    \"herramientas\": [\"string: nombre herramienta gratuita/low-cost — para qué la usan\"]\n"
            . "  }\n"
            . "}";

        for ($intento = 1; $intento <= self::MAX_INTENTOS_TEXTO; $intento++) {
            try {
                $response = Http::withToken(config('services.openai.key'))
                    ->timeout(90)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'           => 'gpt-4o',
                        'messages'        => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature'     => 0.8,
                    ]);

                if ($response->successful()) {
                    $data = json_decode($response->json('choices.0.message.content'), true);
                    if (is_array($data) && isset($data['documento_ejecutivo'])) {
                        return $data;
                    }
                }
            } catch (\Throwable $e) {
                // sigue al reintento
            }

            if ($intento < self::MAX_INTENTOS_TEXTO) {
                sleep(self::ESPERA_TEXTO);
            }
        }

        throw new \RuntimeException('No se pudo generar el contenido del entregable con IA tras ' . self::MAX_INTENTOS_TEXTO . ' intentos.');
    }

    private function llamarOpenAiImagen(string $prompt): ?string
    {
        for ($intento = 1; $intento <= 2; $intento++) {
            try {
                $response = Http::withToken(config('services.openai.key'))
                    ->timeout(120)
                    ->post('https://api.openai.com/v1/images/generations', [
                        'model'  => 'gpt-image-1',
                        'prompt' => $prompt,
                        'size'   => '1536x1024',
                        'n'      => 1,
                    ]);

                if ($response->successful()) {
                    $b64 = $response->json('data.0.b64_json');
                    if ($b64) {
                        return $b64;
                    }
                }
            } catch (\Throwable $e) {
                // sigue al reintento
            }

            if ($intento < 2) {
                sleep(10);
            }
        }

        return null; // sin imagen no se aborta el entregable entero: se genera solo con texto
    }
}
