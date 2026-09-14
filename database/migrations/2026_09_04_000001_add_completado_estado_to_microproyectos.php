<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE microproyectos
            MODIFY COLUMN estado ENUM(
                'en_edicion', 'propuesta', 'validado', 'completado', 'archivado'
            ) NOT NULL DEFAULT 'en_edicion'");
    }

    public function down(): void
    {
        // No hay backfill que revertir: 'completado' no existía antes, así que si hay
        // filas con ese valor al hacer rollback, MySQL las rechazaría al reducir el enum.
        // Se dejan como 'validado' (estado inmediatamente anterior en el flujo).
        DB::statement("UPDATE microproyectos SET estado = 'validado' WHERE estado = 'completado'");

        DB::statement("ALTER TABLE microproyectos
            MODIFY COLUMN estado ENUM(
                'en_edicion', 'propuesta', 'validado', 'archivado'
            ) NOT NULL DEFAULT 'en_edicion'");
    }
};
