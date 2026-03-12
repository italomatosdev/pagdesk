<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela pivô: vínculo entre Cliente (global) e Operação
     * Contém limite de crédito por operação
     */
    public function up(): void
    {
        Schema::create('operation_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->decimal('limite_credito', 15, 2)->default(0); // Limite de crédito para esta operação
            $table->enum('status', ['ativo', 'bloqueado'])->default('ativo');
            $table->text('notas_internas')->nullable();
            $table->foreignId('consultor_id')->nullable()->constrained('users')->onDelete('set null'); // Consultor responsável
            $table->timestamps();
            $table->softDeletes();

            // Evitar duplicatas
            $table->unique(['operacao_id', 'cliente_id']);
            
            // Índices para busca
            $table->index('operacao_id');
            $table->index('cliente_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_clients');
    }
};

