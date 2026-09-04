<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('nombre');
        });

        // El nombre real se cifrará en BD (ver AlumnadoCifrarNombres). El payload cifrado de
        // Laravel (iv + valor + mac, en base64) supera con facilidad los 255 caracteres de un
        // string para nombres largos, así que se amplía la columna antes de cifrar nada.
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->text('nombre')->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->dropColumn('alias');
            $table->string('nombre')->change();
        });
    }
};
