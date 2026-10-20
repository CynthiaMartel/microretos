<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Protección de datos — fase 3: el alumnado solo existe por su alias
 * ("NombreDePila Animal Color"), así que equipo_miembros.nombre (nombre real completo) ya
 * no se usa. Se elimina la columna.
 *
 * Guarda: si queda algún nombre sin vaciar, la migración se detiene sin tocar nada — hay
 * que pasar antes `alumnado:anonimizar --commit` (commit a48363d) en ese entorno.
 * down() recrea la columna vacía: los nombres no se recuperan (ni deben).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('equipo_miembros', 'nombre')) {
            return;
        }

        $conNombre = DB::table('equipo_miembros')->whereNotNull('nombre')->where('nombre', '!=', '')->count();
        if ($conNombre > 0) {
            throw new RuntimeException(
                "Quedan {$conNombre} miembros con nombre real: ejecuta antes `php artisan alumnado:anonimizar --commit` (commit a48363d)."
            );
        }

        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('equipo_miembros', 'nombre')) {
            Schema::table('equipo_miembros', function (Blueprint $table) {
                $table->text('nombre')->nullable()->after('equipo_id');
            });
        }
    }
};
