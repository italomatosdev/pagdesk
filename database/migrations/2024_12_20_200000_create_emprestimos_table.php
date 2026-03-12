<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Empréstimos em dinheiro
     */
    public function up(): void
    {
        Schema::create('emprestimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade'); // Consultor responsável
            $table->decimal('valor_total', 15, 2); // Valor total do empréstimo
            $table->integer('numero_parcelas'); // Número de parcelas
            $table->enum('frequencia', ['diaria', 'semanal', 'mensal']); // Frequência das parcelas
            $table->date('data_inicio'); // Data de início do empréstimo
            $table->decimal('taxa_juros', 5, 2)->default(0); // Taxa de juros (opcional)
            $table->enum('status', ['draft', 'pendente', 'aprovado', 'ativo', 'finalizado', 'cancelado'])->default('draft');
            $table->text('observacoes')->nullable();
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->onDelete('set null'); // Quem aprovou
            $table->timestamp('aprovado_em')->nullable(); // Quando foi aprovado
            $table->text('motivo_rejeicao')->nullable(); // Se foi rejeitado
            $table->timestamps();
            $table->softDeletes();

            // Índices para busca
            $table->index('operacao_id');
            $table->index('cliente_id');
            $table->index('consultor_id');
            $table->index('status');
            $table->index('data_inicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprestimos');
    }
};

