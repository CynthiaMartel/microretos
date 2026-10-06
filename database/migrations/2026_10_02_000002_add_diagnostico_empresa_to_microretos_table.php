<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia del diagnóstico de la empresa (sector, tamaño, P1–P5) en el momento de guardar
 * el reto. La ficha muestra esta copia, así que editar la empresa después no altera los
 * retos ya creados. Nullable: los retos anteriores siguen leyendo la empresa en directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->json('diagnostico_empresa')->nullable()->after('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->dropColumn('diagnostico_empresa');
        });
    }
};
