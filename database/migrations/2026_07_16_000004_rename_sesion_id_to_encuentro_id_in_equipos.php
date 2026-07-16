<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nivel 3 del rename sesión → encuentro. `equipos.sesion_id` es FK hacia
// `encuentros.id`. Además, Equipo::encuentro() (belongsTo sin FK explícita)
// asume por convención la columna `encuentro_id` — hasta esta migración esa
// relación apuntaba a una columna inexistente; esto la deja funcional.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('equipos', 'sesion_id') && !Schema::hasColumn('equipos', 'encuentro_id')) {
            Schema::table('equipos', function (Blueprint $table) {
                $table->renameColumn('sesion_id', 'encuentro_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('equipos', 'encuentro_id') && !Schema::hasColumn('equipos', 'sesion_id')) {
            Schema::table('equipos', function (Blueprint $table) {
                $table->renameColumn('encuentro_id', 'sesion_id');
            });
        }
    }
};
