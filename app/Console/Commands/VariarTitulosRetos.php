<?php

namespace App\Console\Commands;

use App\Models\Microproyecto;
use App\Models\Microreto;
use App\Services\EmpresaFicticiaIAService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reescribe con IA los títulos repetitivos de los retos simulados de un centro: los que
 * arrancan por «Optimización / Mejora / Implementación / Optimizar» y los repetidos. Una
 * llamada por empresa. Solo cambia el título; los proyectos que copiaron el título de su
 * reto («Título» o «Título (2)») se renombran igual para que sigan cuadrando.
 *
 * Sin --preview ni --commit: dry-run sin coste, solo cuenta los retos afectados.
 */
class VariarTitulosRetos extends Command
{
    protected $signature = 'retos:variar-titulos
                            {--centro=10 : Centro cuyos retos simulados se revisan.}
                            {--empresa= : Ids de empresa separados por coma (solo esas; p. ej. para no tocar retos ya publicados).}
                            {--preview : Llama a la IA y muestra los títulos nuevos, sin escribir en BD.}
                            {--commit : Llama a la IA y guarda los títulos nuevos.}';

    protected $description = 'Reescribe con IA los títulos repetitivos de los retos simulados de un centro (dry-run por defecto).';

    private const ARRANQUES = '/^(Optimizaci|Mejora|Implementaci|Optimizar)/iu';

    public function __construct(private EmpresaFicticiaIAService $ia)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $escribir = (bool) $this->option('commit');
        $retos = Microreto::with('empresa:id,nombre_comercial')
            ->where('es_simulado', true)
            ->whereHas('empresa', fn ($q) => $q->where('centro_id', (int) $this->option('centro')))
            ->when($this->option('empresa'), fn ($q, $ids) => $q->whereIn('empresa_id', array_map('intval', explode(',', (string) $ids))))
            ->get(['id', 'empresa_id', 'titulo', 'subtitulo', 'pregunta_reto']);

        $repetidos = $retos->countBy(fn ($r) => mb_strtolower(trim((string) $r->titulo)))->filter(fn ($n) => $n > 1);
        $afectados = $retos->filter(fn ($r) => preg_match(self::ARRANQUES, (string) $r->titulo)
            || $repetidos->has(mb_strtolower(trim((string) $r->titulo))));

        $this->info("{$afectados->count()} de {$retos->count()} retos con título repetitivo, en {$afectados->groupBy('empresa_id')->count()} empresas.");
        if (!$escribir && !$this->option('preview')) {
            $this->warn('Dry-run: --preview para ver los títulos nuevos sin guardar, --commit para guardarlos.');
            return self::SUCCESS;
        }

        $cambiados = 0;
        $proyectos = 0;
        foreach ($afectados->groupBy('empresa_id') as $empresaId => $grupo) {
            $evitar = $retos->where('empresa_id', $empresaId)->whereNotIn('id', $grupo->pluck('id'))->pluck('titulo')->all();
            $titulos = $this->ia->variarTitulos(
                (string) $grupo->first()?->empresa?->nombre_comercial,
                $grupo->mapWithKeys(fn ($r) => [$r->id => [
                    'titulo' => (string) $r->titulo, 'subtitulo' => (string) $r->subtitulo, 'pregunta' => (string) $r->pregunta_reto,
                ]])->all(),
                $evitar,
            );
            if ($titulos === null) {
                $this->warn("Empresa #{$empresaId}: la IA no devolvió títulos válidos, se salta.");
                continue;
            }

            foreach ($grupo as $reto) {
                $nuevo = $titulos[$reto->id];
                $this->line("#{$reto->id}  {$reto->titulo}  →  {$nuevo}");
                if (!$escribir) continue;

                DB::transaction(function () use ($reto, $nuevo, &$proyectos) {
                    $viejo = (string) $reto->titulo;
                    // Proyectos con el título del reto, o con su sufijo « (n)»
                    Microproyecto::where('microreto_id', $reto->id)
                        ->where(fn ($q) => $q->where('titulo', $viejo)->orWhere('titulo', 'like', addcslashes($viejo, '%_\\') . ' (%)'))
                        ->get()
                        ->each(function (Microproyecto $p) use ($viejo, $nuevo, &$proyectos) {
                            $p->update(['titulo' => $nuevo . mb_substr((string) $p->titulo, mb_strlen($viejo))]);
                            $proyectos++;
                        });
                    $reto->update(['titulo' => $nuevo]);
                });
                $cambiados++;
            }
        }

        $this->info($escribir ? "{$cambiados} retos y {$proyectos} proyectos renombrados." : 'Preview: no se ha escrito nada en BD.');
        return self::SUCCESS;
    }
}
