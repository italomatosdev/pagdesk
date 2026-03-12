<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Categorias de entrada e despesa para movimentações de caixa.
     */
    public function up(): void
    {
        Schema::create('categoria_movimentacao', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->enum('tipo', ['entrada', 'despesa']);
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'tipo', 'ativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categoria_movimentacao');
    }
};
