<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('microproyectos', 'codigo_clase')) {
            Schema::table('microproyectos', function (Blueprint $table) {
                $table->dropUnique(['codigo_clase']);
                $table->dropColumn('codigo_clase');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('microproyectos', 'codigo_clase')) {
            Schema::table('microproyectos', function (Blueprint $table) {
                $table->string('codigo_clase', 8)->nullable()->unique()->after('token_empresa');
            });
        }
    }
};
