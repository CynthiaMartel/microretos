<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            // Identificador numérico estable del equipo dentro de su encuentro (1, 2, 3...),
            // independiente del texto libre en "nombre" (que el docente podría renombrar).
            // Necesario para poder reestructurar el reparto de alumnado de forma fiable.
            $table->tinyInteger('numero_equipo')->unsigned()->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('numero_equipo');
        });
    }
};
