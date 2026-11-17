<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia el calendario de sesiones de cada propuesta (diseno_microproyecto.clases) a cada uno
 * de sus encuentros, para que los encuentros ya creados no pierdan su planificación al pasar
 * a leerla del encuentro. Solo rellena encuentros sin planificación: es idempotente.
 * Autorizado por Cynthia el 2026-11-17 (regla de BD de CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('encuentros')
            ->whereNull('planificacion_sesiones')
            ->whereNotNull('microproyecto_id')
            ->orderBy('id')
            ->select(['id', 'microproyecto_id'])
            ->chunkById(200, function ($encuentros) {
                $disenos = DB::table('microproyectos')
                    ->whereIn('id', $encuentros->pluck('microproyecto_id')->unique())
                    ->pluck('diseno_microproyecto', 'id');

                foreach ($encuentros as $encuentro) {
                    $sesiones = $this->sesionesDe($disenos[$encuentro->microproyecto_id] ?? null);
                    if ($sesiones === null) {
                        continue;
                    }
                    DB::table('encuentros')->where('id', $encuentro->id)
                        ->update(['planificacion_sesiones' => json_encode($sesiones)]);
                }
            });
    }

    // Solo la estructura que usa la app: [{ fases: [int 0–4] }]. Null si la propuesta no tenía.
    private function sesionesDe(?string $disenoJson): ?array
    {
        $clases = json_decode($disenoJson ?? '', true)['clases'] ?? null;
        if (!is_array($clases) || $clases === []) {
            return null;
        }

        return array_values(array_map(fn ($c) => [
            'fases' => array_values(array_filter(
                array_map('intval', is_array($c['fases'] ?? null) ? $c['fases'] : []),
                fn ($f) => $f >= 0 && $f <= 4,
            )),
        ], $clases));
    }

    // Sin vuelta atrás que hacer: la columna la quita la migración anterior en su down() y la
    // propuesta conserva su diseno_microproyecto intacto.
    public function down(): void
    {
    }
};
