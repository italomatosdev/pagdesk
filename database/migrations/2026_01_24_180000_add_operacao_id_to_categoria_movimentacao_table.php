<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Categorias podem pertencer a uma operação (ou null = compartilhada na empresa).
     */
    public function up(): void
    {
        Schema::table('categoria_movimentacao', function (Blueprint $table) {
            $table->foreignId('operacao_id')->nullable()->after('empresa_id')->constrained('operacoes')->onDelete('cascade');
            $table->index(['operacao_id', 'tipo', 'ativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_movimentacao', function (Blueprint $table) {
            $table->dropForeign(['operacao_id']);
            $table->dropIndex(['operacao_id', 'tipo', 'ativo']);
        });
    }
};
