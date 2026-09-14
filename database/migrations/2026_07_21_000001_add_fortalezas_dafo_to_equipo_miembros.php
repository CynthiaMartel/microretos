<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->text('fortalezas')->nullable()->after('rol');
            $table->json('dafo')->nullable()->after('fortalezas');
        });
    }

    public function down(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->dropColumn(['fortalezas', 'dafo']);
        });
    }
};
