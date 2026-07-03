<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microproyecto_id')->constrained('microproyectos')->cascadeOnDelete();
            $table->string('nombre');                         // "Grupo A", "Grupo B"...
            $table->string('token', 40)->unique();            // token largo para URL directa
            $table->string('codigo_acceso', 8)->unique();     // código corto tipo Kahoot: "XKM-479"
            $table->tinyInteger('fase_actual')->default(0);   // fase en la que se encuentra el equipo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
