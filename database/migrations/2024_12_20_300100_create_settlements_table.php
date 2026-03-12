<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Prestação de contas (settlements) dos consultores
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->onDelete('cascade');
            $table->foreignId('consultor_id')->constrained('users')->onDelete('cascade');
            $table->date('data_inicio'); // Período da prestação
            $table->date('data_fim');
            $table->decimal('valor_total', 15, 2); // Valor total da prestação
            $table->enum('status', ['pendente', 'conferido', 'validado', 'rejeitado'])->default('pendente');
            $table->foreignId('conferido_por')->nullable()->constrained('users')->onDelete('set null'); // Gestor que conferiu
            $table->timestamp('conferido_em')->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->onDelete('set null'); // Admin que validou
            $table->timestamp('validado_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('operacao_id');
            $table->index('consultor_id');
            $table->index('status');
            $table->index(['consultor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};

