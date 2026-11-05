<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma del empresario entrevistado y consentimientos aceptados (aviso legal, información
 * RGPD y una cesión por cada empresa cesionaria). Registro de evidencia: solo se inserta,
 * nunca se edita. snapshot guarda los textos aceptados y los datos firmados; documento_sha256
 * es su huella, para poder demostrar que no han cambiado.
 * Si la empresa se purga, la firma se conserva (empresa_id → NULL) con su snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_firmas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('comercial_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('firmante_nombre', 255);
            $table->text('firmante_dni');            // cifrado (cast encrypted)
            $table->string('firmante_cargo', 255)->nullable();
            $table->text('firmante_email')->nullable(); // cifrado (cast encrypted)
            $table->string('firma_path', 255);        // PNG en el disco privado 'local'
            $table->json('snapshot');
            $table->char('documento_sha256', 64);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('firmado_at');
            $table->timestamps();
        });

        Schema::create('empresa_consentimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_firma_id')->constrained('empresa_firmas')->cascadeOnDelete();
            // aviso_legal, info_rgpd o la clave de la cesión (config/captacion.php)
            $table->string('clave', 50);
            $table->string('version', 20);
            $table->char('texto_sha256', 64);
            $table->boolean('aceptado');
            $table->timestamps();

            $table->unique(['empresa_firma_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_consentimientos');
        Schema::dropIfExists('empresa_firmas');
    }
};
