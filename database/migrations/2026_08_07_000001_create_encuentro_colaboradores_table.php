<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuentro_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuentro_id')->constrained('encuentros')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('puede_editar')->default(false);
            $table->timestamps();

            $table->unique(['encuentro_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuentro_colaboradores');
    }
};
