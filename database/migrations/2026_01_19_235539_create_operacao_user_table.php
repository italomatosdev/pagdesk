<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operacao_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Evitar duplicatas
            $table->unique(['operacao_id', 'user_id']);
            
            // Índices para busca
            $table->index('operacao_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacao_user');
    }
};
