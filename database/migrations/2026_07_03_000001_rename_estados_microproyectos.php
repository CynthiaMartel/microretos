<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar el enum para que acepte tanto los valores viejos como los nuevos
        //    (MySQL no permite eliminar valores del enum si hay filas con ese valor)
        DB::statement("ALTER TABLE microproyectos
            MODIFY COLUMN estado ENUM(
                'borrador', 'publicado', 'archivado',
                'en_edicion', 'propuesta', 'validado'
            ) NOT NULL DEFAULT 'en_edicion'");

        // 2. Backfill: convertir valores viejos a nuevos
        DB::statement("UPDATE microproyectos SET estado = 'en_edicion' WHERE estado = 'borrador'");
        DB::statement("UPDATE microproyectos SET estado = 'validado'   WHERE estado = 'publicado' AND empresa_validado = 1");
        DB::statement("UPDATE microproyectos SET estado = 'propuesta'  WHERE estado = 'publicado' AND empresa_validado = 0");

        // 3. Cerrar el enum solo con los valores nuevos
        DB::statement("ALTER TABLE microproyectos
            MODIFY COLUMN estado ENUM(
                'en_edicion', 'propuesta', 'validado', 'archivado'
            ) NOT NULL DEFAULT 'en_edicion'");
    }

    public function down(): void
    {
        // Revertir a enum original
        DB::statement("ALTER TABLE microproyectos
            MODIFY COLUMN estado ENUM(
                'en_edicion', 'propuesta', 'validado', 'archivado',
                'borrador', 'publicado'
            ) NOT NULL DEFAULT 'en_edicion'");

        DB::statement("UPDATE microproyectos SET estado = 'borrador'  WHERE estado = 'en_edicion'");
        DB::statement("UPDATE microproyectos SET estado = 'publicado' WHERE estado = 'propuesta'");
        DB::statement("UPDATE microproyectos SET estado = 'publicado' WHERE estado = 'validado'");

        DB::statement("ALTER TABLE microproyectos
            MODIFY COLUMN estado ENUM(
                'borrador', 'publicado', 'archivado'
            ) NOT NULL DEFAULT 'borrador'");
    }
};
