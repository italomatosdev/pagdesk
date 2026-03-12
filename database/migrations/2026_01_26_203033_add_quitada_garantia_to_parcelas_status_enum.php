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
        // Adicionar 'quitada_garantia' ao enum de status
        DB::statement("ALTER TABLE parcelas MODIFY COLUMN status ENUM(
            'pendente',
            'paga',
            'atrasada',
            'cancelada',
            'quitada_garantia'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover 'quitada_garantia' do enum (voltar ao estado anterior)
        // Nota: Se houver parcelas com status 'quitada_garantia', precisarão ser atualizadas antes
        DB::statement("ALTER TABLE parcelas MODIFY COLUMN status ENUM(
            'pendente',
            'paga',
            'atrasada',
            'cancelada'
        )");
    }
};
