<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices compostos para as queries mais usadas (dashboard e listagens).
     * Melhora performance de filtros por operacao_id+status, empresa_id+status, status+data.
     */
    public function up(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->index(['operacao_id', 'status']);
        });

        Schema::table('parcelas', function (Blueprint $table) {
            $table->index(['empresa_id', 'status']);
        });

        Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
            $table->index(['status', 'liberado_em']);
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->index(['consultor_id', 'data_pagamento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emprestimos', function (Blueprint $table) {
            $table->dropIndex(['operacao_id', 'status']);
        });

        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'status']);
        });

        Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
            $table->dropIndex(['status', 'liberado_em']);
        });

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropIndex(['consultor_id', 'data_pagamento']);
        });
    }
};
