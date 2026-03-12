<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitações de pagamento com juros abaixo do devido (consultor → aprovação gestor/admin).
     */
    public function up(): void
    {
        Schema::create('solicitacao_pagamento_juros_parcial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcela_id')->constrained('parcelas')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade');
            $table->decimal('valor', 15, 2);
            $table->string('metodo', 50);
            $table->date('data_pagamento');
            $table->string('comprovante_path')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tipo_juros', 50)->nullable();
            $table->decimal('taxa_juros_aplicada', 5, 2)->nullable();
            $table->decimal('valor_juros_solicitado', 15, 2)->default(0);
            $table->decimal('valor_juros_devido', 15, 2)->default(0);
            $table->enum('status', ['aguardando', 'aprovado', 'rejeitado'])->default('aguardando');
            $table->foreignId('aprovado_por_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->foreignId('rejeitado_por_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejeitado_em')->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'empresa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_pagamento_juros_parcial');
    }
};
