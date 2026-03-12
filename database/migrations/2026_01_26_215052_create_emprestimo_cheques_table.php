<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela para armazenar cheques de empréstimos tipo "troca_cheque"
     */
    public function up(): void
    {
        Schema::create('emprestimo_cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->string('banco', 100); // Nome do banco
            $table->string('agencia', 20); // Agência
            $table->string('conta', 20); // Conta
            $table->string('numero_cheque', 50); // Número do cheque
            $table->date('data_vencimento'); // Data que o cheque deve ser depositado
            $table->decimal('valor_cheque', 15, 2); // Valor de face do cheque
            $table->integer('dias_ate_vencimento')->default(0); // Dias até vencimento (calculado)
            $table->decimal('taxa_juros', 5, 2)->default(0); // Taxa de juros aplicada (%)
            $table->decimal('valor_juros', 15, 2)->default(0); // Juros calculados para este cheque
            $table->decimal('valor_liquido', 15, 2)->default(0); // valor_cheque - valor_juros
            $table->string('portador', 255)->nullable(); // Nome do portador do cheque
            $table->enum('status', [
                'aguardando',    // Aguardando data de vencimento
                'depositado',    // Foi depositado no banco
                'compensado',    // Compensou com sucesso
                'devolvido',     // Foi devolvido (sem fundos, etc.)
                'cancelado'      // Cancelado
            ])->default('aguardando');
            $table->dateTime('data_deposito')->nullable(); // Quando foi depositado
            $table->dateTime('data_compensacao')->nullable(); // Quando compensou
            $table->dateTime('data_devolucao')->nullable(); // Quando foi devolvido
            $table->text('motivo_devolucao')->nullable(); // Motivo da devolução
            $table->text('observacoes')->nullable();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('emprestimo_id');
            $table->index('data_vencimento');
            $table->index('status');
            $table->index(['data_vencimento', 'status']); // Para buscar cheques a depositar
            $table->unique(['numero_cheque', 'banco', 'agencia', 'conta']); // Evitar cheques duplicados
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprestimo_cheques');
    }
};
