<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centros en los que el superadmin coloca una empresa captada por un comercial. La empresa
 * no tiene centro propio (empresas.centro_id = NULL): la ven todos los centros de esta tabla
 * (ver Empresa::scopeDelCentroDe y perteneceAlCentroDe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centro_educativo')->cascadeOnDelete();
            $table->foreignId('asignada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'centro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_empresa');
    }
};
