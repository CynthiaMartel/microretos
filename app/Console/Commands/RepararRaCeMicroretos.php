<?php

namespace App\Console\Commands;

use App\Models\Microreto;
use App\Models\Modulo;
use App\Services\RaCeCatalogoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Repara evaluacion_oficial de microretos generados ANTES del fix de la IA
 * (los que tienen ra/ce en texto libre sin ra_id/ce_ids reales). Estrategia
 * en 3 niveles, de más barato a más caro:
 *
 *   1. Match por similitud de texto contra el catálogo real del módulo (gratis).
 *   2. Si no hay match de texto suficiente, se le pide a la IA que seleccione
 *      ra_id/ce_ids de ese mismo módulo (closed-book, mismo enfoque que
 *      RaCeCatalogoService/sugerirRaCe — nunca redacta texto, solo elige ids).
 *   3. Si el módulo ni siquiera existe en el catálogo (aún no importado del
 *      BOE), se deja tal cual y se reporta para revisión manual.
 *
 * Por defecto es un dry-run: no persiste nada hasta pasar --commit.
 */
class RepararRaCeMicroretos extends Command
{
    protected $signature = 'microretos:reparar-ra-ce
                            {--commit : Guarda los cambios en BD. Sin esta opción es un dry-run.}
                            {--limit=0 : Máximo de microretos a procesar en esta ejecución (0 = sin límite).}
                            {--umbral=70 : Umbral mínimo de similitud de texto (0-100) para aceptar un match del Nivel 1.}';

    protected $description = 'Repara evaluacion_oficial de microretos antiguos enlazando ra_id/ce_ids reales, por similitud de texto o, en su defecto, con selección de IA sobre el catálogo cerrado del módulo.';

    /** @var array<int, Modulo> */
    private array $cacheModulos = [];

    /** @var array<int, array{status:int, intento:int}> respuestas no exitosas de la IA (para distinguir fallo de conexión de "no encontró nada") */
    private array $fallosIa = [];

    /**
     * Ciclos "hermanos" confirmados manualmente (ver análisis de 2026-07): microretos de
     * estos ciclos referencian de forma consistente el currículo de una familia
     * profesional muy cercana, no aleatoria. Es una lista blanca explícita y pequeña
     * — NUNCA una búsqueda global entre ciclos — precisamente para no enlazar
     * currículo de una familia profesional no relacionada.
     *
     *   129 Paisajismo y Medio Rural                              -> 83  Jardinería y Floristería
     *   160 Título Profesional Básico en Carpintería y Mueble      -> 102 Carpintería y Mueble
     *                                                               -> 54  Diseño y Amueblamiento
     *
     * Si el nombre del módulo coincide con más de un hermano a la vez, se considera
     * ambiguo y se descarta — mejor dejarlo pendiente que enlazar mal.
     */
    private const CICLOS_HERMANOS = [
        129 => [83],
        160 => [102, 54],
    ];

    public function handle(RaCeCatalogoService $raCeCatalogo): int
    {
        $commit = (bool) $this->option('commit');
        $limite = (int) $this->option('limit');
        $umbral = (int) $this->option('umbral');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se guardará nada. Relanza con --commit para persistir los cambios.');
        }

        $procesados         = 0;
        $resueltosPorTexto  = 0;
        $resueltosPorIa     = 0;
        $sinResolver        = [];

