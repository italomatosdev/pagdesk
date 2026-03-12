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
        Schema::table('operacoes', function (Blueprint $table) {
            $table->decimal('valor_aprovacao_automatica', 15, 2)
                  ->nullable()
                  ->after('ativo')
                  ->comment('Valor máximo para aprovação automática de empréstimos. Empréstimos com valor menor ou igual a este valor são aprovados automaticamente, ignorando dívida ativa e limite de crédito.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn('valor_aprovacao_automatica');
        });
    }
};
