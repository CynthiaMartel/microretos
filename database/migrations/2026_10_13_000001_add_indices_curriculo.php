<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de la cadena ciclo → módulo → RA → CE. Las tablas vienen del dump SQL original
 * sin índices en estas columnas (el `ce_idmoduloRA_index` de la migración de creación nunca
 * llegó a existir, ver 2026_07_10_000001). Sin ellos, comprobar qué familias tienen módulos
 * con RA y CE (Familia::scopeConDatosParaRetos, catálogo DuaLab) recorre las tablas enteras.
 * Solo crea el índice si la columna no tiene ya alguno, para ser segura en cualquier entorno.
 */
return new class extends Migration
{
    private const INDICES = [
        'modulos'                => ['idcicloformativo', 'modulos_idcicloformativo_index'],
        'resultados_aprendizaje' => ['idmodulo', 'ra_idmodulo_index'],
        'criterios_evaluacion'   => ['idresultadoaprendizaje', 'ce_idresultadoaprendizaje_index'],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => [$columna, $nombre]) {
            if (!$this->columnaIndexada($tabla, $columna)) {
                Schema::table($tabla, fn (Blueprint $table) => $table->index($columna, $nombre));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => [, $nombre]) {
            if (!empty(DB::select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = ?", [$nombre]))) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropIndex($nombre));
            }
        }
    }

    private function columnaIndexada(string $tabla, string $columna): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `{$tabla}` WHERE Column_name = ? AND Seq_in_index = 1", [$columna]));
    }
};
