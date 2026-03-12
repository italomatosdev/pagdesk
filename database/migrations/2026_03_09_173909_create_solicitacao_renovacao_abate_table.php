<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitações de renovação com abate quando valor pago é inferior ao principal.
     */
    public function up(): void
    {
        Schema::create('solicitacao_renovacao_abate', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parcela_id');
            $table->foreign('parcela_id', 'sra_parcela_fk')->references('id')->on('parcelas')->onDelete('cascade');
            $table->unsignedBigInteger('consultor_id');
            $table->foreign('consultor_id', 'sra_consultor_fk')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_principal', 15, 2);
            $table->decimal('valor_parcela_total', 15, 2);
            $table->string('metodo', 50);
            $table->date('data_pagamento');
            $table->string('comprovante_path')->nullable();
            $table->text('observacoes')->nullable();
            $table->enum('status', ['aguardando', 'aprovado', 'rejeitado'])->default('aguardando');
            $table->unsignedBigInteger('aprovado_por_id')->nullable();
            $table->foreign('aprovado_por_id', 'sra_aprovado_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('aprovado_em')->nullable();
            $table->unsignedBigInteger('rejeitado_por_id')->nullable();
            $table->foreign('rejeitado_por_id', 'sra_rejeitado_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('rejeitado_em')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id', 'sra_empresa_fk')->references('id')->on('empresas')->onDelete('cascade');
            $table->timestamps();
            $table->index(['status', 'empresa_id'], 'sra_status_empresa_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacao_renovacao_abate');
    }
};
