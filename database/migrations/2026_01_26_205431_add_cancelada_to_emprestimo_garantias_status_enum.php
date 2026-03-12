<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar 'cancelada' ao enum de status
        DB::statement("ALTER TABLE emprestimo_garantias MODIFY COLUMN status ENUM(
            'ativa',
            'liberada',
            'executada',
            'cancelada'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover 'cancelada' do enum (voltar ao estado anterior)
        // Nota: Se houver garantias com status 'cancelada', precisarão ser atualizadas antes
        DB::statement("ALTER TABLE emprestimo_garantias MODIFY COLUMN status ENUM(
            'ativa',
            'liberada',
            'executada'
        )");
    }
};
