<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_tareas', function (Blueprint $table) {
            // Tareas que el equipo no puede borrar (p.ej. QA interno) — se aplican en el seed genérico.
            $table->boolean('obligatoria')->default(false)->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('equipo_tareas', function (Blueprint $table) {
            $table->dropColumn('obligatoria');
        });
    }
};
