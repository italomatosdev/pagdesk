<?php

namespace App\Modules\Loans\Services\Strategies;

use App\Modules\Loans\Models\Emprestimo;

/**
 * Factory para criar a estratégia correta baseada no tipo do empréstimo
 */
class LoanStrategyFactory
{
    /**
     * Criar estratégia baseada no tipo do empréstimo
     *
     * @param Emprestimo|string $emprestimoOuTipo Empréstimo ou string com o tipo
     * @return LoanStrategyInterface
     */
    public static function create(Emprestimo|string $emprestimoOuTipo): LoanStrategyInterface
    {
        $tipo = $emprestimoOuTipo instanceof Emprestimo 
            ? $emprestimoOuTipo->tipo 
            : $emprestimoOuTipo;

        return match($tipo) {
            'dinheiro' => new DinheiroStrategy(),
            'price' => new PriceStrategy(),
            'empenho' => new EmpenhoStrategy(),
            'troca_cheque' => new TrocaChequeStrategy(),
            'crediario' => new DinheiroStrategy(), // Crediário gera parcelas como dinheiro (valor fixo)
            default => new DinheiroStrategy(), // Fallback
        };
    }
}
