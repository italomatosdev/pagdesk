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
            'pagamento_juros_contrato_reduzido_pendente',
            'renovacao_abate_valor_inferior_pendente',
            'renovacao_abate_aprovada',
            'renovacao_abate_rejeitada',
            'quitacao_desconto_pendente',
            'quitacao_desconto_aprovada',
            'quitacao_desconto_rejeitada',
            'negociacao_pendente',
            'negociacao_aprovada',
            'negociacao_rejeitada',
            'emprestimo_retroativo_aguardando_aceite'
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
            'pagamento_juros_parcial_pendente',
            'pagamento_juros_contrato_reduzido_pendente',
            'renovacao_abate_valor_inferior_pendente',
            'quitacao_desconto_pendente',
            'quitacao_desconto_aprovada',
            'quitacao_desconto_rejeitada',
            'negociacao_pendente',
            'negociacao_aprovada',
            'negociacao_rejeitada',
            'emprestimo_retroativo_aguardando_aceite'
        )");
    }
};
