<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Familia;
use App\Services\EmpresaFamiliaService;
use App\Services\EmpresaFicticiaIAService;
use App\Support\SectoresEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa el paquete de empresas tipo de DuaLab (CSV con punto y coma, UTF-8 con BOM) como
 * plantillas NUEVAS del catálogo: no toca ninguna empresa existente.
 *
 * Del paquete se usan los datos sólidos (sector, tamaño, municipio, necesidad, problema con
 * cifras, restricciones, consecuencias, nivel de digitalización, familias e intereses). Los
 * débiles (nombre con número, actividad, P1, P2b, P3b y P5) los reescribe la IA para que
 * cuadren con la necesidad (EmpresaFicticiaIAService::completarEmpresaTipo). Se ignoran las
 * configuraciones de retos, las 8 dimensiones de digitalización y la vista maestra.
 *
 * Idempotente por id_externo (EMP-0001…): una empresa ya importada se salta.
 * Sin --preview ni --commit: dry-run sin coste (ni IA ni escritura), solo valida y lista el plan.
 *
 * @phpstan-type Fila array{sector: string, sector_corregido: bool, tamano: string, empleados: int,
 *     municipio: string, provincia: string, actividad: string, nivel_digitalizacion: string,
 *     necesidad: string, friccion_problema: string, restricciones: string, consecuencias: string,
 *     intereses: array<string, string>}
 */
class ImportarPaqueteCatalogo extends Command
{
    protected $signature = 'catalogo:importar-paquete
                            {ruta? : Carpeta del paquete (por defecto DUALAB_PAQUETE_REVISION_IMPORTACION en la raíz).}
                            {--limit=0 : Tope de empresas a procesar en esta ejecución (0 = todas las pendientes).}
                            {--preview : Llama a la IA y muestra el resultado, sin escribir en BD.}
                            {--commit : Llama a la IA y crea las plantillas en BD.}';

    protected $description = 'Importa el paquete de empresas tipo de DuaLab como plantillas del catálogo (dry-run por defecto).';

    // Sector del paquete corregido según su sector original: el paquete los dejó en
    // "Consultoría y servicios a empresas" aunque tienen sector propio en la lista cerrada.
    private const SECTOR_POR_ORIGINAL = [
        'Marketing y comunicación'  => 'Marketing, publicidad y diseño',
        'Servicios sociosanitarios' => 'Servicios sociales y tercer sector',
        'Asociación empresarial'    => 'Servicios sociales y tercer sector',
        'Federación sectorial'      => 'Servicios sociales y tercer sector',
        'Mantenimiento industrial'  => 'Industria y fabricación',
    ];

    public function __construct(private EmpresaFicticiaIAService $ia, private EmpresaFamiliaService $familias)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ruta = rtrim($this->argument('ruta') ?: base_path('DUALAB_PAQUETE_REVISION_IMPORTACION'), '/');
        $escribir = (bool) $this->option('commit');
        $usarIA = $escribir || $this->option('preview');

        $tieneColumnas = Schema::hasColumn('empresas', 'id_externo');
        if ($escribir && !$tieneColumnas) {
            $this->error('Falta la migración de id_externo / nivel_digitalizacion: ejecuta php artisan migrate antes de --commit.');
            return self::FAILURE;
        }

        try {
            $filas = $this->leerPaquete($ruta);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // Familias por nombre exacto: los ids de local y producción no coinciden
        $nombresFamilia = collect($filas)->flatMap(fn ($f) => array_keys($f['intereses']))->unique()->values();
        $familias = Familia::whereIn('nombre', $nombresFamilia)->get()->keyBy('nombre');

        // Ya importadas (también en la papelera: el índice único las sigue contando)
        $importadas = $tieneColumnas
            ? Empresa::withTrashed()->whereNotNull('id_externo')->pluck('id_externo')->flip()
            : collect();

        $pendientes = [];
        $errores = [];
        $saltadas = 0;
        foreach ($filas as $id => $fila) {
            if ($importadas->has($id)) { $saltadas++; continue; }
            $problemas = $this->validar($fila, $familias);
            if ($problemas) { $errores[$id] = $problemas; continue; }
            $pendientes[$id] = $fila;
        }

        if ($errores) {
            $this->warn('Filas con errores (no se importan):');
            foreach ($errores as $id => $problemas) $this->line("  {$id}: " . implode('; ', $problemas));
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) $pendientes = array_slice($pendientes, 0, $limit, true);

        $this->table(['Id', 'Sector', 'Tamaño', 'Digital.', 'Necesidad', 'Familias'], collect($pendientes)->map(fn ($f, $id) => [
            $id, $f['sector'] . ($f['sector_corregido'] ? ' *' : ''), $f['tamano'], $f['nivel_digitalizacion'],
            $f['necesidad'], implode(', ', array_keys($f['intereses'])),
        ])->values()->all());
        $this->info(count($filas) . " en el paquete · {$saltadas} ya importadas · " . count($errores) . ' con errores · ' . count($pendientes) . ' a procesar.');
        if (collect($pendientes)->contains('sector_corregido', true)) {
            $this->line('  * sector corregido según el sector original del paquete');
        }

        if (!$usarIA) {
            $this->warn('Dry-run: --preview para ver lo que genera la IA sin guardar, --commit para crear las plantillas.');
            return self::SUCCESS;
        }

        $creadas = 0;
        $fallidas = [];
        foreach ($pendientes as $id => $fila) {
            $redactado = $this->ia->completarEmpresaTipo($fila);
            if ($redactado === null) {
                $fallidas[] = $id;
                $this->warn("{$id}: la IA no devolvió una ficha completa, se salta.");
                continue;
            }

            if (!$escribir) {
                $this->mostrar($id, $fila, $redactado);
                continue;
            }

            $empresa = $this->crear($id, $fila, $redactado, $familias);
            $creadas++;
            $this->line("{$id} → #{$empresa->id} {$empresa->nombre_comercial}");
        }

        if ($fallidas) $this->warn(count($fallidas) . ' fallidas (relanza el comando para reintentarlas): ' . implode(', ', $fallidas));
        $this->info($escribir ? "{$creadas} plantillas creadas." : 'Preview: no se ha escrito nada en BD.');

        return self::SUCCESS;
    }

