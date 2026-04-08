<?php

namespace App\Support;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Models\Settlement;
use Carbon\Carbon;

/**
 * Indica se um lançamento de caixa já entrou no fechamento (settlement concluído),
 * espelhando a regra do extrato em SettlementService (período + último dia com recebido_em).
 */
final class PagamentoEstornoFechamentoGate
{
    public static function lancamentoConsolidado(CashLedgerEntry $entry): bool
    {
        if ($entry->consultor_id === null || $entry->operacao_id === null) {
            return false;
        }

        $dataMov = $entry->data_movimentacao instanceof Carbon
            ? $entry->data_movimentacao->copy()->startOfDay()
            : Carbon::parse($entry->data_movimentacao)->startOfDay();

        return Settlement::query()
            ->where('status', 'concluido')
            ->where('consultor_id', $entry->consultor_id)
            ->where('operacao_id', $entry->operacao_id)
            ->whereDate('data_inicio', '<=', $dataMov)
            ->whereDate('data_fim', '>=', $dataMov)
            ->get()
            ->contains(fn (Settlement $s) => self::linhaContadaNoFechamentoConcluido($s, $entry, $dataMov));
    }

    private static function linhaContadaNoFechamentoConcluido(Settlement $settlement, CashLedgerEntry $entry, Carbon $dataMov): bool
    {
        $inicio = $settlement->data_inicio->copy()->startOfDay();
        $fim = $settlement->data_fim->copy()->startOfDay();

        if ($dataMov->lt($inicio) || $dataMov->gt($fim)) {
            return false;
        }

        if ($dataMov->lt($fim)) {
            return true;
        }

        if ($settlement->recebido_em) {
            return $entry->created_at->lte($settlement->recebido_em);
        }

        return true;
    }
}
