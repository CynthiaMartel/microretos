<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de retos: DuaLab envía a un docente (con o sin cuenta en la app) un enlace con
 * token para que cuente qué quiere trabajar (familia, ciclo, curso, módulos, RA/CE, su grupo y
 * la sesión). DuaLab elige la empresa, rellena el generador con esas respuestas y genera los
 * retos; los retos guardados desde una solicitud quedan en la recámara (microretos.en_recamara)
 * hasta que el docente se una. Ver SolicitudRetoService.
 *
 * Del token solo se guarda su huella SHA-256, como en el formulario de la entrevista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_reto', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('nombre_docente', 150);
            $table->string('email_docente', 255);
            // Texto: el centro del docente puede no estar dado de alta todavía
            $table->string('centro_nombre', 255);
            $table->foreignId('centro_id')->nullable()->constrained('centro_educativo')->nullOnDelete();

            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('expira_at')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->string('privacidad_version', 30)->nullable();

            // Respuestas del docente (SolicitudRetoService::CAMPOS), validadas por GuardarSolicitudRetoRequest
            $table->json('respuestas')->nullable();
            // Sesión en el aula que propone el docente: sugerencia para el encuentro
            $table->date('sesion_fecha')->nullable();
            $table->time('sesion_hora_inicio')->nullable();
            $table->time('sesion_hora_fin')->nullable();

            // pendiente → respondida | descartada (retos, aviso y encuentro se deducen de sus relaciones)
            $table->string('estado', 20)->default('pendiente')->index();
            // Empresa que elige DuaLab para los retos
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->text('notas')->nullable();

            // Aviso manual «tus retos están listos»: cuándo y qué retos (uuid) se le enviaron
            $table->timestamp('avisado_at')->nullable();
            $table->json('retos_avisados')->nullable();

            $table->foreignId('creada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reto');
    }
};
