<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// microreto_titulo ya no es obligatorio: la referencia canónica es sesion → proyecto → reto
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->string('microreto_titulo', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->string('microreto_titulo', 500)->nullable(false)->change();
        });
    }
};