    /**
     * Une las tablas del paquete por empresa_id_externo.
     *
     * @return array<string, Fila>
     */
    private function leerPaquete(string $ruta): array
    {
        $empresas    = $this->leerCsv("{$ruta}/EMPRESAS_DUALAB_IMPORT.csv");
        $diagnostico = collect($this->leerCsv("{$ruta}/DIAGNOSTICO_P1_P5_DUALAB_IMPORT.csv"))->keyBy('empresa_id_externo');
        $digital     = collect($this->leerCsv("{$ruta}/DIAG_DIGITAL_REFERENCIA_DUALAB_IMPORT.csv"))->keyBy('empresa_id_externo');
        $intereses   = collect($this->leerCsv("{$ruta}/EMPRESA_FAMILIA_DUALAB_IMPORT.csv"))->groupBy('empresa_id_externo');

        $filas = [];
        foreach ($empresas as $e) {
            $id = trim($e['empresa_id_externo'] ?? '');
            $d = $diagnostico->get($id, []);
            $sector = trim($e['sector'] ?? '');
            $corregido = self::SECTOR_POR_ORIGINAL[trim($e['sector_original_referencia'] ?? '')] ?? null;

            $filas[$id] = [
                'sector'               => $corregido ?? $sector,
                'sector_corregido'     => $corregido !== null && $corregido !== $sector,
                'tamano'               => trim($e['tamano'] ?? ''),
                'empleados'            => (int) ($e['num_empleados_referencia'] ?? 0),
                'municipio'            => trim($e['municipio'] ?? ''),
                'provincia'            => trim($e['provincia'] ?? ''),
                'actividad'            => trim($e['actividad'] ?? ''),
                'nivel_digitalizacion' => trim($d['nivel_digitalizacion_referencia'] ?? ''),
                // La necesidad de fondo es el área de fricción (P2): da más coherencia que la del paquete
                'necesidad'            => trim($digital->get($id)['necesidad_original'] ?? ''),
                'friccion_problema'    => trim($d['friccion_problema'] ?? ''),
                // Chips: en el paquete van con ";", nosotros los guardamos con ", " (como el Generador)
                'restricciones'        => $this->chips($d['restricciones'] ?? ''),
                'consecuencias'        => $this->chips($d['consecuencias'] ?? ''),
                'intereses'            => $intereses->get($id, collect())
                    ->mapWithKeys(fn ($r) => [trim($r['familia_nombre_exacto'] ?? '') => trim($r['interes'] ?? '')])->all(),
            ];
        }
        return $filas;
    }

