<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de empresas ficticias de DuaLab (T2): plantillas sin centro (`centro_id` nulo),
 * de solo lectura, que cada centro copia con «Usar en mi centro». `copiada_de_id` enlaza
 * la copia con su plantilla para reutilizarla en vez de duplicarla dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('es_catalogo')->default(false)->after('es_simulada')->index();
            $table->foreignId('copiada_de_id')->nullable()->after('es_catalogo')
                ->constrained('empresas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copiada_de_id');
            $table->dropColumn('es_catalogo');
        });
    }
};
