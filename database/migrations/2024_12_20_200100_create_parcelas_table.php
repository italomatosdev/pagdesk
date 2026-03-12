<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Parcelas dos empréstimos
     */
    public function up(): void
    {
        Schema::create('parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->integer('numero'); // Número da parcela (1, 2, 3...)
            $table->decimal('valor', 15, 2); // Valor da parcela
            $table->decimal('valor_pago', 15, 2)->default(0); // Valor já pago (suporta pagamento parcial no futuro)
            $table->date('data_vencimento'); // Data de vencimento
            $table->date('data_pagamento')->nullable(); // Data em que foi paga
            $table->enum('status', ['pendente', 'paga', 'atrasada', 'cancelada'])->default('pendente');
            $table->integer('dias_atraso')->default(0); // Dias de atraso (calculado)
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices para busca
            $table->index('emprestimo_id');
            $table->index('data_vencimento');
            $table->index('status');
            $table->index(['data_vencimento', 'status']); // Para "Cobranças do Dia"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcelas');
    }
};

