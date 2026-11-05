<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas captadas por un comercial en entrevista (app comercial.dualab.es).
 * estado_captacion: null en el resto de empresas; 'borrador' mientras el comercial la
 * rellena, 'pendiente_centro' tras la firma (sin centro, invisible para los centros) y
 * 'asignada' cuando el superadmin la coloca en uno o varios centros (tabla centro_empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('creada_por_user_id')->nullable()->after('centro_id')
                ->constrained('users')->nullOnDelete();
            $table->string('estado_captacion', 20)->nullable()->index()->after('creada_por_user_id');
            $table->timestamp('firmada_at')->nullable()->after('estado_captacion');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creada_por_user_id');
            $table->dropColumn(['estado_captacion', 'firmada_at']);
        });
    }
};