        Microreto::whereNotNull('evaluacion_oficial')
            ->where('es_simulado', false)
            ->whereNull('demo_id')
            ->orderBy('id')
            ->chunkById(50, function ($microretos) use (
                $raCeCatalogo, $commit, $umbral, $limite,
                &$procesados, &$resueltosPorTexto, &$resueltosPorIa, &$sinResolver
            ) {
                foreach ($microretos as $microreto) {
                    if ($limite > 0 && $procesados >= $limite) {
                        return false; // detiene el chunking, ya alcanzamos el límite
                    }

                    $evaluacion = $microreto->evaluacion_oficial ?? [];
                    if (!is_array($evaluacion) || !count($evaluacion)) continue;

                    // Idempotencia: si todas las entradas ya tienen ra_id, no hay nada que hacer
                    $pendientes = collect($evaluacion)->filter(fn ($e) => empty($e['ra_id'] ?? null));
                    if ($pendientes->isEmpty()) continue;

                    $procesados++;
                    $cambios = false;

                    foreach ($evaluacion as &$item) {
                        if (!empty($item['ra_id'] ?? null)) continue; // ya reparado

                        try {
                            $moduloId = $this->resolverModuloId($microreto->ciclo_id, $item['modulo'] ?? null);
                            if (!$moduloId) {
                                $sinResolver[] = [
                                    'microreto_id' => $microreto->id,
                                    'modulo'       => $item['modulo'] ?? '(sin nombre)',
                                    'motivo'       => 'módulo no encontrado en el catálogo',
                                ];
                                continue;
                            }

                            $modulo = $this->cargarModulo($moduloId);

                            $fallosAntes = count($this->fallosIa);
                            $match = $this->matchPorTexto($item, $modulo, $umbral)
                                ?? $this->pedirSeleccionIa($microreto, $modulo, $raCeCatalogo);

                            if (!$match) {
                                $motivo = count($this->fallosIa) > $fallosAntes
                                    ? 'fallo de conexión con la IA tras reintentos — reintentar más tarde'
                                    : 'sin match de texto ni de IA';
                                $sinResolver[] = [
                                    'microreto_id' => $microreto->id,
                                    'modulo'       => $item['modulo'] ?? '(sin nombre)',
                                    'motivo'       => $motivo,
                                ];
                                continue;
                            }

                            $fuePorTexto = isset($match['_via']) && $match['_via'] === 'texto';
                            unset($match['_via']);

                            $item = array_merge($match, ['aplicacion' => $item['aplicacion'] ?? '']);
                            $cambios = true;
                            $fuePorTexto ? $resueltosPorTexto++ : $resueltosPorIa++;
                        } catch (\Throwable $e) {
                            // Red de seguridad: cualquier error inesperado no debe tumbar todo el
                            // comando — se reporta y se sigue con la siguiente entrada.
                            $sinResolver[] = [
                                'microreto_id' => $microreto->id,
                                'modulo'       => $item['modulo'] ?? '(sin nombre)',
                                'motivo'       => 'error inesperado: ' . $e->getMessage(),
                            ];
                        }
                    }
                    unset($item);

                    if ($cambios && $commit) {
                        $microreto->evaluacion_oficial = $evaluacion;
                        $microreto->save();
                    }
                }
            });

        $this->newLine();
        $this->info("Microretos procesados: {$procesados}");
        $this->info("Entradas resueltas por texto (gratis): {$resueltosPorTexto}");
        $this->info("Entradas resueltas por IA: {$resueltosPorIa}");
        $this->warn('Entradas sin resolver: ' . count($sinResolver));

        if (count($sinResolver)) {
            $this->table(
                ['microreto_id', 'módulo', 'motivo'],
                collect($sinResolver)->map(fn ($s) => [$s['microreto_id'], $s['modulo'], $s['motivo']])
            );
        }

        if (!$commit) {
            $this->newLine();
            $this->comment('Esto fue un dry-run. Relanza con --commit para guardar los cambios.');
        }

