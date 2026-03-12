<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cliente é GLOBAL - não tem operacao_id
     * CPF é único e chave principal de busca
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('cpf', 11)->unique(); // CPF sem formatação (apenas números)
            $table->string('nome');
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->text('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índice para busca rápida por CPF
            $table->index('cpf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

