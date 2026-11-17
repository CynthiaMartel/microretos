<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entrevista enviada por correo sin hacerla en persona: el comercial solo sabe el email de la
 * empresa, que rellena ella misma su nombre y demás datos desde el enlace. Hasta entonces la
 * captación no tiene nombre (sigue en borrador: firmar exige nombre, sector y familias).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_captaciones', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Las captaciones sin nombre (formulario aún sin rellenar) se quedan con el email
        DB::table('empresa_captaciones')->whereNull('nombre_comercial')
            ->update(['nombre_comercial' => DB::raw("COALESCE(email_general, 'Sin nombre')")]);
        Schema::table('empresa_captaciones', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable(false)->change();
        });
    }
};
