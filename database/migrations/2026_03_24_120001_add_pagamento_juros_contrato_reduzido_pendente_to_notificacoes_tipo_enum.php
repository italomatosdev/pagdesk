<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
            'garantia_liberada',
            'pagamento_produto_objeto_pendente',
            'pagamento_juros_parcial_pendente',
            'pagamento_juros_contrato_reduzido_pendente'
        )");
    }

    public function down(): void
    {
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
            'garantia_liberada',
            'pagamento_produto_objeto_pendente',
            'pagamento_juros_parcial_pendente'
        )");
    }
};
