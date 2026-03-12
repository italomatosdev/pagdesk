<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacao_negociacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->decimal('saldo_devedor', 15, 2);
            $table->json('dados_novo_emprestimo');
            $table->text('motivo');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->text('observacao_aprovador')->nullable();
            $table->foreignId('novo_emprestimo_id')->nullable()->constrained('emprestimos')->onDelete('set null');
            $table->timestamps();

            $table->index(['status', 'operacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_negociacao');
    }
};
