<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fotos e anexos (documentos) do produto.
     */
    public function up(): void
    {
        if (Schema::hasTable('produto_anexos')) {
            return;
        }
        Schema::create('produto_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->string('nome_arquivo');
            $table->string('caminho');
            $table->enum('tipo', ['imagem', 'documento'])->default('documento');
            $table->unsignedInteger('ordem')->default(0);
            $table->integer('tamanho')->nullable();
            $table->timestamps();

            $table->index(['produto_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produto_anexos');
    }
};
