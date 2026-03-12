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
        Schema::create('forma_pagamento_venda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->onDelete('cascade');
            $table->string('forma', 20);
            $table->decimal('valor', 15, 2)->default(0);
            $table->unsignedInteger('numero_parcelas')->nullable();
            $table->foreignId('emprestimo_id')->nullable()->constrained('emprestimos')->onDelete('set null');
            $table->timestamps();
            $table->index('venda_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forma_pagamento_venda');
    }
};
