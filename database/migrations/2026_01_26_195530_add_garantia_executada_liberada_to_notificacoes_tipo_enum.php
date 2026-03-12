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
        // Adicionar 'garantia_executada' e 'garantia_liberada' ao enum
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
            'emprestimo_cancelado',
            'garantia_executada',
            'garantia_liberada'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover 'garantia_executada' e 'garantia_liberada' do enum (voltar ao estado anterior)
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
};
