<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Solicitações de quitação com desconto (exigem aprovação de gestor/administrador).
     */
    public function up(): void
    {
        Schema::create('solicitacoes_quitacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->foreignId('solicitante_id')->constrained('users')->onDelete('cascade');
            $table->decimal('saldo_devedor', 15, 2)->comment('Saldo devedor no momento da solicitação');
            $table->decimal('valor_solicitado', 15, 2)->comment('Valor que o cliente vai pagar (pode ser menor = desconto)');
            $table->string('metodo', 50)->default('dinheiro');
            $table->date('data_pagamento');
            $table->string('comprovante_path')->nullable();
            $table->text('observacoes')->nullable();
            $table->text('motivo_desconto')->nullable()->comment('Obrigatório quando valor_solicitado < saldo_devedor');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('cascade');
            $table->timestamps();

            $table->index('emprestimo_id');
            $table->index('solicitante_id');
            $table->index('status');
            $table->index('empresa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_quitacao');
    }
};
