<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Torna consultor_id nullable para permitir movimentações do caixa da operação
     * (sem estar vinculado a um gestor ou consultor específico)
     */
    public function up(): void
    {
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            // Remover a constraint de foreign key existente
            $table->dropForeign(['consultor_id']);
        });

        // Alterar a coluna para nullable
        DB::statement('ALTER TABLE cash_ledger_entries MODIFY consultor_id BIGINT UNSIGNED NULL');

        // Recriar a foreign key como nullable
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->foreign('consultor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Antes de tornar obrigatório, precisamos garantir que não há NULLs
        DB::statement('UPDATE cash_ledger_entries SET consultor_id = 1 WHERE consultor_id IS NULL');

        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['consultor_id']);
        });

        // Tornar obrigatório novamente
        DB::statement('ALTER TABLE cash_ledger_entries MODIFY consultor_id BIGINT UNSIGNED NOT NULL');

        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->foreign('consultor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
};
