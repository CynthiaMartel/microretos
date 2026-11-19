<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de DuaLab Central: qué se hizo, quién y cuándo (validar o descartar empresas captadas,
 * papelera, cuentas de usuario, solicitudes de retos, retos generados y publicados…). Como el registro
 * de actividad de Gestión web. Se guarda el nombre y el rol de quien lo hizo (si la cuenta se borra,
 * el historial sigue diciendo quién fue); nunca correos ni contraseñas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_central', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre', 255);
            $table->string('usuario_rol', 50);
            $table->string('seccion', 40)->index();     // captaciones, papelera, usuarios, solicitudes, retos
            $table->string('accion', 60);
            $table->string('descripcion', 500);
            $table->string('entidad_tipo', 40)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->boolean('critica')->default(false)->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_central');
    }
};
