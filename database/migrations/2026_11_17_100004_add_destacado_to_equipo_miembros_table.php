<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            // Selección para prácticas (P3b): DuaLab, como empresa intermediaria, marca desde DuaLab
            // Central al alumnado más destacado (por alias). Null = no destacado. Lo ve el docente del
            // encuentro, nunca el propio alumnado; los datos reales se piden al docente fuera de la app.
            $table->timestamp('destacado_en')->nullable()->after('puntos_mejora');
            $table->foreignId('destacado_por_id')->nullable()->after('destacado_en')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destacado_por_id');
            $table->dropColumn('destacado_en');
        });
    }
};
