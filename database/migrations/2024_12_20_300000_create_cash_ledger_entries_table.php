<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Movimentações de caixa do consultor
     */
    public function up(): void
    {
        Schema::create('cash_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pagamento_id')->nullable()->constrained('pagamentos')->onDelete('set null'); // Se originou de um pagamento
            $table->enum('tipo', ['entrada', 'saida']); // Entrada ou saída
            $table->decimal('valor', 15, 2);
            $table->string('descricao');
            $table->text('observacoes')->nullable();
            $table->date('data_movimentacao');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('operacao_id');
            $table->index('consultor_id');
            $table->index('data_movimentacao');
            $table->index(['consultor_id', 'data_movimentacao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_ledger_entries');
    }
};

