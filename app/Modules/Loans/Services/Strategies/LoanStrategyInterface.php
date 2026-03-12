<?php

namespace App\Modules\Loans\Services\Strategies;

use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Validation\ValidationException;

/**
 * Interface para estratégias de diferentes tipos de empréstimo
 * 
 * Cada tipo de empréstimo (dinheiro, price, empenho, troca_cheque) 
 * implementa esta interface para definir seu comportamento específico
 */
interface LoanStrategyInterface
{
    /**
     * Gerar estrutura de pagamento (parcelas, cheques, etc.)
     * Chamado após criar o empréstimo
     *
     * @param Emprestimo $emprestimo
     * @return void
     */
    public function gerarEstruturaPagamento(Emprestimo $emprestimo): void;

    /**
     * Validar condições específicas antes de aprovar
     * Ex: empenho precisa de garantias, troca_cheque precisa de cheques
     *
     * @param Emprestimo $emprestimo
     * @return void
     * @throws ValidationException
     */
    public function validarAntesAprovacao(Emprestimo $emprestimo): void;

    /**
     * Calcular valor líquido a ser pago ao cliente
     * Ex: troca_cheque = valor cheques - juros
     *
     * @param Emprestimo $emprestimo
     * @return float
     */
    public function calcularValorLiquido(Emprestimo $emprestimo): float;

    /**
     * Verificar se pode finalizar o empréstimo
     * Ex: todas parcelas pagas OU todos cheques compensados
     *
     * @param Emprestimo $emprestimo
     * @return bool
     */
    public function podeFinalizar(Emprestimo $emprestimo): bool;
}
