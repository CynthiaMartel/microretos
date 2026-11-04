<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Familia;
use App\Services\EmpresaFamiliaService;
use App\Services\EmpresaFicticiaIAService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mejora con IA el diagnóstico de las empresas ficticias de un centro, con el nivel de las
 * plantillas del paquete de DuaLab (EmpresaFicticiaIAService::enriquecerEmpresa). Cada empresa
 * conserva su id, nombre, sector, actividad, ubicación, chips, retos y proyectos; cambian el
 * nivel de digitalización, el área de fricción, P1, P2b, P3b y P5, y puede ganar familias.
 *
 * Los retos ya creados no cambian: guardan su propia copia del diagnóstico.
 * Idempotente: una empresa con nivel de digitalización ya está enriquecida y se salta.
 * Sin --preview ni --commit: dry-run sin coste (ni IA ni escritura), solo lista el plan.
 */
class EnriquecerEmpresas extends Command
{
    protected $signature = 'empresas:enriquecer
                            {--centro= : Id del centro cuyas empresas ficticias se enriquecen (obligatorio).}
                            {--excluir= : Ids de empresa separados por coma que no se tocan (p. ej. editadas a mano).}
                            {--familias=Administración y Gestión,Comercio y Marketing,Informática y Comunicaciones : Familias que se le pueden añadir a cada empresa, por nombre.}
                            {--limit=0 : Tope de empresas a procesar en esta ejecución (0 = todas las pendientes).}
                            {--preview : Llama a la IA y muestra el resultado, sin escribir en BD.}
                            {--commit : Llama a la IA y guarda los cambios.}';

    protected $description = 'Enriquece con IA el diagnóstico de las empresas ficticias de un centro (dry-run por defecto).';

    public function __construct(private EmpresaFicticiaIAService $ia, private EmpresaFamiliaService $familias)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $centroId = (int) $this->option('centro');
        if ($centroId <= 0) {
            $this->error('Indica el centro con --centro=<id>.');
            return self::FAILURE;
        }
        $escribir = (bool) $this->option('commit');
        $excluir = array_filter(array_map('intval', explode(',', (string) $this->option('excluir'))));
        $nombresFamilia = array_filter(array_map('trim', explode(',', (string) $this->option('familias'))));
        $familiasPermitidas = Familia::whereIn('nombre', $nombresFamilia)->pluck('nombre')->all();

        $empresas = Empresa::with('familias')
            ->where('centro_id', $centroId)
            ->where('es_simulada', true)
            ->whereNull('nivel_digitalizacion')
            ->whereNotIn('id', $excluir)
            ->orderBy('id')
            ->when((int) $this->option('limit') > 0, fn ($q) => $q->take((int) $this->option('limit')))
            ->get();

        $this->table(['Id', 'Empresa', 'Familias', 'Área actual'], $empresas->map(fn ($e) => [
            $e->id, $e->nombre_comercial, $e->familias->pluck('nombre')->implode(', '), mb_substr((string) $e->friccion_area, 0, 50),
        ])->all());
        $this->info($empresas->count() . ' empresas a procesar' . ($excluir ? ' (excluidas: ' . implode(', ', $excluir) . ')' : '') . '.');

        if (!$escribir && !$this->option('preview')) {
            $this->warn('Dry-run: --preview para ver lo que genera la IA sin guardar, --commit para guardar.');
            return self::SUCCESS;
        }

        $hechas = 0;
        $fallidas = [];
        foreach ($empresas as $empresa) {
            $vinculadas = $empresa->familias->pluck('nombre')->all();
            $resultado = $this->ia->enriquecerEmpresa(
                $empresa,
                array_values(array_diff($familiasPermitidas, $vinculadas)),
                $this->referencias($vinculadas, $empresa->id),
            );
            if ($resultado === null) {
                $fallidas[] = $empresa->id;
                $this->warn("#{$empresa->id}: la IA no devolvió una ficha válida, se salta.");
                continue;
            }

            if (!$escribir) {
                $this->mostrar($empresa, $resultado);
                continue;
            }

            DB::transaction(function () use ($empresa, $resultado) {
                $empresa->fill($resultado['columnas'])->save();
                foreach ($resultado['intereses'] as $nombre => $_) {
                    $familia = Familia::where('nombre', $nombre)->first();
                    if ($familia) $this->familias->anadir($empresa->id, $familia);
                }
                if ($resultado['intereses']) $this->familias->guardarIntereses($empresa->id, $resultado['intereses']);
            });
            $hechas++;
            $this->line("#{$empresa->id} {$empresa->nombre_comercial}: {$resultado['columnas']['nivel_digitalizacion']}"
                . ($resultado['intereses'] ? ' · + ' . implode(', ', array_keys($resultado['intereses'])) : ''));
        }

        if ($fallidas) $this->warn(count($fallidas) . ' fallidas (relanza para reintentarlas): ' . implode(', ', $fallidas));
        $this->info($escribir ? "{$hechas} empresas enriquecidas." : 'Preview: no se ha escrito nada en BD.');

        return self::SUCCESS;
    }

    /**
     * Dos plantillas del paquete de DuaLab (id_externo) de una familia de la empresa, como
     * muestra del nivel de detalle; varían por empresa para no anclar todas al mismo ejemplo.
     *
     * @param  string[]  $familias
     * @return array<int, array{friccion_area: string, friccion_problema: string, expectativas_alumno: string}>
     */
    private function referencias(array $familias, int $semilla): array
    {
        $candidatas = Empresa::where('es_catalogo', true)->whereNotNull('id_externo')
            ->whereHas('familias', fn ($q) => $q->whereIn('nombre', $familias))
            ->orderBy('id')->get(['id', 'friccion_area', 'friccion_problema', 'expectativas_alumno']);
        if ($candidatas->isEmpty()) return [];

        return collect([0, 1])
            ->map(fn ($i) => $candidatas->get(($semilla + $i * 7) % $candidatas->count()))
            ->filter()
            ->unique('id')
            ->map(fn (Empresa $e) => [
                'friccion_area'       => (string) $e->friccion_area,
                'friccion_problema'   => (string) $e->friccion_problema,
                'expectativas_alumno' => (string) $e->expectativas_alumno,
            ])->values()->all();
    }

    /** @param array{columnas: array<string, string>, intereses: array<string, string>} $resultado */
    private function mostrar(Empresa $empresa, array $resultado): void
    {
        $c = $resultado['columnas'];
        $this->newLine();
        $this->info("── #{$empresa->id} · {$empresa->nombre_comercial} ({$empresa->sector}, {$empresa->tamano}) → digitalización {$c['nivel_digitalizacion']}");
        $this->line("P2 Área:   {$empresa->friccion_area}  →  {$c['friccion_area']}");
        $this->line('P2b ANTES: ' . mb_substr((string) $empresa->friccion_problema, 0, 300) . '…');
        $this->line("P2b AHORA: {$c['friccion_problema']}");
        $this->line("P1 Día a día: {$c['dia_a_normal']}");
        $this->line("P3b No quieren: {$c['lo_que_no_quieren']}");
        $this->line("P5 Expectativas: {$c['expectativas_alumno']}");
        foreach ($resultado['intereses'] as $familia => $interes) $this->line("+ Familia {$familia}: {$interes}");
    }
}
