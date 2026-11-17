<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Horas de prácticas (A3): las anotan el docente y DuaLab (superadmin) en la reunión con el
        // tutor de empresa; las ve el propio alumnado en su workspace. Por alias (equipo_miembros).
        Schema::create('registros_horas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuentro_id')->constrained('encuentros')->cascadeOnDelete();   // cronograma del plan
            $table->foreignId('equipo_miembro_id')->constrained('equipo_miembros')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('horas', 5, 2);
            $table->string('descripcion', 500)->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipo_miembro_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_horas');
    }
};
