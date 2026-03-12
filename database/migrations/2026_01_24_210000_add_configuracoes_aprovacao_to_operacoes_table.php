<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona configurações de aprovação e liberação na operação
     */
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->boolean('requer_aprovacao')->default(true)->after('valor_aprovacao_automatica')->comment('Se requer aprovação manual antes de liberar');
            $table->boolean('requer_liberacao')->default(true)->after('requer_aprovacao')->comment('Se requer liberação do gestor após aprovação');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn(['requer_aprovacao', 'requer_liberacao']);
        });
    }
};
