<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            // Etapa (ver CLAUDE.md «Etapas»): 'taller' = propuesta del Taller de ideas (aula);
            // 'plan' = plan de proyecto de las prácticas en empresa. String y no enum para poder
            // añadir etapas sin ALTER; valores en Microproyecto::ETAPAS. Los existentes son del taller.
            $table->string('etapa', 20)->default('taller')->after('uuid')->index();
            // Propuesta del taller de la que sale el plan (se amplía). Null: el plan parte de otro reto.
            $table->foreignId('origen_id')->nullable()->after('etapa')
                ->constrained('microproyectos')->nullOnDelete();
            // Datos propios del plan de prácticas
            $table->unsignedSmallInteger('horas_estimadas')->nullable()->after('kpis');
            $table->text('expectativas_empresa')->nullable()->after('horas_estimadas'); // «qué espera la empresa de ti»
        });
    }

    public function down(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origen_id');
            $table->dropIndex(['etapa']);
            $table->dropColumn(['etapa', 'horas_estimadas', 'expectativas_empresa']);
        });
    }
};
