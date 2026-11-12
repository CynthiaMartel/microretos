<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centros educativos captados (entrevista del comercial o formulario web de centros), a la
 * espera de que DuaLab los valide. Mismos campos que el formulario de centros del frontoffice.
 * Al validarlo se da de alta (o se enlaza con) un centro_educativo, que es lo que usa la app.
 *   estado: pendiente_validacion → validada | descartada
 * Email y teléfono son de una persona: cifrados (cast 'encrypted', columnas text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_captaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('origen_id');
            $table->foreign('origen_id')->references('id')->on('captacion_origenes');
            $table->string('origen_ref', 64)->nullable();
            $table->unique(['origen_id', 'origen_ref']);
            $table->uuid('cliente_uuid')->nullable()->unique();
            $table->foreignId('creada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('pendiente_validacion')->index();
            // registro | info | demo (qué pide el centro, como los botones del formulario web)
            $table->string('accion', 10)->nullable();

            $table->string('centro_nombre', 160);
            $table->string('direccion', 200)->nullable();
            $table->string('provincia', 60)->nullable();
            $table->string('municipio', 120)->nullable();
            $table->json('familias')->nullable(); // nombres de familia (1–4)

            $table->string('contacto_nombre', 120);
            $table->string('contacto_apellidos', 120)->nullable();
            $table->string('contacto_cargo', 120)->nullable();
            $table->text('contacto_email');
            $table->text('contacto_telefono')->nullable();
            $table->text('comentarios')->nullable();

            $table->foreignId('centro_educativo_id')->nullable()->constrained('centro_educativo')->nullOnDelete();
            $table->foreignId('revisada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_at')->nullable();
            $table->string('motivo_descarte', 500)->nullable();
            $table->timestamps();
        });

        // Lo que aceptó el centro (protección de datos y cesiones), con la versión y la huella
        // del texto que se le mostró. Sin firma: igual que el formulario web.
        Schema::create('centro_captacion_consentimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_captacion_id')->constrained('centro_captaciones')->cascadeOnDelete();
            $table->string('clave', 40);
            $table->string('version', 20);
            $table->char('texto_sha256', 64);
            $table->boolean('aceptado');
            $table->timestamp('created_at')->nullable();
            $table->unique(['centro_captacion_id', 'clave'], 'centro_capt_consent_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_captacion_consentimientos');
        Schema::dropIfExists('centro_captaciones');
    }
};
