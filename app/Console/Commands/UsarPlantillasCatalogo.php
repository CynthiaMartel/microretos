<?php

namespace App\Console\Commands;

use App\Models\CentroEducativo;
use App\Models\Empresa;
use App\Services\EmpresaCatalogoService;
use App\Services\EmpresaFamiliaService;
use App\Services\EmpresaFicticiaIAService;
use Illuminate\Console\Command;

/**
 * Copia plantillas del catálogo DuaLab a un centro (EmpresaCatalogoService::usarEnCentro, lo
 * mismo que el botón "Usar en mi centro") y reescribe con IA el interés de cada familia en la
 * copia, porque el del paquete es una frase genérica repetida. Pensado para dar a la demo
 * empresas de otros sectores que piden alumnado de Marketing, Administración o Informática.
 *
 * Sin --preview ni --commit: dry-run sin coste. Idempotente: una plantilla ya copiada al
 * centro se reutiliza y no se vuelve a reescribir.
 */
class UsarPlantillasCatalogo extends Command
{
    protected $signature = 'catalogo:usar-plantillas
                            {--centro= : Id del centro al que se copian (obligatorio).}
                            {--plantillas= : Ids de plantillas del catálogo separados por coma (obligatorio).}
                            {--preview : Llama a la IA sobre la plantilla y muestra el resultado, sin escribir en BD.}
                            {--commit : Copia las plantillas y guarda los intereses.}';

    protected $description = 'Copia plantillas del catálogo a un centro y concreta con IA sus intereses por familia (dry-run por defecto).';

    public function __construct(
        private EmpresaCatalogoService $catalogo,
        private EmpresaFamiliaService $familias,
        private EmpresaFicticiaIAService $ia,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $centro = CentroEducativo::find((int) $this->option('centro'));
        $ids = array_filter(array_map('intval', explode(',', (string) $this->option('plantillas'))));
        if (!$centro || !$ids) {
            $this->error('Indica --centro=<id> y --plantillas=<ids>.');
            return self::FAILURE;
        }

        $plantillas = Empresa::with('familias')->where('es_catalogo', true)->whereIn('id', $ids)->orderBy('id')->get();
        $faltan = array_diff($ids, $plantillas->pluck('id')->all());
        if ($faltan) $this->warn('No son plantillas del catálogo: ' . implode(', ', $faltan));

        $this->table(['Id', 'Empresa', 'Sector', 'Familias'], $plantillas->map(fn ($e) => [
            $e->id, $e->nombre_comercial, $e->sector, $e->familias->pluck('nombre')->implode(', '),
        ])->all());

        $escribir = (bool) $this->option('commit');
        if (!$escribir && !$this->option('preview')) {
            $this->warn('Dry-run: --preview para ver los intereses que genera la IA, --commit para copiar y guardar.');
            return self::SUCCESS;
        }

        foreach ($plantillas as $plantilla) {
            if ($escribir) {
                [$empresa, $nueva] = $this->catalogo->usarEnCentro($plantilla, $centro);
                if (!$nueva) {
                    $this->line("#{$plantilla->id}: ya estaba copiada en el centro (#{$empresa->id}), se salta.");
                    continue;
                }
            } else {
                $empresa = $plantilla;
            }

            $intereses = $this->ia->concretarIntereses($empresa);
            if ($intereses === null) {
                $this->warn("#{$empresa->id}: la IA no devolvió intereses válidos; se queda el texto de la plantilla.");
                continue;
            }
            if ($escribir) $this->familias->guardarIntereses($empresa->id, $intereses);

            $this->newLine();
            $this->info(($escribir ? "#{$empresa->id} (copia de #{$plantilla->id})" : "#{$plantilla->id}") . " · {$empresa->nombre_comercial} ({$empresa->sector})");
            foreach ($intereses as $familia => $texto) $this->line("  {$familia}: {$texto}");
        }

        $this->info($escribir ? 'Plantillas copiadas al centro.' : 'Preview: no se ha escrito nada en BD.');
        return self::SUCCESS;
    }
}
