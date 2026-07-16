<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Empresa;
use App\Models\Microreto;
use App\Models\CicloFormativo;
use App\Models\Familia;
use App\Models\CentroEducativo;
use App\Models\Microproyecto;
use App\Models\Encuentro;
use App\Models\User;

class PurgarPapelera extends Command
{
    protected $signature = 'papelera:purgar
                            {--dias= : Días de retención (sobreescribe PAPELERA_DIAS_RETENCION)}
                            {--dry-run : Muestra qué se eliminaría sin ejecutar el borrado}';

    protected $description = 'Elimina permanentemente los elementos que llevan más de X días en la papelera';

    private array $modelos = [
        'empresas'   => Empresa::class,
        'microretos' => Microreto::class,
        'ciclos'     => CicloFormativo::class,
        'familias'   => Familia::class,
        'centros'    => CentroEducativo::class,
        'proyectos'  => Microproyecto::class,
        'sesiones'   => Encuentro::class,
        'usuarios'   => User::class,
    ];

    public function handle(): int
    {
        $dias    = (int) ($this->option('dias') ?? config('papelera.dias_retencion', 30));
        $dryRun  = $this->option('dry-run');
        $limite  = Carbon::now()->subDays($dias);

        $this->info(
            ($dryRun ? '[DRY-RUN] ' : '') .
            "Purga de papelera: eliminando elementos con más de {$dias} días (antes de {$limite->toDateString()})"
        );

        $totalEliminados = 0;

        foreach ($this->modelos as $tipo => $clase) {
            $items = $clase::onlyTrashed()
                ->where('deleted_at', '<=', $limite)
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            $this->line("  [{$tipo}] Encontrados: {$items->count()}");

            if (!$dryRun) {
                foreach ($items as $item) {
                    $this->limpiarRelaciones($item, $tipo);
                    $item->forceDelete();
                }
            }

            $totalEliminados += $items->count();
        }

        $this->info(
            ($dryRun ? '[DRY-RUN] ' : '') .
            "Total: {$totalEliminados} elemento(s)" . ($dryRun ? ' serían eliminados.' : ' eliminados permanentemente.')
        );

        if (!$dryRun) {
            Log::info("PurgarPapelera: {$totalEliminados} elementos eliminados (retención: {$dias} días)");
        }

        return Command::SUCCESS;
    }

    private function limpiarRelaciones(mixed $item, string $tipo): void
    {
        match ($tipo) {
            'empresas'  => $this->limpiarEmpresa($item),
            'centros'   => $this->limpiarCentro($item),
            'proyectos' => $this->limpiarProyecto($item),
            // 'sesiones' (Encuentro) no necesita limpieza manual: equipos.encuentro_id
            // ya tiene ON DELETE SET NULL, y microproyectos ya no apunta a encuentros
            // (ver 2026_07_16_000003_drop_sesion_id_from_microproyectos).
            'usuarios'  => $this->limpiarUsuario($item),
            default     => null,
        };
    }

    private function limpiarEmpresa(Empresa $empresa): void
    {
        DB::table('empresa_familia')->where('empresa_id', $empresa->id)->delete();
        DB::table('microretos')->where('empresa_id', $empresa->id)->update(['empresa_id' => null]);
    }

    private function limpiarCentro(CentroEducativo $centro): void
    {
        Empresa::withTrashed()->where('centro_id', $centro->id)->update([
            'centro_id'        => null,
            'centro_educativo' => null,
        ]);
        DB::table('centro_ciclo')->where('centro_id', $centro->id)->delete();
    }

    private function limpiarProyecto(Microproyecto $proyecto): void
    {
        // MicroproyectoRecurso no usa SoftDeletes: delete() es borrado permanente
        $proyecto->recursos()->delete();
    }

    private function limpiarUsuario(User $user): void
    {
        // Elimina los tokens Sanctum para no dejar huérfanos en personal_access_tokens
        $user->tokens()->delete();
    }
}
