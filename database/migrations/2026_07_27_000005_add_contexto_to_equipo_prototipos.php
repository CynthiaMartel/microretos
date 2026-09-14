<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_prototipos', function (Blueprint $table) {
            // Distingue los archivos subidos como prototipo (F2) de los subidos
            // como entregable final (F3) — misma infraestructura de Cloudinary, distinto uso.
            $table->enum('contexto', ['prototipo', 'entregable'])->default('prototipo')->after('equipo_id');
        });
    }

    public function down(): void
    {
        Schema::table('equipo_prototipos', function (Blueprint $table) {
            $table->dropColumn('contexto');
        });
    }
};
