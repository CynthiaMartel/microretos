<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->json('fortalezas')->nullable()->change();
            $table->json('puntos_mejora')->nullable()->after('fortalezas');
        });

        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->dropColumn('dafo');
        });
    }

    public function down(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->dropColumn('puntos_mejora');
            $table->json('dafo')->nullable()->after('fortalezas');
            $table->text('fortalezas')->nullable()->change();
        });
    }
};
