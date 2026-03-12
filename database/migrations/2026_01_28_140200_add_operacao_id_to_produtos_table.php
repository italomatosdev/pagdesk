<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Produtos passam a pertencer a uma operação.
     */
    public function up(): void
    {
        if (Schema::hasColumn('produtos', 'operacao_id')) {
            return;
        }
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('operacao_id')->nullable()->after('empresa_id')->constrained('operacoes')->onDelete('cascade');
            $table->index('operacao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['operacao_id']);
            $table->dropIndex(['operacao_id']);
        });
    }
};
