<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE microproyecto_recursos
            MODIFY COLUMN tipo ENUM('video', 'documento', 'imagen') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM microproyecto_recursos WHERE tipo = 'imagen'");
        DB::statement("ALTER TABLE microproyecto_recursos
            MODIFY COLUMN tipo ENUM('video', 'documento') NOT NULL");
    }
};
