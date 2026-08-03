<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            // Código que el docente genera para desbloquear los botones "Sugerir con IA"
            // del workspace de equipo — mismo formato que codigo_clase (ABC-123).
            $table->string('codigo_ia', 8)->nullable()->after('codigo_clase');
        });
    }

    public function down(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->dropColumn('codigo_ia');
        });
    }
};
