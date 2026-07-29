<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            // La IA ya genera estos dos campos (ver MicroretoIAController::generar()) y la ficha
            // ya intenta pintarlos (MicroretoModal.vue), pero al no existir como columna se
            // perdían en silencio al guardar — Eloquent descarta claves fuera de $fillable/BD.
            $table->string('subtitulo', 500)->nullable()->after('titulo');
            $table->json('variantes')->nullable()->after('tips_profesorado');
        });
    }

    public function down(): void
    {
        Schema::table('microretos', function (Blueprint $table) {
            $table->dropColumn(['subtitulo', 'variantes']);
        });
    }
};
