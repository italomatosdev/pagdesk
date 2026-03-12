<?php

namespace App\Modules\Loans\Services\Strategies;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\EmprestimoService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Estratégia para empréstimos tipo "dinheiro" (juros simples)
 */
class DinheiroStrategy implements LoanStrategyInterface
{
    public function gerarEstruturaPagamento(Emprestimo $emprestimo): void
    {
        // Usar método existente do EmprestimoService
        $service = app(EmprestimoService::class);
        $service->gerarParcelasSimples($emprestimo);
    }

    public function validarAntesAprovacao(Emprestimo $emprestimo): void
    {
        // Nenhuma validação específica para dinheiro
    }

    public function calcularValorLiquido(Emprestimo $emprestimo): float
    {
        // Para dinheiro, valor líquido = valor total (já inclui juros nas parcelas)
        return $emprestimo->valor_total;
    }

    public function podeFinalizar(Emprestimo $emprestimo): bool
    {
        // Finaliza quando todas as parcelas estão pagas ou quitadas por garantia
        return $emprestimo->todasParcelasPagas();
    }
}
