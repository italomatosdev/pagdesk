<?php

namespace App\Modules\Loans\Services\Strategies;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\EmprestimoService;
use Illuminate\Validation\ValidationException;

/**
 * Estratégia para empréstimos tipo "price" (sistema de amortização)
 */
class PriceStrategy implements LoanStrategyInterface
{
    public function gerarEstruturaPagamento(Emprestimo $emprestimo): void
    {
        // Usar método existente do EmprestimoService
        $service = app(EmprestimoService::class);
        $service->gerarParcelasPrice($emprestimo);
    }

    public function validarAntesAprovacao(Emprestimo $emprestimo): void
    {
        // Validar se tem taxa de juros (obrigatória para Price)
        if (empty($emprestimo->taxa_juros) || $emprestimo->taxa_juros <= 0) {
            throw ValidationException::withMessages([
                'taxa_juros' => 'A taxa de juros é obrigatória para empréstimos do tipo Price.'
            ]);
        }
    }

    public function calcularValorLiquido(Emprestimo $emprestimo): float
    {
        // Para Price, valor líquido = valor total (já inclui juros nas parcelas)
        return $emprestimo->valor_total;
    }

    public function podeFinalizar(Emprestimo $emprestimo): bool
    {
        // Finaliza quando todas as parcelas estão pagas ou quitadas por garantia
        return $emprestimo->todasParcelasPagas();
    }
}
