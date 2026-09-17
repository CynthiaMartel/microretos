<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InvocaControladoresReales;
use App\Http\Controllers\MicroretoIAController;
use App\Http\Requests\GenerarMicroretoRequest;
use App\Models\CicloFormativo;
use App\Models\Empresa;
use App\Models\Microreto;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Genera microretos (biblioteca) para cada empresa ficticia simulada, reutilizando
 * MicroretoIAController::generar() — el mismo endpoint del Generador de Retos real,
 * con cobertura de currículo RA/CE real (nunca ids inventados, ver RaCeCatalogoService).
 *
 * Idempotente: una empresa que ya tenga retos simulados se salta salvo --force (que
 * AÑADE más retos, nunca borra ni regenera los existentes).
 *
 * Dry-run por defecto: sin --commit no se llama a la IA ni se escribe nada, solo se
 * imprime el plan (ciclo/curso/nivel elegidos y nº de retos) por empresa.
 */
class DemoGenerarRetos extends Command
{
    use InvocaControladoresReales;

    protected $signature = 'demo:generar-retos
                            {--min=6 : Mínimo de retos a generar por empresa.}
                            {--max=7 : Máximo de retos a generar por empresa.}
                            {--limit=0 : Tope de EMPRESAS a procesar en esta ejecución (0 = todas las pendientes).}
                            {--force : Vuelve a procesar empresas que ya tienen retos simulados (añade más, no borra los existentes).}
                            {--completar-transversales : Modo de relleno: solo genera el grupo \'ambos_cursos\' para empresas que ya tienen retos pero les falta el transversal (tras un lote normal con fallos). Ignora --min/--max/--force.}
                            {--transversales=2 : Nº de retos transversales a generar por empresa en modo --completar-transversales.}
                            {--todo-transversal : Genera TODOS los retos de cada empresa como transversales (ambos_cursos) en vez del reparto habitual 1º/2º/transversal. Usa el mismo fallback a ciclos hermanos que --completar-transversales.}
                            {--commit : Llama a la IA real y persiste en BD. Sin esta opción es un dry-run sin coste.}';

    protected $description = 'Genera microretos (biblioteca) para las empresas ficticias simuladas, con currículo RA/CE real.';

    // Ciclo "flagship" por familia, para elegir un currículo real y con buena cobertura
    // RA/CE sin depender del centro de la empresa (ver investigación en el informe).
    // Se excluyen deliberadamente, para cada familia:
    //   - Las versiones "A Distancia": duplican el currículo del ciclo presencial, no
    //     aportan variedad real a la biblioteca.
    //   - Los "Curso de especialización": currículo muy reducido (4-6 módulos), pensado
    //     para formación de posgrado corta, no representativo de un ciclo estándar.
    //   - Los programas auxiliares IFE+16/ADG/IFC+21 (FP Básica muy acotada, 3-4 módulos).
    //   - Ciclos con cobertura RA/CE parcial (p.ej. "Servicios Comerciales", 10/12 módulos).
    // Los ids vienen de una consulta de solo lectura a CicloFormativo/Modulo (ver informe).
    private const CICLOS_POR_FAMILIA = [
        3 => [18, 22, 81, 152],      // Admón. y Gestión: Admón. y Finanzas, Asistencia a Dirección, Gestión Administrativa, Servicios Administrativos
        5 => [28, 29, 88, 130, 132], // Comercio y Marketing: Comercio Internacional, Gestión Ventas y Esp. Comerciales, Act. Comerciales, Marketing y Publicidad, Transporte y Logística
        1 => [1, 2, 15, 51],         // Informática y Comunicaciones: ASIR, SMR, DAW, DAM
    ];

    private const CENTRO_ID = 10; // DuaLab — único centro con empresas generadas por demo:generar-empresas

    private const NIVELES     = ['Bajo', 'Medio', 'Alto'];
    private const DURACIONES  = ['1 a 2 semanas', '2 a 3 semanas', '3 a 4 semanas'];

    // GenerarMicroretoRequest limita 'cantidad' a máx. 5 por llamada — se piden en
    // lotes para minimizar nº de llamadas a OpenAI (coste + riesgo de rate-limit).
    private const CANTIDAD_MAX_POR_LLAMADA = 5;

    // El transversal (ambos_cursos) es el prompt más pesado (currículo de los dos cursos
    // a la vez) y el que más se acerca al límite de tokens/minuto de OpenAI — pedido
    // explícito del usuario ("necesito los transversales, son los más importantes"): más
    // intentos y esperas más largas que un simple rate-limit puntual, tanto si la IA
    // responde con error como si la llamada revienta por timeout/conexión (ambos casos se
    // tratan igual, ver llamarConReintento()).
    private const MAX_INTENTOS_IA        = 5;
    private const ESPERA_BASE_SEGUNDOS   = 30;

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $min    = max(1, (int) $this->option('min'));
        $max    = max($min, (int) $this->option('max'));
        $limite = (int) $this->option('limit');
        $forzar = (bool) $this->option('force');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se llama a la IA ni se escribe nada. Relanza con --commit para generar de verdad.');
        }

