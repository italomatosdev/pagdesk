<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela para rastrear liberação de dinheiro do gestor para o consultor
     */
    public function up(): void
    {
        Schema::create('emprestimo_liberacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade'); // Consultor que receberá
            $table->foreignId('gestor_id')->nullable()->constrained('users')->onDelete('set null'); // Gestor que liberou
            $table->decimal('valor_liberado', 15, 2); // Valor liberado (pode ser diferente do valor_total se houver taxas)
            $table->enum('status', ['aguardando', 'liberado', 'pago_ao_cliente'])->default('aguardando');
            $table->timestamp('liberado_em')->nullable(); // Quando o gestor liberou
            $table->timestamp('pago_ao_cliente_em')->nullable(); // Quando o consultor pagou ao cliente
            $table->text('observacoes_liberacao')->nullable(); // Observações do gestor
            $table->text('observacoes_pagamento')->nullable(); // Observações do consultor ao pagar
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('emprestimo_id');
            $table->index('consultor_id');
            $table->index('gestor_id');
            $table->index('status');
            $table->index('liberado_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprestimo_liberacoes');
    }
};
