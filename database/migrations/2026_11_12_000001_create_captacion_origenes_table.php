<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Por dónde llega una captación (empresa_captaciones, centro_captaciones). Tabla y no
 * enum para poder añadir vías nuevas (feria, importación…) sin tocar las de captaciones.
 * Los ids son fijos: el código los usa (App\Models\CaptacionOrigen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacion_origenes', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('clave', 30)->unique();
            $table->string('nombre', 80);
        });

        DB::table('captacion_origenes')->insert([
            ['id' => 1, 'clave' => 'entrevista', 'nombre' => 'Entrevista del comercial'],
            ['id' => 2, 'clave' => 'formulario', 'nombre' => 'Formulario web'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('captacion_origenes');
    }
};
