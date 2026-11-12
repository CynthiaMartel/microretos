<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La captación de empresas deja de vivir en `empresas` (estado_captacion, 2026_11_05_000001)
 * y pasa a empresa_captaciones: la firma y los retos en borrador cuelgan ahora de la
 * captación, y `empresas` solo guarda empresas ya validadas por DuaLab.
 * Requiere que no haya datos de captación (los de prueba se borraron el 12/11/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_firmas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            // Evidencia legal: no se puede borrar la captación mientras tenga firma
            $table->foreignId('empresa_captacion_id')->after('uuid')->constrained('empresa_captaciones')->restrictOnDelete();
        });

        Schema::table('microreto_borradores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            $table->foreignId('empresa_captacion_id')->after('uuid')->constrained('empresa_captaciones')->cascadeOnDelete();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creada_por_user_id');
            $table->dropIndex(['estado_captacion']);
            $table->dropColumn(['estado_captacion', 'firmada_at']);
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('creada_por_user_id')->nullable()->after('centro_id')->constrained('users')->nullOnDelete();
            $table->string('estado_captacion', 20)->nullable()->index()->after('creada_por_user_id');
            $table->timestamp('firmada_at')->nullable()->after('estado_captacion');
        });

        Schema::table('microreto_borradores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_captacion_id');
            $table->foreignId('empresa_id')->after('uuid')->constrained('empresas')->cascadeOnDelete();
        });

        Schema::table('empresa_firmas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_captacion_id');
            $table->foreignId('empresa_id')->nullable()->after('uuid')->constrained('empresas')->nullOnDelete();
        });
    }
};
