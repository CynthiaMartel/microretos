<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            // Eliminar FK y campos legacy/desnormalizados
            $table->dropForeign(['microreto_id']);
            $table->dropColumn(['microreto_id', 'microreto_titulo', 'proyecto_titulo', 'proyecto_uuid']);
        });

        Schema::table('sesiones', function (Blueprint $table) {
            // Añadir FK correcta: sesion → microproyecto
            $table->foreignId('microproyecto_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('microproyectos')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->dropForeign(['microproyecto_id']);
            $table->dropColumn('microproyecto_id');
        });

        Schema::table('sesiones', function (Blueprint $table) {
            $table->unsignedBigInteger('microreto_id')->nullable()->after('user_id');
            $table->string('microreto_titulo', 500)->nullable()->after('microreto_id');
            $table->string('proyecto_titulo', 500)->nullable()->after('microreto_titulo');
            $table->string('proyecto_uuid', 36)->nullable()->after('proyecto_titulo');
            $table->foreign('microreto_id')->references('id')->on('microretos')->nullOnDelete();
        });
    }
};