    /** @return list<array<string, string>> */
    private function leerCsv(string $archivo): array
    {
        if (!is_readable($archivo)) throw new \RuntimeException("No se puede leer {$archivo}");

        $f = fopen($archivo, 'r');
        $cabecera = $f ? fgetcsv($f, 0, ';') : false;
        if (!$f || !$cabecera) throw new \RuntimeException("{$archivo} está vacío o no se puede abrir");

        $cabecera = array_map(fn ($c) => (string) $c, $cabecera);
        $cabecera[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]); // BOM
        $filas = [];
        while (($valores = fgetcsv($f, 0, ';')) !== false) {
            if ($valores === [null]) continue; // línea vacía
            $valores = array_map(fn ($v) => (string) $v, array_pad($valores, count($cabecera), ''));
            $filas[] = array_combine($cabecera, array_slice($valores, 0, count($cabecera)));
        }
        fclose($f);
        return $filas;
    }

    private function chips(string $valor): string
    {
        return collect(explode(';', $valor))->map(fn ($c) => trim($c))->filter()->implode(', ');
    }

    /**
     * Mismas listas cerradas que el modal de empresa y el Generador.
     *
     * @param Fila $fila
     * @param \Illuminate\Support\Collection<string, Familia> $familias
     * @return list<string>
     */
    private function validar(array $fila, $familias): array
    {
        $problemas = [];
        if (!SectoresEmpresa::esValido($fila['sector'])) $problemas[] = "sector «{$fila['sector']}» fuera de la lista";
        if (!in_array($fila['tamano'], EmpresaFicticiaIAService::TAMANOS, true)) $problemas[] = "tamaño «{$fila['tamano']}» no válido";
        if (!in_array($fila['nivel_digitalizacion'], Empresa::NIVELES_DIGITALIZACION, true)) $problemas[] = "nivel de digitalización «{$fila['nivel_digitalizacion']}» no válido";
        if ($fila['necesidad'] === '' || $fila['friccion_problema'] === '') $problemas[] = 'falta la necesidad o el problema';

        $fuera = array_diff(explode(', ', $fila['restricciones']), EmpresaFicticiaIAService::LIMITACIONES, ['']);
        if ($fuera) $problemas[] = 'restricciones fuera de la lista: ' . implode(', ', $fuera);
        $fuera = array_diff(explode(', ', $fila['consecuencias']), EmpresaFicticiaIAService::CONSECUENCIAS, ['']);
        if ($fuera) $problemas[] = 'consecuencias fuera de la lista: ' . implode(', ', $fuera);

        if (!$fila['intereses']) $problemas[] = 'sin familias';
        foreach ($fila['intereses'] as $familia => $interes) {
            if (!$familias->has($familia)) $problemas[] = "familia «{$familia}» no existe";
            if (mb_strlen($interes) > 500) $problemas[] = "interés de «{$familia}» de más de 500 caracteres";
        }
        return $problemas;
    }

    /**
     * @param Fila $fila
     * @param array<string, string> $redactado
     * @param \Illuminate\Support\Collection<string, Familia> $familias
     */
    private function crear(string $id, array $fila, array $redactado, $familias): Empresa
    {
        return DB::transaction(function () use ($id, $fila, $redactado, $familias) {
            $empresa = new Empresa([
                'nombre_comercial'     => Empresa::nombreLibreEnCentro(null, $redactado['nombre_comercial']),
                'sector'               => $fila['sector'],
                'tamano'               => $fila['tamano'],
                'nivel_digitalizacion' => $fila['nivel_digitalizacion'],
                'municipio'            => $fila['municipio'],
                'provincia'            => $fila['provincia'],
                'actividad'            => $redactado['actividad'],
                'dia_a_normal'         => $redactado['dia_a_normal'],
                'friccion_area'        => mb_substr($fila['necesidad'], 0, 400),
                'friccion_problema'    => $redactado['friccion_problema'],
                'restricciones'        => $fila['restricciones'],
                'consecuencias'        => $fila['consecuencias'],
                'lo_que_no_quieren'    => $redactado['lo_que_no_quieren'],
                'expectativas_alumno'  => $redactado['expectativas_alumno'],
                'es_simulada'          => true,
            ]);
            // No fillables: solo los fija el backend. Sin centro = plantilla del catálogo.
            $empresa->es_catalogo = true;
            $empresa->id_externo = $id;
            $empresa->save();

            foreach (array_keys($fila['intereses']) as $nombre) {
                // validar() ya comprobó que existen
                $familia = $familias->get((string) $nombre) ?? throw new \LogicException("Familia {$nombre} no encontrada");
                $this->familias->anadir($empresa->id, $familia);
            }
            $this->familias->guardarIntereses($empresa->id, $fila['intereses']);

            return $empresa;
        });
    }

    /**
     * @param Fila $fila
     * @param array<string, string> $redactado
     */
    private function mostrar(string $id, array $fila, array $redactado): void
    {
        $this->newLine();
        $this->info("── {$id} · {$redactado['nombre_comercial']} ({$fila['sector']}, {$fila['tamano']}, {$fila['municipio']}, digitalización {$fila['nivel_digitalizacion']})");
        $this->line("Actividad: {$redactado['actividad']}");
        $this->line("P1 Día a día: {$redactado['dia_a_normal']}");
        $this->line("P2 Área: {$fila['necesidad']}");
        $this->line("P2b Problema: {$redactado['friccion_problema']}");
        $this->line("P3 Restricciones: {$fila['restricciones']}");
        $this->line("P3b No quieren: {$redactado['lo_que_no_quieren']}");
        $this->line("P4 Consecuencias: {$fila['consecuencias']}");
        $this->line("P5 Expectativas: {$redactado['expectativas_alumno']}");
        foreach ($fila['intereses'] as $familia => $interes) $this->line("Interés {$familia}: {$interes}");
    }
}
