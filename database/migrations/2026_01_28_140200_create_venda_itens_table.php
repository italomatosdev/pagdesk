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
        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->onDelete('cascade');
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->onDelete('set null');
            $table->string('descricao')->nullable();
            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('preco_unitario_vista', 15, 2)->default(0);
            $table->decimal('preco_unitario_crediario', 15, 2)->default(0);
            $table->decimal('subtotal_vista', 15, 2)->default(0);
            $table->decimal('subtotal_crediario', 15, 2)->default(0);
            $table->timestamps();
            $table->index('venda_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venda_itens');
    }
};
