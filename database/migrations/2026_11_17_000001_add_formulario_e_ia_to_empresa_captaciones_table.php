<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App comercial:
 *  - Formulario público de la entrevista: si no da tiempo a hacerla en persona, el comercial
 *    envía a la empresa un enlace con las mismas preguntas. Del token solo se guarda su huella
 *    (SHA-256); el enlace en claro solo lo ven el correo y el comercial al crearlo.
 *  - respuestas_ia: preguntas cuya respuesta partió de una sugerencia de la IA que la empresa
 *    confirmó en la entrevista (para que DuaLab lo sepa al validarla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_captaciones', function (Blueprint $table) {
            $table->string('formulario_token_hash', 64)->nullable()->unique()->after('notas');
            $table->timestamp('formulario_expira_at')->nullable()->after('formulario_token_hash');
            $table->timestamp('formulario_enviado_at')->nullable()->after('formulario_expira_at');
            $table->timestamp('formulario_respondido_at')->nullable()->after('formulario_enviado_at');
            // Versión de la información de protección de datos que aceptó la empresa al enviarlo
            $table->string('formulario_privacidad_version', 30)->nullable()->after('formulario_respondido_at');
            $table->json('respuestas_ia')->nullable()->after('formulario_privacidad_version');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_captaciones', function (Blueprint $table) {
            $table->dropUnique(['formulario_token_hash']);
            $table->dropColumn([
                'formulario_token_hash', 'formulario_expira_at', 'formulario_enviado_at',
                'formulario_respondido_at', 'formulario_privacidad_version', 'respuestas_ia',
            ]);
        });
    }
};
