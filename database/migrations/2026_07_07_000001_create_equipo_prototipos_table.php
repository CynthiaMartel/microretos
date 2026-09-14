<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_prototipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->string('filename');
            $table->text('url');
            $table->string('public_id')->unique();
            $table->string('resource_type', 20);   // video, image, raw
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('label', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_prototipos');
    }
};
