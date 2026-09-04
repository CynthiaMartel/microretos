<?php

namespace App\Console\Commands;

use App\Models\EquipoMiembro;
use App\Support\AliasGenerator;
use Illuminate\Console\Command;

/**
 * Rellena el alias ("Nombre Adjetivo") de los miembros de equipo dados de alta ANTES
 * de que existiera esta columna. Los miembros nuevos ya reciben alias automáticamente
 * (ver EquipoMiembro::booted()); este comando es solo el backfill de los históricos.
 *
 * Dry-run por defecto: solo con --commit escribe en BD (regla del proyecto: ninguna
 * mutación de datos existentes sin confirmación explícita).
 */
class GenerarAliasAlumnado extends Command
{
    protected $signature = 'alumnado:generar-alias
                            {--commit : Guarda los alias generados en BD. Sin esta opción es un dry-run.}';

    protected $description = 'Genera el alias de exhibición para los miembros de equipo que aún no tienen uno.';

    public function handle(): int
    {
        $pendientes = EquipoMiembro::whereNull('alias')->orWhere('alias', '')->get()->groupBy('equipo_id');

        if ($pendientes->isEmpty()) {
            $this->info('No hay miembros sin alias. Nada que hacer.');
            return self::SUCCESS;
        }

        $commit = $this->option('commit');
        $total = 0;

        foreach ($pendientes as $equipoId => $miembros) {
            foreach ($miembros->values() as $posicion => $miembro) {
                $alias = AliasGenerator::generar($miembro->nombre, $posicion);
                $this->line("Equipo {$equipoId} · miembro {$miembro->id}: \"{$miembro->nombre}\" -> \"{$alias}\"");

                if ($commit) {
                    $miembro->alias = $alias;
                    $miembro->save();
                }
                $total++;
            }
        }

        if (!$commit) {
            $this->warn("Dry-run: {$total} miembros recibirían alias. Ejecuta con --commit para guardarlo.");
        } else {
            $this->info("Hecho: {$total} miembros actualizados con su alias.");
        }

        return self::SUCCESS;
    }
}
