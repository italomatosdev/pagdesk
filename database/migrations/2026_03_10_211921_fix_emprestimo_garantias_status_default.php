<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Corrigir garantias existentes com status NULL ou vazio para 'ativa'
        DB::table('emprestimo_garantias')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'ativa']);

        // Restaurar o DEFAULT 'ativa' que foi perdido na migration anterior
        DB::statement("ALTER TABLE emprestimo_garantias MODIFY COLUMN status ENUM(
            'ativa',
            'liberada',
            'executada',
            'cancelada'
        ) DEFAULT 'ativa'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove o default (volta ao estado quebrado - apenas para rollback)
        DB::statement("ALTER TABLE emprestimo_garantias MODIFY COLUMN status ENUM(
            'ativa',
            'liberada',
            'executada',
            'cancelada'
        )");
    }
};
