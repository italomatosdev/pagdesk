<?php

namespace App\Modules\Loans\Services\Strategies;

use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Validation\ValidationException;

/**
 * Estratégia para empréstimos tipo "troca_cheque"
 * 
 * Diferente dos outros tipos:
 * - Não gera parcelas (cliente já recebeu o dinheiro)
 * - Gera agendamentos de depósito (cheques)
 * - Valor líquido = soma dos cheques - juros calculados
 */
class TrocaChequeStrategy implements LoanStrategyInterface
{
    public function gerarEstruturaPagamento(Emprestimo $emprestimo): void
    {
        // Troca de cheque NÃO gera parcelas
        // Cheques são cadastrados separadamente pelo usuário
        // Cada cheque representa um agendamento de depósito
    }

    public function validarAntesAprovacao(Emprestimo $emprestimo): void
    {
        // Validar se tem cheques cadastrados
        if (!$emprestimo->temCheques()) {
            throw ValidationException::withMessages([
                'cheques' => 'Troca de cheque precisa ter pelo menos um cheque cadastrado antes de ser aprovada.'
            ]);
        }

        // Validar se todos os cheques têm data de vencimento futura
        $chequesVencidos = $emprestimo->cheques()
            ->where('data_vencimento', '<', now()->startOfDay())
            ->count();

        if ($chequesVencidos > 0) {
            throw ValidationException::withMessages([
                'cheques' => 'Não é possível aprovar troca de cheque com cheques vencidos.'
            ]);
        }
    }

    public function calcularValorLiquido(Emprestimo $emprestimo): float
    {
        // Carregar cheques se ainda não estiverem carregados
        if (!$emprestimo->relationLoaded('cheques')) {
            $emprestimo->load('cheques');
        }

        // Se não tem cheques cadastrados ainda, retorna 0
        if ($emprestimo->cheques->isEmpty()) {
            return 0;
        }

        // Valor líquido = soma dos valores dos cheques - soma dos juros
        $totalCheques = $emprestimo->cheques->sum('valor_cheque');
        $totalJuros = $emprestimo->cheques->sum('valor_juros');

        return round($totalCheques - $totalJuros, 2);
    }

    public function podeFinalizar(Emprestimo $emprestimo): bool
    {
        // Carregar cheques se ainda não estiverem carregados
        if (!$emprestimo->relationLoaded('cheques')) {
            $emprestimo->load('cheques');
        }

        // Finaliza quando todos os cheques estão compensados
        $totalCheques = $emprestimo->cheques->count();
        
        if ($totalCheques === 0) {
            return false;
        }

        $chequesCompensados = $emprestimo->cheques
            ->where('status', 'compensado')
            ->count();

        return $chequesCompensados === $totalCheques;
    }
}
