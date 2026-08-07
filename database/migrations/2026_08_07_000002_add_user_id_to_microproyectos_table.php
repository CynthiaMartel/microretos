<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete();
        });

        // Backfill best-effort: el docente de un microproyecto existente se infiere del
        // encuentro más antiguo que lo referencia. Los proyectos sin ningún encuentro
        // asociado quedan con user_id null — nadie los ha reclamado todavía.
        DB::statement('
            UPDATE microproyectos m
            JOIN (
                SELECT microproyecto_id, MIN(user_id) AS uid
                FROM encuentros
                WHERE microproyecto_id IS NOT NULL AND user_id IS NOT NULL
                GROUP BY microproyecto_id
            ) e ON e.microproyecto_id = m.id
            SET m.user_id = e.uid
        ');
    }

    public function down(): void
    {
        Schema::table('microproyectos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
