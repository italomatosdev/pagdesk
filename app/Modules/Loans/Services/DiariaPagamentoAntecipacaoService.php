<?php

namespace App\Modules\Loans\Services;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Diária com N parcelas: antecipação em cascata (fim do cronograma) sem sobra de valor.
 */
class DiariaPagamentoAntecipacaoService
{
    public function __construct(
        protected PagamentoService $pagamentoService
    ) {}

    public function valorDevidoNoPagamento(Parcela $parcela, array $dadosValidados): float
    {
        $parcela->loadMissing('emprestimo.operacao');
        $falta = max(0, (float) $parcela->valor - (float) $parcela->valor_pago);
        $juros = (float) ($this->pagamentoService->getDadosJuros($parcela, $dadosValidados)['valor_juros'] ?? 0);

        return round($falta + $juros, 2);
    }

    public function calcularMaximoPermitido(Parcela $parcela, array $dadosValidados): float
    {
        $parcela->loadMissing('emprestimo.parcelas');
        $emprestimo = $parcela->emprestimo;
        $due = $this->valorDevidoNoPagamento($parcela, $dadosValidados);
        $tail = 0.0;
        foreach ($emprestimo->parcelas as $p) {
            if ((int) $p->id === (int) $parcela->id || $p->isQuitada()) {
                continue;
            }
            $tail += max(0, (float) $p->valor - (float) $p->valor_pago);
        }

        return round($due + $tail, 2);
    }

    /**
     * @return list<array{parcela_id: int, valor: float, is_current: bool}>
     */
    public function simularAlocacao(Parcela $parcela, float $valorTotal, array $dadosValidados): array
    {
        $parcela->loadMissing('emprestimo.parcelas');
        $emprestimo = $parcela->emprestimo;

        $ordered = collect([$parcela]);
        $others = $emprestimo->parcelas
            ->filter(fn (Parcela $p) => (int) $p->id !== (int) $parcela->id && ! $p->isQuitada())
            ->sortByDesc('numero')
            ->values();
        foreach ($others as $p) {
            $ordered->push($p);
        }

        $r = round($valorTotal, 2);
        $allocations = [];

        foreach ($ordered as $p) {
            if ($r <= 0) {
                break;
            }
            if ($p->isQuitada()) {
                continue;
            }
            $falta = max(0, (float) $p->valor - (float) $p->valor_pago);
            if ((int) $p->id === (int) $parcela->id) {
                $juros = (float) ($this->pagamentoService->getDadosJuros($p, $dadosValidados)['valor_juros'] ?? 0);
                $cap = round($falta + $juros, 2);
            } else {
                $cap = round($falta, 2);
            }
            if ($cap <= 0) {
                continue;
            }
            $take = round(min($r, $cap), 2);
            if ($take <= 0) {
                continue;
            }
            $allocations[] = [
                'parcela_id' => (int) $p->id,
                'valor' => $take,
                'is_current' => (int) $p->id === (int) $parcela->id,
            ];
            $r = round($r - $take, 2);
        }

        if ($r > 0.009) {
            $max = $this->calcularMaximoPermitido($parcela, $dadosValidados);
            throw ValidationException::withMessages([
                'valor' => 'O valor informado excede o máximo permitido para antecipação (R$ '.number_format($max, 2, ',', '.').'). Ajuste o valor para não sobrar dinheiro sem destino.',
            ]);
        }

        return $allocations;
    }

    /**
     * Registra N pagamentos no mesmo lote (transação única por chamada externa).
     *
     * @param  array<string, mixed>  $validatedBase  dados do request (parcela_id da parcela em foco, metodo, etc.)
     */
    public function executarAntecipacao(Parcela $parcela, float $valorTotal, array $validatedBase): string
    {
        $allocations = $this->simularAlocacao($parcela, $valorTotal, $validatedBase);
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'valor' => 'Não há parcelas em aberto para alocar este pagamento.',
            ]);
        }

        $loteId = (string) Str::uuid();

        return DB::transaction(function () use ($allocations, $validatedBase, $loteId, $parcela) {
            Emprestimo::lockForUpdate()->findOrFail($parcela->emprestimo_id);

            foreach ($allocations as $row) {
                $sub = array_merge($validatedBase, [
                    'parcela_id' => $row['parcela_id'],
                    'valor' => $row['valor'],
                    'tipo_juros' => $row['is_current'] ? ($validatedBase['tipo_juros'] ?? 'nenhum') : 'nenhum',
                    'lote_id' => $loteId,
                ]);
                $this->pagamentoService->registrar($sub);
            }

            return $loteId;
        });
    }
}
