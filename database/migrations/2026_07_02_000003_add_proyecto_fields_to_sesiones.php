<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->string('proyecto_titulo', 500)->nullable()->after('microreto_titulo');
            $table->string('proyecto_uuid', 36)->nullable()->after('proyecto_titulo');
        });
    }

    public function down(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->dropColumn(['proyecto_titulo', 'proyecto_uuid']);
        });
    }
};
