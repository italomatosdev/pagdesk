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
        // Adicionar 'emprestimo_cancelado' ao enum
        DB::statement("ALTER TABLE notificacoes MODIFY COLUMN tipo ENUM(
            'emprestimo_pendente',
            'emprestimo_aprovado',
            'liberacao_disponivel',
            'parcela_vencendo',
            'parcela_atrasada',
            'prestacao_pendente',
            'prestacao_aprovada',
            'prestacao_rejeitada',
            'pagamento_registrado',
            'emprestimo_cancelado'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover 'emprestimo_cancelado' do enum (voltar ao estado anterior)
        DB::statement("ALTER TABLE notificacoes MODIFY COLUMN tipo ENUM(
            'emprestimo_pendente',
            'emprestimo_aprovado',
            'liberacao_disponivel',
            'parcela_vencendo',
            'parcela_atrasada',
            'prestacao_pendente',
            'prestacao_aprovada',
            'prestacao_rejeitada',
            'pagamento_registrado'
        )");
    }
};
