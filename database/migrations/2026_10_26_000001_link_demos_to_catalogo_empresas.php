<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La demo del Generador pasa a enseñar una empresa del catálogo DuaLab (empresas.es_catalogo)
 * en lugar de una empresa escrita a mano en esta tabla. La demo guarda solo su configuración
 * (familia, ciclo, curso, módulos, nivel, enfoque); la ficha y el diagnóstico se leen de la
 * plantilla del catálogo, que es la única fuente.
 *
 * Las columnas antiguas de empresa y diagnóstico se vuelven opcionales y dejan de usarse;
 * se borrarán en una migración posterior, cuando no quede ninguna demo antigua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demos', function (Blueprint $table) {
            // Plantilla del catálogo que enseña la demo. Si se borra, la demo deja de ofrecerse.
            $table->foreignId('empresa_id')->nullable()->after('id')
                ->constrained('empresas')->nullOnDelete();
            // familias.id es INT UNSIGNED (increments)
            $table->unsignedInteger('familia_id')->nullable()->after('empresa_id');
            $table->foreign('familia_id')->references('id')->on('familias')->restrictOnDelete();
            // ciclos_formativos.id es INT con signo (tabla creada con SQL en bruto)
            $table->integer('ciclo_id')->nullable()->after('familia_id');
            $table->foreign('ciclo_id')->references('id')->on('ciclos_formativos')->restrictOnDelete();
            // Módulos forzados del Generador (ids de modulos); null = los elige la IA
            $table->json('modulo_ids')->nullable()->after('ciclo_id');
            // Paso 3, opcional: tipo de propuesta (mismo límite que GenerarMicroretoRequest)
            $table->string('enfoque', 500)->nullable()->after('cantidad_microretos');

            // Una demo por familia era una restricción de la versión escrita a mano
            $table->dropUnique(['familia_profesional']);
        });

        Schema::table('demos', function (Blueprint $table) {
            // '1', '2' o 'ambos_cursos', como microretos.curso
            $table->string('curso_seleccionado', 20)->default('2')->change();

            // Columnas antiguas: opcionales para las demos nuevas, que no las rellenan
            $table->string('familia_profesional')->nullable()->change();
            $table->string('etiqueta')->nullable()->change();
            $table->string('empresa_nombre')->nullable()->change();
            $table->string('empresa_sector')->nullable()->change();
            $table->string('empresa_tamano')->nullable()->change();
            $table->text('dia_a_normal')->nullable()->change();
            $table->string('friccion_area')->nullable()->change();
            $table->text('friccion_problema')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sin equivalente en la columna numérica: ambos cursos vuelve a 2º
        DB::table('demos')->where('curso_seleccionado', 'ambos_cursos')->update(['curso_seleccionado' => '2']);

        Schema::table('demos', function (Blueprint $table) {
            $table->unsignedTinyInteger('curso_seleccionado')->default(2)->change();
        });

        Schema::table('demos', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropForeign(['familia_id']);
            $table->dropForeign(['ciclo_id']);
            $table->dropColumn(['empresa_id', 'familia_id', 'ciclo_id', 'modulo_ids', 'enfoque']);
            $table->unique('familia_profesional');
        });
        // Las columnas antiguas se quedan opcionales: volver a NOT NULL fallaría con demos nuevas
    }
};
