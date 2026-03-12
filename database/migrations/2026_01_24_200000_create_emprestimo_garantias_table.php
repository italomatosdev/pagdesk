<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela para armazenar garantias de empréstimos do tipo empenho
     */
    public function up(): void
    {
        Schema::create('emprestimo_garantias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
            $table->enum('categoria', ['imovel', 'veiculo', 'outros'])->default('outros');
            $table->string('descricao'); // Descrição do bem
            $table->decimal('valor_avaliado', 15, 2)->nullable(); // Valor estimado do bem
            $table->string('localizacao')->nullable(); // Onde o bem está
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('emprestimo_id');
            $table->index('categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprestimo_garantias');
    }
};
