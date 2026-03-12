<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cliente_dados_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            
            // Dados que podem ser sobrescritos por empresa
            $table->string('nome')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->date('data_nascimento')->nullable();
            
            // Dados do responsável (pessoa jurídica)
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_cpf')->nullable();
            $table->string('responsavel_rg')->nullable();
            $table->string('responsavel_cnh')->nullable();
            $table->string('responsavel_cargo')->nullable();
            
            // Endereço
            $table->string('endereco')->nullable();
            $table->string('numero')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 9)->nullable();
            
            // Observações específicas da empresa
            $table->text('observacoes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Garantir que cada empresa só tem um registro de override por cliente
            $table->unique(['cliente_id', 'empresa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_dados_empresa');
    }
};
