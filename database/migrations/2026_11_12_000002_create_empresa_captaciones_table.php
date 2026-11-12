<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas captadas (entrevista del comercial o formulario web) a la espera de que DuaLab las
 * valide. Nada de aquí lo ve un centro: al validarla se crea (o se fusiona con) una fila de
 * `empresas` y se guarda en empresa_id.
 *   estado: borrador (el comercial la está rellenando) → pendiente_validacion (firmada o
 *           recibida) → validada | descartada
 * Mismos tipos de columna que `empresas`, para copiarlas tal cual al validar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_captaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('origen_id');
            $table->foreign('origen_id')->references('id')->on('captacion_origenes');
            // Id del envío en el sistema de origen (p. ej. el formulario web): no se duplica al reenviarlo
            $table->string('origen_ref', 64)->nullable();
            $table->unique(['origen_id', 'origen_ref']);
            // Alta hecha sin conexión en la tablet: al reenviarla no se duplica
            $table->uuid('cliente_uuid')->nullable()->unique();
            $table->foreignId('creada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('borrador')->index();

            $table->string('nombre_comercial');
            $table->string('razon_social')->nullable();
            $table->string('cif')->nullable();
            $table->string('sector')->nullable();
            $table->string('tamano')->nullable();
            $table->string('nivel_digitalizacion', 10)->nullable();
            $table->text('web')->nullable();
            $table->text('actividad')->nullable();
            $table->string('persona_contacto')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email_general')->nullable();
            $table->string('direccion')->nullable();
            $table->text('municipio')->nullable();
            $table->text('provincia')->nullable();
            $table->string('codigo_postal')->nullable();
            // [{ nombre, interes }]: se pasan a empresa_familia al validar
            $table->json('familias')->nullable();

            $table->text('dia_a_normal')->nullable();
            $table->text('friccion_area')->nullable();
            $table->text('friccion_problema')->nullable();
            $table->text('consecuencias')->nullable();
            $table->text('restricciones')->nullable();
            $table->text('lo_que_no_quieren')->nullable();
            $table->text('expectativas_alumno')->nullable();

            $table->timestamp('firmada_at')->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('revisada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
            $table->string('motivo_descarte', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_captaciones');
    }
};
