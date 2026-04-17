<?php

namespace App\Support;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Loans\Models\EmprestimoCheque;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use Illuminate\Support\Collection;

/**
 * Resolve o ID do empréstimo a partir de uma linha do razão de caixa (para link na UI).
 */
final class CashLedgerEmprestimoLink
{
    /** @var array<int, int|null> */
    private static array $chequeEmprestimo = [];

    /** @var array<int, int|null> */
    private static array $liberacaoEmprestimo = [];

    public static function warmForCollection(Collection $entries): void
    {
        $chequeIds = $entries
            ->whereIn('referencia_tipo', ['compensacao_cheque', 'pagamento_cheque_devolvido'])
            ->pluck('referencia_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        if ($chequeIds->isNotEmpty()) {
            foreach (EmprestimoCheque::query()->whereIn('id', $chequeIds)->get(['id', 'emprestimo_id']) as $row) {
                self::$chequeEmprestimo[(int) $row->id] = $row->emprestimo_id ? (int) $row->emprestimo_id : null;
            }
        }

        $libIds = $entries
            ->where('referencia_tipo', 'liberacao_emprestimo')
            ->pluck('referencia_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        if ($libIds->isNotEmpty()) {
            foreach (LiberacaoEmprestimo::query()->whereIn('id', $libIds)->get(['id', 'emprestimo_id']) as $row) {
                self::$liberacaoEmprestimo[(int) $row->id] = $row->emprestimo_id ? (int) $row->emprestimo_id : null;
            }
        }
    }

    public static function emprestimoId(CashLedgerEntry $entry): ?int
    {
        $viaParcela = $entry->pagamento?->parcela?->emprestimo_id;
        if ($viaParcela) {
            return (int) $viaParcela;
        }

        $tipo = $entry->referencia_tipo;
        $ref = $entry->referencia_id;
        if (! $tipo || $ref === null) {
            return null;
        }

        $ref = (int) $ref;

        if (in_array($tipo, ['quitacao_emprestimo', 'pagamento_cliente', 'cancelamento_emprestimo', 'devolucao_principal_cancelamento_renovacao'], true)) {
            return $ref > 0 ? $ref : null;
        }

        if ($tipo === 'liberacao_emprestimo') {
            $eid = self::$liberacaoEmprestimo[$ref]
                ?? LiberacaoEmprestimo::query()->whereKey($ref)->value('emprestimo_id');

            return $eid ? (int) $eid : null;
        }

        if (in_array($tipo, ['compensacao_cheque', 'pagamento_cheque_devolvido'], true)) {
            $eid = self::$chequeEmprestimo[$ref]
                ?? EmprestimoCheque::query()->whereKey($ref)->value('emprestimo_id');

            return $eid ? (int) $eid : null;
        }

        return null;
    }
}
