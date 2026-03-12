<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pagamentos recebidos (registro de recebimento)
     */
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcela_id')->constrained('parcelas')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade'); // Quem recebeu
            $table->decimal('valor', 15, 2); // Valor do pagamento
            $table->enum('metodo', ['dinheiro', 'pix', 'transferencia', 'outro'])->default('dinheiro');
            $table->date('data_pagamento'); // Data do pagamento
            $table->string('comprovante_path')->nullable(); // Caminho do comprovante (se houver)
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('parcela_id');
            $table->index('consultor_id');
            $table->index('data_pagamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};

