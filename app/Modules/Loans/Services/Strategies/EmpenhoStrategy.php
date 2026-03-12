<?php

namespace App\Modules\Loans\Services\Strategies;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\EmprestimoService;
use Illuminate\Validation\ValidationException;

/**
 * Estratégia para empréstimos tipo "empenho" (com garantias)
 */
class EmpenhoStrategy implements LoanStrategyInterface
{
    public function gerarEstruturaPagamento(Emprestimo $emprestimo): void
    {
        // Usar método existente do EmprestimoService
        $service = app(EmprestimoService::class);
        $service->gerarParcelasSimples($emprestimo);
    }

    public function validarAntesAprovacao(Emprestimo $emprestimo): void
    {
        // Validar se tem garantias cadastradas
        if (!$emprestimo->temGarantias()) {
            throw ValidationException::withMessages([
                'garantias' => 'Empréstimos do tipo Empenho precisam ter pelo menos uma garantia cadastrada antes de serem aprovados.'
            ]);
        }
    }

    public function calcularValorLiquido(Emprestimo $emprestimo): float
    {
        // Para empenho, valor líquido = valor total (já inclui juros nas parcelas)
        return $emprestimo->valor_total;
    }

    public function podeFinalizar(Emprestimo $emprestimo): bool
    {
        // Finaliza quando todas as parcelas estão pagas ou quitadas por garantia
        return $emprestimo->todasParcelasPagas();
    }
}
