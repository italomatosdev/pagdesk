<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Documentos KYC (Know Your Customer) dos clientes
     */
    public function up(): void
    {
        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('tipo'); // rg, cnh, comprovante_residencia, etc
            $table->string('numero')->nullable();
            $table->string('orgao_emissor')->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->string('arquivo_path')->nullable(); // Caminho do arquivo anexado
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_documents');
    }
};