        // generar() comprueba perteneceAlCentroDe($user) para el rol del usuario que
        // invoca — se actúa siempre como la cuenta DuaLab (id=48, admin de su centro),
        // igual que el resto de comandos demo.
        $usuario = User::find(48);
        if (!$usuario) {
            $this->error('No existe el usuario id=48 (DuaLab). Abortando.');
            return self::FAILURE;
        }
        $this->actuarComo($usuario);

        if ($this->option('completar-transversales')) {
            return $this->completarTransversales($usuario, $commit, $limite);
        }

        // Scoped a centro_id (DuaLab): 'es_simulada' es un flag genérico que ya usan otras
        // empresas de prueba ajenas a este flujo (de otros centros, creadas a mano) —
        // sin este filtro se les generarían retos que no corresponden a este comando.
        $empresasQuery = Empresa::where('es_simulada', true)
            ->where('centro_id', self::CENTRO_ID)
            ->with('familias')->orderBy('id');
        if (!$forzar) {
            $empresasQuery->whereDoesntHave('microretos', fn ($q) => $q->where('es_simulado', true));
        }
        if ($limite > 0) {
            $empresasQuery->limit($limite);
        }
        $empresas = $empresasQuery->get();

        if ($empresas->isEmpty()) {
            $this->info('No hay empresas simuladas pendientes de generar retos (usa --force para reprocesar todas).');
            return self::SUCCESS;
        }

        $todoTransversal = (bool) $this->option('todo-transversal');
        $totalGenerados  = 0;

        foreach ($empresas as $empresa) {
            [$familia, $ciclo] = $this->resolverFamiliaYCiclo($empresa);
            if (!$familia || !$ciclo) {
                continue;
            }

            $cantidadTotal = random_int($min, $max);
            $nivelGrupo    = self::NIVELES[$empresa->id % count(self::NIVELES)];
            $duracion      = self::DURACIONES[$empresa->id % count(self::DURACIONES)];

            // Array de consecuencias reconstruido a partir del texto plano guardado en la
            // empresa (generar() lo usa tal cual, ver MicroretoIAController::generar()).
            $consecuenciasArray = array_values(array_filter([$empresa->consecuencias]));

            if ($todoTransversal) {
                $this->line("Empresa #{$empresa->id} {$empresa->nombre_comercial} | familia {$familia->nombre} | nivel {$nivelGrupo} | {$cantidadTotal} retos a generar (todos transversales, ciclo preferente: {$ciclo->id})");

                if (!$commit) {
                    continue;
                }

                $ciclosCandidatos = self::CICLOS_POR_FAMILIA[$familia->id] ?? [];
                $generadosEmpresa = $this->generarTransversalesConFallback(
                    $empresa, $familia, $ciclosCandidatos, $ciclo->id, $cantidadTotal,
                    $nivelGrupo, $duracion, $usuario, $consecuenciasArray
                );
                $totalGenerados += $generadosEmpresa;

                $this->line("  → {$generadosEmpresa}/{$cantidadTotal} retos transversales generados para {$empresa->nombre_comercial}.");
                continue;
            }

            $cursosDisponibles = Modulo::where('idcicloformativo', $ciclo->id)
                ->distinct()->pluck('curso')->filter()->values()->all();
            $distribucion = $this->distribuirCursos($cursosDisponibles, $cantidadTotal);

            $resumenDistribucion = collect($distribucion)
                ->map(fn ($cantidad, $curso) => ($curso === 'ambos_cursos' ? 'Transversal' : "{$curso}º") . "={$cantidad}")
                ->implode(', ');

            $this->line("Empresa #{$empresa->id} {$empresa->nombre_comercial} | familia {$familia->nombre} | ciclo {$ciclo->id} {$ciclo->nombre} | nivel {$nivelGrupo} | {$cantidadTotal} retos a generar ({$resumenDistribucion})");

            if (!$commit) {
                continue;
            }

            $generadosEmpresa = 0;
            foreach ($distribucion as $cursoSeleccionado => $cantidadCurso) {
                if ($cantidadCurso <= 0) {
                    continue;
                }
                $generados = $this->generarLoteParaCurso(
                    $empresa, $familia, $ciclo, $cursoSeleccionado, $cantidadCurso,
                    $nivelGrupo, $duracion, $usuario, $consecuenciasArray
                );
                $generadosEmpresa += $generados;
                $totalGenerados   += $generados;
            }

            $this->line("  → {$generadosEmpresa}/{$cantidadTotal} retos generados para {$empresa->nombre_comercial}.");
        }

