<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Itens de produto/objeto recebidos em um pagamento (1 pagamento = N itens).
     */
    public function up(): void
    {
        Schema::create('pagamento_produto_objeto_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagamento_id')->constrained('pagamentos')->onDelete('cascade');
            $table->string('nome', 255);
            $table->text('descricao')->nullable();
            $table->decimal('valor_estimado', 15, 2)->nullable();
            $table->unsignedInteger('quantidade')->default(1);
            $table->json('imagens')->nullable()->comment('Paths das imagens deste item');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index('pagamento_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamento_produto_objeto_itens');
    }
};
