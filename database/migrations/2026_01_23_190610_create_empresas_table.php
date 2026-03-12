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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('razao_social')->nullable();
            $table->string('cnpj', 14)->unique()->nullable(); // CNPJ sem formatação (apenas números)
            $table->string('email_contato')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->enum('status', ['ativa', 'suspensa', 'cancelada'])->default('ativa');
            $table->enum('plano', ['basico', 'profissional', 'enterprise'])->default('basico');
            $table->date('data_ativacao')->nullable();
            $table->date('data_expiracao')->nullable();
            $table->json('configuracoes')->nullable(); // Setup flexível por empresa
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('status');
            $table->index('plano');
            $table->index('cnpj');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