        $this->newLine();
        if ($commit) {
            $this->info("Total de retos generados en esta ejecución: {$totalGenerados}.");
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para generar de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * Intenta generar $cantidad retos transversales para una empresa, probando primero su
     * ciclo preferente y, si no cabe en el límite de tokens/min de OpenAI (confirmado: no
     * es cuestión de reintentar, el currículo de ESE ciclo en concreto ya supera el límite
     * él solo), los demás ciclos "hermanos" de la misma familia — pedido explícito del
     * usuario, prioriza conseguir el transversal sobre mantener siempre el mismo ciclo.
     * Compartido entre completarTransversales() y el modo --todo-transversal.
     *
     * @param array<int> $ciclosCandidatos todos los ciclos de la familia (CICLOS_POR_FAMILIA).
     */
    private function generarTransversalesConFallback(
        Empresa $empresa,
        \App\Models\Familia $familia,
        array $ciclosCandidatos,
        int $cicloPreferenteId,
        int $cantidad,
        string $nivelGrupo,
        string $duracion,
        User $usuario,
        array $consecuenciasArray
    ): int {
        $ordenIntentos = array_unique([$cicloPreferenteId, ...$ciclosCandidatos]);
        $generados     = 0;

        foreach ($ordenIntentos as $posicion => $cicloId) {
            $ciclo = CicloFormativo::find($cicloId);
            if (!$ciclo) {
                continue;
            }

            $cursosDisponibles = Modulo::where('idcicloformativo', $ciclo->id)
                ->distinct()->pluck('curso')->filter()->values()->all();
            if (!in_array(1, $cursosDisponibles, true) || !in_array(2, $cursosDisponibles, true)) {
                continue; // este ciclo no tiene ambos cursos con currículo, ni lo intentamos
            }

            // Exploración con solo 2 intentos por ciclo candidato (un "Request too large"
            // es determinista, no hace falta agotar los 5 intentos para descartarlo) — el
            // ciclo que finalmente funcione ya se queda con ese resultado.
            $esUltimoCandidato = $posicion === array_key_last($ordenIntentos);
            $maxIntentos       = $esUltimoCandidato ? self::MAX_INTENTOS_IA : 2;

            if ($ciclo->id !== $cicloPreferenteId) {
                $this->warn("  … ciclo {$cicloPreferenteId} no cupo, probando ciclo hermano {$ciclo->id} {$ciclo->nombre}");
            }

            $generados = $this->generarLoteParaCurso(
                $empresa, $familia, $ciclo, 'ambos_cursos', $cantidad,
                $nivelGrupo, $duracion, $usuario, $consecuenciasArray, $maxIntentos
            );

            if ($generados > 0) {
                if ($ciclo->id !== $cicloPreferenteId) {
                    $this->line("  ✓ Conseguido con ciclo hermano {$ciclo->id} {$ciclo->nombre} (el resto de retos de la empresa, si los hay, siguen con el ciclo {$cicloPreferenteId}).");
                }
                break;
            }
        }

        return $generados;
    }

    /**
     * Modo de relleno (--completar-transversales): tras un lote normal, algunas empresas
     * se quedan sin su grupo 'ambos_cursos' (es el prompt que más currículo necesita y más
     * fácil agota el presupuesto de tokens/minuto de OpenAI). En vez de relanzar todo el
     * comando (que regeneraría también 1º/2º, ya generados), esta pasada SOLO ataca las
     * empresas con retos pero sin ningún reto curso='ambos_cursos', y solo pide ese grupo
     * — así no compite por presupuesto de tokens con las llamadas de 1º/2º de la misma
     * empresa justo antes (una de las causas de que fallara en el lote normal).
     */
    private function completarTransversales(User $usuario, bool $commit, int $limite): int
    {
        $cantidadTransversales = max(1, (int) $this->option('transversales'));

        $empresasQuery = Empresa::where('es_simulada', true)
            ->where('centro_id', self::CENTRO_ID)
            ->whereHas('microretos', fn ($q) => $q->where('es_simulado', true))
            ->whereDoesntHave('microretos', fn ($q) => $q->where('es_simulado', true)->where('curso', 'ambos_cursos'))
            ->with('familias')->orderBy('id');
        if ($limite > 0) {
            $empresasQuery->limit($limite);
        }
        $empresas = $empresasQuery->get();

        if ($empresas->isEmpty()) {
            $this->info('Todas las empresas simuladas con retos ya tienen su grupo transversal. Nada que completar.');
            return self::SUCCESS;
        }

        $this->info("Empresas sin transversal: {$empresas->count()}. Generando {$cantidadTransversales} retos transversales para cada una.");
        $this->newLine();

        $totalGenerados = 0;

        foreach ($empresas as $empresa) {
            $familia = $empresa->familias->first();
            if (!$familia) {
                $this->warn("Empresa #{$empresa->id} ({$empresa->nombre_comercial}) sin familia asociada — omitida.");
                continue;
            }

            $ciclosCandidatos = self::CICLOS_POR_FAMILIA[$familia->id] ?? [];
            if (empty($ciclosCandidatos)) {
                $this->warn("Familia '{$familia->nombre}' sin ciclo curado — empresa #{$empresa->id} omitida.");
                continue;
            }

            $nivelGrupo = self::NIVELES[$empresa->id % count(self::NIVELES)];
            $duracion   = self::DURACIONES[$empresa->id % count(self::DURACIONES)];
            $consecuenciasArray = array_values(array_filter([$empresa->consecuencias]));

            $cicloOriginalId = $ciclosCandidatos[$empresa->id % count($ciclosCandidatos)];

            $this->line("Empresa #{$empresa->id} {$empresa->nombre_comercial} | familia {$familia->nombre} | {$cantidadTransversales} retos transversales a generar (ciclo preferente: {$cicloOriginalId})");

            if (!$commit) {
                continue;
            }

            $generados = $this->generarTransversalesConFallback(
                $empresa, $familia, $ciclosCandidatos, $cicloOriginalId, $cantidadTransversales,
                $nivelGrupo, $duracion, $usuario, $consecuenciasArray
            );

            $totalGenerados += $generados;
            $this->line("  → {$generados}/{$cantidadTransversales} retos transversales generados para {$empresa->nombre_comercial}.");
        }

        $this->newLine();
        if ($commit) {
            $this->info("Total de retos transversales generados en esta ejecución: {$totalGenerados}.");
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para generar de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * Resuelve la familia y el ciclo "flagship" (CICLOS_POR_FAMILIA) de una empresa —
     * misma lógica para el flujo normal y para completarTransversales(), antes duplicada.
     *
     * @return array{0: ?\App\Models\Familia, 1: ?CicloFormativo}
     */
    private function resolverFamiliaYCiclo(Empresa $empresa): array
    {
        $familia = $empresa->familias->first();
        if (!$familia) {
            $this->warn("Empresa #{$empresa->id} ({$empresa->nombre_comercial}) sin familia asociada — omitida.");
            return [null, null];
        }

        $ciclosCandidatos = self::CICLOS_POR_FAMILIA[$familia->id] ?? [];
        if (empty($ciclosCandidatos)) {
            $this->warn("Familia '{$familia->nombre}' (id {$familia->id}) sin ciclo curado en CICLOS_POR_FAMILIA — empresa #{$empresa->id} omitida.");
            return [null, null];
        }

        $ciclo = CicloFormativo::find($ciclosCandidatos[$empresa->id % count($ciclosCandidatos)]);
        if (!$ciclo) {
            $this->warn("Ciclo formativo no encontrado para empresa #{$empresa->id} — omitida.");
            return [null, null];
        }

        return [$familia, $ciclo];
    }

    /**
     * Reparte los retos de una empresa entre 1º, 2º y transversal ("Ambos Cursos"): al
     * menos 2 de 1º y 2 de 2º (pedido explícito del usuario), el resto transversal — así
     * cada empresa tiene variedad real de curso en su biblioteca, no todos el mismo. Si
     * el ciclo no tiene currículo de ambos cursos disponible, cae al único que sí tenga
     * (nunca se pide un curso sin módulos reales, rompería la cobertura RA/CE).
     *
     * @return array<int|string, int> clave = 1, 2 o 'ambos_cursos'; valor = cantidad de retos.
     */
    private function distribuirCursos(array $cursosDisponibles, int $cantidadTotal): array
    {
        $tiene1 = in_array(1, $cursosDisponibles, true);
        $tiene2 = in_array(2, $cursosDisponibles, true);

        if (!$tiene1 || !$tiene2) {
            return [($tiene2 ? 2 : 1) => $cantidadTotal];
        }

        $minPorCurso = 2;
        if ($cantidadTotal < $minPorCurso * 2) {
            // No llega para cubrir el mínimo de los dos cursos — reparto simple, sin transversal.
            $curso1 = (int) ceil($cantidadTotal / 2);
            return array_filter([1 => $curso1, 2 => $cantidadTotal - $curso1]);
        }

        return array_filter([
            1              => $minPorCurso,
            2              => $minPorCurso,
            'ambos_cursos' => $cantidadTotal - ($minPorCurso * 2),
        ]);
    }

    /**
     * Llama a generar() con reintento y backoff creciente, cubriendo TANTO una respuesta
     * sin 'microretos' (típicamente un 429 de rate-limit que generar() no distingue de
     * otros errores, siempre devuelve el mismo ['error' => '...']) COMO una excepción real
     * (timeout de cURL, corte de conexión — generar() no las captura, se propagan). Es una
     * llamada de solo lectura hacia OpenAI sin efectos secundarios, reintentarla es seguro.
     */
    private function llamarConReintento(array $payload, User $usuario, int|string $cursoSeleccionado, int $empresaId, int $maxIntentos = self::MAX_INTENTOS_IA): array
    {
        $data = ['error' => 'sin intentos ejecutados'];

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $request   = $this->peticion(GenerarMicroretoRequest::class, $payload, $usuario);
                $respuesta = app(MicroretoIAController::class)->generar($request);
                $data      = json_decode($respuesta->getContent(), true);
            } catch (\Throwable $e) {
                $data = ['error' => $e->getMessage()];
            }

            if (isset($data['microretos']) && is_array($data['microretos'])) {
                return $data;
            }

            if ($intento < $maxIntentos) {
                $espera = self::ESPERA_BASE_SEGUNDOS * $intento;
                $this->warn("  … la IA no devolvió microretos válidos (curso {$cursoSeleccionado}), reintentando en {$espera}s (intento {$intento}/{$maxIntentos}): " . json_encode($data));
                sleep($espera);
            }
        }

        $this->error("  ✗ La IA no devolvió microretos válidos tras {$maxIntentos} intentos para la empresa #{$empresaId} (curso {$cursoSeleccionado}): " . json_encode($data));

        return $data;
    }

    /**
     * Genera $cantidadTotal retos para un único valor de 'cursoSeleccionado' (1, 2 o
     * 'ambos_cursos'), en lotes de máx. CANTIDAD_MAX_POR_LLAMADA por llamada a la IA —
     * es la lógica que antes vivía en el bucle principal, ahora reutilizable por cada
     * grupo de la distribución 1º/2º/transversal.
     */
    private function generarLoteParaCurso(
        Empresa $empresa,
        \App\Models\Familia $familia,
        CicloFormativo $ciclo,
        int|string $cursoSeleccionado,
        int $cantidadTotal,
        string $nivelGrupo,
        string $duracion,
        User $usuario,
        array $consecuenciasArray,
        int $maxIntentos = self::MAX_INTENTOS_IA
    ): int {
        $moduloTxt = $cursoSeleccionado === 'ambos_cursos'
            ? 'Transversal (1º y 2º)'
            : "A determinar por IA ({$cursoSeleccionado}º Curso)";

        $pendiente = $cantidadTotal;
        $generados = 0;

        while ($pendiente > 0) {
            $lote = min(self::CANTIDAD_MAX_POR_LLAMADA, $pendiente);

            try {
                $payload = [
                    'empresa_id'         => $empresa->id,
                    'empresaNombre'      => $empresa->nombre_comercial,
                    'empresaSector'      => $empresa->sector,
                    'empresaTamano'      => $empresa->tamano,
                    'empresaUbicacion'   => $empresa->municipio,
                    'diaANormal'         => $empresa->dia_a_normal,
                    'friccionArea'       => $empresa->friccion_area,
                    'friccionProblema'   => $empresa->friccion_problema,
                    'restricciones'      => $empresa->restricciones,
                    'loQueNoQuieren'     => $empresa->lo_que_no_quieren,
                    'consecuencias'      => $consecuenciasArray,
                    'expectativasAlumno' => $empresa->expectativas_alumno,
                    'ciclo_nombre'       => $ciclo->nombre,
                    'ciclo_id'           => $ciclo->id,
                    'nivelGrupo'         => $nivelGrupo,
                    'cursoSeleccionado'  => $cursoSeleccionado,
                    'modulo_id'          => null,
                    'cantidad'           => $lote,
                    'familia'            => $familia->nombre,
                    'duracion'           => $duracion,
                ];

                $data = $this->llamarConReintento($payload, $usuario, $cursoSeleccionado, $empresa->id, $maxIntentos);

                if (!isset($data['microretos']) || !is_array($data['microretos'])) {
                    break; // ya se ha logueado el error dentro de llamarConReintento()
                }

                foreach ($data['microretos'] as $reto) {
                    $modulosUnicos = collect($reto['evaluacion_oficial'] ?? [])->pluck('modulo')->filter()->unique();

                    // Persistencia directa vía Eloquent en vez de reutilizar
                    // MicroretoIAController::guardarEnBD()/StoreMicroretoRequest: sus reglas
                    // de validación fijan 'curso' => 'nullable|integer', pero el caso
                    // "Ambos Cursos" guarda literalmente el string 'ambos_cursos' (así lo
                    // hace también el propio frontend, ver GeneradorMicroretos.vue línea
                    // ~829) — la validación lo rechazaría con un 422. Es una inconsistencia
                    // ya existente entre el FormRequest y la columna `curso` (string desde
                    // la migración change_curso_to_string_in_microretos), reportada en el
                    // informe final; aquí se evita reproduciendo solo el saneado de texto
                    // que aplicaría StoreMicroretoRequest, no la validación de 'curso'.
                    $microreto = Microreto::create([
                        'empresa_id'         => $empresa->id,
                        'empresa_nombre'     => $empresa->nombre_comercial,
                        'titulo'             => $this->limpiarTexto($reto['titulo'] ?? null),
                        'subtitulo'          => $this->limpiarTexto($reto['subtitulo'] ?? null),
                        'quien_es'           => $this->limpiarTexto($reto['quien_es'] ?? null),
                        'dia_a_dia'          => $this->limpiarTexto($reto['dia_a_dia'] ?? null),
                        'pregunta_reto'      => $this->limpiarTexto($reto['pregunta_reto'] ?? null),
                        'dificultades'       => $this->limpiarArray($reto['dificultades'] ?? []),
                        'que_necesitan'      => $this->limpiarArray($reto['que_necesitan'] ?? []),
                        'limitaciones'       => $this->limpiarArray($reto['limitaciones'] ?? []),
                        'prototipos'         => $this->limpiarArray($reto['prototipos'] ?? []),
                        'ods_sugeridos'      => $this->limpiarArray($reto['ods_sugeridos'] ?? []),
                        'evaluacion_oficial' => $reto['evaluacion_oficial'] ?? [], // ids ya resueltos por RaCeCatalogoService::resolver()
                        'tips_profesorado'   => $this->limpiarArray($reto['tips_profesorado'] ?? []),
                        'variantes'          => $this->limpiarArray($reto['variantes'] ?? []),
                        'nivel_grupo'        => $nivelGrupo,
                        'curso'              => (string) $cursoSeleccionado,
                        'ciclo_id'           => $ciclo->id,
                        'ciclo'              => $ciclo->nombre,
                        'modulo'             => $moduloTxt,
                        'multimodulo'        => $modulosUnicos->count() > 1,
                        'duracion'           => $duracion,
                        'es_simulado'        => true,
                    ]);

                    $generados++;
                    $aviso = !empty($reto['aviso_cobertura_incompleta']) ? ' [aviso: cobertura de currículo incompleta]' : '';
                    $this->info("  ✓ Reto #{$microreto->id}: {$microreto->titulo}{$aviso}");
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->error("  ✗ Validación falló para empresa #{$empresa->id} (curso {$cursoSeleccionado}): " . json_encode($e->errors()));
                break;
            } catch (\Throwable $e) {
                $this->error("  ✗ Error generando retos para empresa #{$empresa->id} (curso {$cursoSeleccionado}): " . $e->getMessage());
                break;
            }

            $pendiente -= $lote;
        }

        return $generados;
    }

    private function limpiarTexto(?string $valor): ?string
    {
        return $valor !== null ? strip_tags($valor) : null;
    }

    private function limpiarArray(array $valores): array
    {
        return array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $valores);
    }
}
