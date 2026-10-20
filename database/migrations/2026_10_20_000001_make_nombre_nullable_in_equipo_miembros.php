<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protección de datos: los miembros nuevos solo tienen alias, sin nombre real, así que
 * equipo_miembros.nombre pasa a admitir NULL. No toca ningún dato: los nombres antiguos
 * se vacían después con `alumnado:anonimizar` y la columna se elimina en una migración
 * posterior, una vez comprobado todo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->text('nombre')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->text('nombre')->nullable(false)->change();
        });
    }
};
