<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitações de pagamento com valor inferior ao devido (juros do contrato reduzido).
     * Consultor não pode concluir sozinho; exige aprovação de gestor/admin.
     * Valor sempre >= principal (nunca menor que o emprestado).
     */
    public function up(): void
    {
        Schema::create('solicitacao_pagamento_juros_contrato_reduzido', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parcela_id');
            $table->foreign('parcela_id', 'spjcr_parcela_fk')->references('id')->on('parcelas')->onDelete('cascade');
            $table->unsignedBigInteger('consultor_id');
            $table->foreign('consultor_id', 'spjcr_consultor_fk')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_principal', 15, 2);
            $table->decimal('valor_parcela_total', 15, 2);
            $table->string('metodo', 50);
            $table->date('data_pagamento');
            $table->string('comprovante_path')->nullable();
            $table->text('observacoes')->nullable();
            $table->enum('status', ['aguardando', 'aprovado', 'rejeitado'])->default('aguardando');
            $table->unsignedBigInteger('aprovado_por_id')->nullable();
            $table->foreign('aprovado_por_id', 'spjcr_aprovado_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->unsignedBigInteger('rejeitado_por_id')->nullable();
            $table->foreign('rejeitado_por_id', 'spjcr_rejeitado_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('rejeitado_em')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id', 'spjcr_empresa_fk')->references('id')->on('empresas')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'empresa_id'], 'spjcr_status_empresa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_pagamento_juros_contrato_reduzido');
    }
};
