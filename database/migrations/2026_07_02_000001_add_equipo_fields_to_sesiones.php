<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->tinyInteger('num_equipos')->unsigned()->nullable()->default(3)->after('num_alumnos');
            $table->json('alumnados')->nullable()->after('num_equipos');
            $table->string('codigo_clase', 8)->nullable()->unique()->after('alumnados');
        });
    }

    public function down(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->dropColumn(['num_equipos', 'alumnados', 'codigo_clase']);
        });
    }
};
