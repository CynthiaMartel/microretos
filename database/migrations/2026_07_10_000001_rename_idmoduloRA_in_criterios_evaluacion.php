<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// `idmoduloRA` es un nombre heredado de la importación original del dump SQL
// (ver 2024_01_01_000006_create_criterios_evaluacion_table.php). Pese a su nombre,
// nunca fue FK a `modulos`: siempre apuntó a `resultados_aprendizaje.id`
// (ver CriterioEvaluacion::resultadoAprendizaje()). Se renombra para reflejar
// la relación real y evitar la confusión de futuras lecturas del esquema.
//
// El índice `ce_idmoduloRA_index` que declaraba la migración original de creación
// nunca llegó a existir realmente en la tabla — de ahí las comprobaciones antes
// de renombrarlo, para que esta migración sea segura en cualquier entorno.
return new class extends Migration
{
    private const INDICE_ANTIGUO = 'ce_idmoduloRA_index';
    private const INDICE_NUEVO   = 'ce_idresultadoaprendizaje_index';

    public function up(): void
    {
        if (Schema::hasColumn('criterios_evaluacion', 'idmoduloRA')) {
            Schema::table('criterios_evaluacion', function (Blueprint $table) {
                $table->renameColumn('idmoduloRA', 'idresultadoaprendizaje');
            });
        }

        if ($this->indiceExiste(self::INDICE_ANTIGUO)) {
            Schema::table('criterios_evaluacion', function (Blueprint $table) {
                $table->renameIndex(self::INDICE_ANTIGUO, self::INDICE_NUEVO);
            });
        }
    }

    public function down(): void
    {
        if ($this->indiceExiste(self::INDICE_NUEVO)) {
            Schema::table('criterios_evaluacion', function (Blueprint $table) {
                $table->renameIndex(self::INDICE_NUEVO, self::INDICE_ANTIGUO);
            });
        }

        if (Schema::hasColumn('criterios_evaluacion', 'idresultadoaprendizaje')) {
            Schema::table('criterios_evaluacion', function (Blueprint $table) {
                $table->renameColumn('idresultadoaprendizaje', 'idmoduloRA');
            });
        }
    }

    private function indiceExiste(string $nombre): bool
    {
        return !empty(DB::select('SHOW INDEX FROM criterios_evaluacion WHERE Key_name = ?', [$nombre]));
    }
};
