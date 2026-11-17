<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            // Etapa del encuentro: encuentro creativo del Taller de ideas (en el aula) o hito del
            // cronograma del plan de prácticas (en la empresa). String y no enum para poder añadir
            // tipos sin ALTER; valores en Encuentro::TIPOS. Los existentes son todos creativos.
            $table->string('tipo', 30)->default('creativo_etapa1')->after('microproyecto_id')->index();

            // Sesiones del encuentro y fases que se trabajan en cada una: [{ "fases": [0, 1] }, …].
            // Antes vivía en la propuesta (microproyectos.diseno_microproyecto.clases); ahora cada
            // encuentro tiene la suya. La fecha fin estimada se deriva de aquí.
            $table->json('planificacion_sesiones')->nullable()->after('fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn(['tipo', 'planificacion_sesiones']);
        });
    }
};