        return self::SUCCESS;
    }

    private function resolverModuloId(?int $cicloId, ?string $nombreModulo): ?int
    {
        if (!$nombreModulo) return null;
        $nombre = trim($nombreModulo);

        $id = $this->buscarModuloEnCiclo($cicloId, $nombre);
        if ($id) return $id;

        // Fallback: probar los ciclos hermanos confirmados. Si el nombre coincide en
        // más de uno a la vez, es ambiguo (p.ej. módulos genéricos de Título Básico
        // que existen en decenas de ciclos) y se descarta en vez de arriesgar.
        $hermanos = self::CICLOS_HERMANOS[$cicloId] ?? [];
        if (empty($hermanos)) return null;

        $matches = collect($hermanos)
            ->map(fn ($cicloHermano) => $this->buscarModuloEnCiclo($cicloHermano, $nombre))
            ->filter()
            ->unique();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function buscarModuloEnCiclo(?int $cicloId, string $nombre): ?int
    {
        if (!$cicloId) return null;
        $query = Modulo::where('idcicloformativo', $cicloId);

        $id = (clone $query)->where('nombre', $nombre)->value('id');
        if ($id) return $id;

        // Tolerar el punto final típico de los nombres BOE, en cualquier dirección
        return (clone $query)
            ->where(function ($q) use ($nombre) {
                $q->where('nombre', rtrim($nombre, '.'))
                  ->orWhere('nombre', $nombre . '.');
            })
            ->value('id');
    }

    private function cargarModulo(int $moduloId): Modulo
    {
        if (!isset($this->cacheModulos[$moduloId])) {
            $this->cacheModulos[$moduloId] = Modulo::with(['ras.criteriosEvaluacion'])->findOrFail($moduloId);
        }
        return $this->cacheModulos[$moduloId];
    }

    /**
     * Nivel 1: intenta enlazar la entrada legacy al RA/CE real más parecido por
     * texto. Solo se acepta si tanto el RA como al menos un CE superan el umbral.
     */
    private function matchPorTexto(array $item, Modulo $modulo, int $umbral): ?array
    {
        $raTexto = $item['ra'] ?? '';
        if (!$raTexto) return null;

        $mejorRa = null;
        $mejorPorcentaje = 0;
        foreach ($modulo->ras as $ra) {
            similar_text(mb_strtolower($raTexto), mb_strtolower($ra->ra), $porcentaje);
            if ($porcentaje > $mejorPorcentaje) {
                $mejorPorcentaje = $porcentaje;
                $mejorRa = $ra;
            }
        }

        if (!$mejorRa || $mejorPorcentaje < $umbral) return null;

        $cesTexto = is_array($item['ce'] ?? null)
            ? $item['ce']
            : (isset($item['ce']) ? [$item['ce']] : []);

        $ceIds = [];
        foreach ($cesTexto as $ceTexto) {
            $mejorCe = null;
            $mejorCePorcentaje = 0;
            foreach ($mejorRa->criteriosEvaluacion as $ce) {
                similar_text(mb_strtolower((string) $ceTexto), mb_strtolower($ce->ce), $porcentajeCe);
                if ($porcentajeCe > $mejorCePorcentaje) {
                    $mejorCePorcentaje = $porcentajeCe;
                    $mejorCe = $ce;
                }
            }
            if ($mejorCe && $mejorCePorcentaje >= $umbral) $ceIds[] = $mejorCe->id;
        }

        if (empty($ceIds)) return null;

        $ceSeleccionados = $mejorRa->criteriosEvaluacion->whereIn('id', $ceIds)->values();

        return [
            'modulo'  => $modulo->nombre,
            'ra_id'   => $mejorRa->id,
            'ra'      => $mejorRa->ra,
            'ce_ids'  => $ceSeleccionados->pluck('id')->values()->all(),
            'ce'      => $ceSeleccionados->pluck('ce')->values()->all(),
            '_via'    => 'texto',
        ];
    }

    /**
     * Nivel 2: fallback con IA closed-book — mismo servicio y misma garantía de
     * no-alucinación que MicroretoIAController::generar() y sugerirRaCe().
     */
    private function pedirSeleccionIa(Microreto $microreto, Modulo $modulo, RaCeCatalogoService $raCeCatalogo): ?array
    {
        [$raIndex, $curriculumTexto, $hayCurriculum] = $raCeCatalogo->construirIndiceYTexto(collect([$modulo]));
        if (!$hayCurriculum) return null;

        $contexto = "Título: {$microreto->titulo}\n";
        if ($microreto->pregunta_reto) $contexto .= "Reto: {$microreto->pregunta_reto}\n";
        if (is_array($microreto->dificultades) && count($microreto->dificultades)) {
            $contexto .= 'Dificultades: ' . implode(', ', $microreto->dificultades) . "\n";
        }

        $systemPrompt = 'Eres un experto en currículum de Formación Profesional española. '
            . 'SELECCIONA únicamente ids de RA y CE que aparezcan literalmente en el currículo proporcionado '
            . '(marcados como [RA id=...] y [CE id=...]), los más relevantes para el microreto descrito. '
            . 'NUNCA inventes un id ni redactes tú el texto — el sistema recupera el texto real de la base de datos.';

        $userPrompt = "Microreto:\n{$contexto}\n{$curriculumTexto}\nDevuelve SOLO este JSON:\n{\"ra_id\": 123, \"ce_ids\": [45, 46]}";

        // Pequeño espaciado antes de cada llamada para no ráfagar el rate limit de OpenAI.
        usleep(300000);

        $intentosMax = 3;
        for ($intento = 1; $intento <= $intentosMax; $intento++) {
            try {
                $response = Http::withToken(config('services.openai.key'))
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
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Timeout / fallo de red — sin respuesta HTTP en absoluto. Reintentar con backoff.
                $this->fallosIa[] = ['status' => 'conexión', 'intento' => $intento];
                usleep(700000 * $intento);
                continue;
            }

            if ($response->successful()) {
                $data     = json_decode($response->json()['choices'][0]['message']['content'], true);
                $resuelto = $raCeCatalogo->resolver([$data], $raIndex);
                if (!empty($resuelto)) {
                    return $resuelto[0];
                }
                // Respuesta 200 pero sin selección válida (p.ej. "{}") — el modelo a veces
                // devuelve esto sin motivo aparente (no-determinismo); reintentar.
                usleep(400000 * $intento);
                continue;
            }

            // 429 (rate limit) o error de servidor: reintentar con backoff. El resto de
            // errores (p.ej. 401/400) no son recuperables reintentando.
            if ($response->status() === 429 || $response->serverError()) {
                $this->fallosIa[] = ['status' => $response->status(), 'intento' => $intento];
                usleep(700000 * $intento);
                continue;
            }

            $this->fallosIa[] = ['status' => $response->status(), 'intento' => $intento];
            break;
        }

        return null;
    }
}
