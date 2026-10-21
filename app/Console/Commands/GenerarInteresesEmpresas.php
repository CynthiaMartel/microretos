<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\EmpresaFamiliaService;
use App\Services\EmpresaFicticiaIAService;
use Illuminate\Console\Command;

/**
 * Rellena con IA el interés de las empresas FICTICIAS (de centro y plantillas del catálogo)
 * en las familias a las que están vinculadas y que aún no lo tienen. Las reales no: su interés
 * es información que debe dar la empresa (lo recoge DUALAB).
 *
 * Sin opciones: solo lista lo pendiente, sin llamar a la IA ni escribir.
 * --muestra=N: pide a la IA las propuestas de N empresas y las enseña, sin guardar.
 * --commit:    genera y guarda (una llamada a la IA por empresa).
 */
class GenerarInteresesEmpresas extends Command
{
    protected $signature = 'empresas:generar-intereses
                            {--commit : Genera y guarda. Sin esta opción no escribe nada.}
                            {--muestra=0 : Sin --commit, pide a la IA las propuestas de N empresas y las muestra.}
                            {--empresa= : Solo esta empresa (id).}';

    protected $description = 'Genera con IA el interés por familia de las empresas ficticias que no lo tienen (sin --commit no escribe).';

    public function handle(EmpresaFicticiaIAService $ia, EmpresaFamiliaService $familias): int
    {
        $pendientes = $this->pendientes();
        if ($pendientes->isEmpty()) {
            $this->info('No hay empresas ficticias con familias sin interés.');
            return self::SUCCESS;
        }

        $this->table(['Id', 'Empresa', 'Catálogo', 'Familias sin interés'], $pendientes->map(fn ($p) => [
            $p['empresa']->id,
            $p['empresa']->nombre_comercial,
            $p['empresa']->es_catalogo ? 'sí' : '',
            implode(', ', $p['familias']),
        ])->all());
        $this->info($pendientes->count() . ' empresas y ' . $pendientes->sum(fn ($p) => count($p['familias'])) . ' familias sin interés.');

        $commit  = (bool) $this->option('commit');
        $muestra = max(0, (int) $this->option('muestra'));
        if (!$commit && $muestra === 0) {
            $this->warn('Sin cambios. Usa --muestra=N para ver propuestas de la IA, o --commit para generarlas y guardarlas.');
            return self::SUCCESS;
        }

        $lote = $commit ? $pendientes : $pendientes->take($muestra);
        $guardados = 0;
        $fallos = 0;
        foreach ($lote as $p) {
            $empresa = $p['empresa'];
            $intereses = $ia->generarIntereses($empresa, $p['familias']);
            if ($intereses === null) {
                $fallos++;
                $this->error("#{$empresa->id} {$empresa->nombre_comercial}: la IA no respondió.");
                continue;
            }
            $this->line("<options=bold>#{$empresa->id} {$empresa->nombre_comercial}</>");
            foreach ($intereses as $familia => $texto) {
                $this->line("  {$familia}: {$texto}");
            }
            if ($commit && $intereses) {
                // guardarIntereses solo toca familias vinculadas; ya se filtró que no tuvieran interés
                $familias->guardarIntereses($empresa->id, $intereses);
                $guardados += count($intereses);
            }
        }

        if ($commit) {
            $this->info("Hecho: {$guardados} intereses guardados" . ($fallos ? ", {$fallos} empresas sin respuesta de la IA." : '.'));
        } else {
            $this->warn('Muestra: no se ha guardado nada. Relanza con --commit para generar y guardar todas.');
        }

        return $fallos && $commit ? self::FAILURE : self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, array{empresa: Empresa, familias: non-empty-array<int, mixed>}>  familias: nombres sin interés */
    private function pendientes(): \Illuminate\Support\Collection
    {
        // Ficticias activas: rondan las 160 en total (sin paginar), se cargan con sus familias
        return Empresa::query()
            ->where('es_simulada', true)
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey((int) $id))
            ->with('familias:id,nombre')
            ->orderBy('id')
            ->take(1000)
            ->get()
            ->map(fn (Empresa $e) => [
                'empresa'  => $e,
                'familias' => $e->familias
                    ->filter(fn ($f) => trim((string) $f->pivot?->getAttribute('interes')) === '')
                    ->pluck('nombre')->values()->all(),
            ])
            ->filter(fn ($p) => $p['familias'] !== [])
            ->values();
    }
}
