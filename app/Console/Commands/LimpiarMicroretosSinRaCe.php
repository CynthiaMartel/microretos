<?php

namespace App\Console\Commands;

use App\Models\Microproyecto;
use App\Models\Microreto;
use Illuminate\Console\Command;

/**
 * Borra (soft-delete, recuperable desde la papelera) los microretos listados en
 * storage/app/microretos_a_borrar.csv — la lista exacta de microretos con RA/CE sin
 * resolver que RepararRaCeMicroretos (dry-run) ya no puede arreglar porque su módulo
 * ni siquiera está importado en el catálogo.
 *
 * Decisión (Cynthia, 2026-09-09): más barato borrar y regenerar con el flujo actual
 * (usa el catálogo real desde el commit 6af381a) que reparar caso a caso.
 *
 * Los microproyectos que ya existan sobre estos microretos NO se borran (la FK
 * microproyectos.microreto_id es nullOnDelete) — quedan huérfanos pero intactos.
 *
 * Dry-run por defecto: solo con --commit hace el soft-delete.
 */
class LimpiarMicroretosSinRaCe extends Command
{
    protected $signature = 'microretos:limpiar-sin-ra-ce
                            {--commit : Aplica el borrado (soft-delete). Sin esta opción es un dry-run.}
                            {--archivo=microretos_a_borrar.csv : Nombre del CSV en storage/app con la columna microreto_id.}';

    protected $description = 'Soft-delete de los microretos listados en el CSV revisado (RA/CE sin resolver, módulo fuera del catálogo).';

    public function handle(): int
    {
        $archivo = $this->option('archivo');
        $ruta = storage_path('app/' . $archivo);

        if (!file_exists($ruta)) {
            $this->error("No encuentro {$ruta}.");
            return self::FAILURE;
        }

        $filas = array_map('str_getcsv', file($ruta));
        $cabecera = array_shift($filas);
        $idxId = array_search('microreto_id', $cabecera);

        if ($idxId === false) {
            $this->error('El CSV no tiene columna microreto_id.');
            return self::FAILURE;
        }

        $ids = array_map(fn($fila) => (int) $fila[$idxId], $filas);

        $microretos = Microreto::whereIn('id', $ids)->get();
        if ($microretos->isEmpty()) {
            $this->info('Ninguno de esos ids sigue activo (¿ya se borraron?). Nada que hacer.');
            return self::SUCCESS;
        }

        $porEmpresa = $microretos->groupBy(fn($m) => $m->empresa->nombre_comercial ?? $m->empresa_nombre ?? 'SIN EMPRESA');
        foreach ($porEmpresa->sortByDesc(fn($items) => $items->count()) as $empresa => $items) {
            $this->line(sprintf('%3d  %s', $items->count(), $empresa));
        }

        $idsActivos = $microretos->pluck('id')->all();
        $conProyecto = Microproyecto::whereIn('microreto_id', $idsActivos)->count();

        $this->newLine();
        $verbo = $this->option('commit') ? 'se han borrado' : 'se borrarían';
        $this->warn(count($idsActivos) . " microretos {$verbo} ({$conProyecto} microproyecto(s) quedarán huérfanos de reto, pero no se borran).");

        if ($this->option('commit')) {
            $borrados = Microreto::whereIn('id', $idsActivos)->delete();
            $this->info("Hecho: {$borrados} microretos con soft-delete. Recuperables desde la papelera.");
        } else {
            $this->warn('Dry-run: relanza con --commit para aplicar el borrado.');
        }

        return self::SUCCESS;
    }
}
