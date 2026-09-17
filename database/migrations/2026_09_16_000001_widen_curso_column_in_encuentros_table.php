<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// encuentros.curso era varchar(10), demasiado corto para el valor 'ambos_cursos' (11
// caracteres) que ya usan microretos.curso (varchar(20)) y microproyectos.curso
// (varchar(255)) para los retos "Ambos Cursos" — cualquier encuentro creado a partir de
// uno de esos proyectos fallaba con "Data too long for column 'curso'". Se amplía al
// mismo tamaño que microretos.curso por consistencia.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->string('curso', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->string('curso', 10)->nullable()->change();
        });
    }
};
