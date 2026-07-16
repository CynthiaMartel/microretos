<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Nivel 3 del rename sesión → encuentro (ver conversación de renombrado de
// nomenclatura). El modelo Eloquent, el controller y las rutas ya usan
// "Encuentro" desde los Niveles 1-2; esta migración alinea el nombre de la
// tabla física con esa nomenclatura. MySQL actualiza automáticamente los FKs
// de `microproyectos.sesion_id` y `equipos.sesion_id` que referencian esta
// tabla al renombrarla (InnoDB resuelve las FKs internamente, no por nombre).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sesiones') && !Schema::hasTable('encuentros')) {
            Schema::rename('sesiones', 'encuentros');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('encuentros') && !Schema::hasTable('sesiones')) {
            Schema::rename('encuentros', 'sesiones');
        }
    }
};
