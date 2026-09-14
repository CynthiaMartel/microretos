<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // La tabla ya existía en la BD (creada fuera de control de versiones) con este
    // mismo esquema — se versiona aquí para que un despliegue limpio (producción)
    // también la tenga. SESSION_DRIVER=database la necesita para las sesiones de
    // Sanctum stateful y para poder invalidar sesiones de un usuario por user_id.
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
