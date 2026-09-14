<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->foreignId('imagen_portada_id')
                  ->nullable()
                  ->after('equipo')
                  ->constrained('microproyecto_recursos')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('imagen_portada_id');
        });
    }
};
