<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aprovações de empréstimos pendentes
     */
    public function up(): void
    {
        Schema::create('aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->foreignId('aprovado_por')->constrained('users')->onDelete('cascade'); // Administrador que aprovou/rejeitou
            $table->enum('decisao', ['aprovado', 'rejeitado']);
            $table->text('motivo')->nullable(); // Motivo da decisão
            $table->timestamps();

            // Índices
            $table->index('emprestimo_id');
            $table->index('aprovado_por');
            $table->index('decisao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aprovacoes');
    }
};

