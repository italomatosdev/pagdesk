<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dados cadastrais do cliente no contexto de uma operação (uma linha por par cliente + operação).
     * Identidade (documento) permanece em clientes.
     */
    public function up(): void
    {
        Schema::create('operacao_dados_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('operacao_id')->constrained('operacoes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();

            $table->string('nome');
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_cpf', 14)->nullable();
            $table->string('responsavel_rg', 20)->nullable();
            $table->string('responsavel_cnh', 20)->nullable();
            $table->string('responsavel_cargo', 100)->nullable();
            $table->text('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->unique(['cliente_id', 'operacao_id']);
            $table->index('operacao_id');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacao_dados_clientes');
    }
};
