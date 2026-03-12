<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela para armazenar anexos das garantias (fotos, documentos)
     */
    public function up(): void
    {
        Schema::create('emprestimo_garantia_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->constrained('emprestimo_garantias')->onDelete('cascade');
            $table->string('nome_arquivo'); // Nome original do arquivo
            $table->string('caminho'); // Path no storage
            $table->enum('tipo', ['imagem', 'documento'])->default('documento');
            $table->integer('tamanho')->nullable(); // Tamanho em bytes
            $table->timestamps();

            // Índices
            $table->index('garantia_id');
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprestimo_garantia_anexos');
    }
};
