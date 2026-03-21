<?php

namespace App\Support;

use App\Modules\Core\Models\OperacaoDadosCliente;
use Illuminate\Support\Collection;

/**
 * Carrega {@see OperacaoDadosCliente} em lote para pares (cliente_id, operacao_id) — evita N+1 em listagens.
 */
final class FichaContatoLookup
{
    /**
     * @param  Collection<int, array{0: int, 1: int}>  $pairs
     * @return Collection<string, OperacaoDadosCliente> chave "clienteId_operacaoId"
     */
    public static function mapByClienteOperacaoPairs(Collection $pairs): Collection
    {
        $pairs = $pairs
            ->filter(fn ($p) => is_array($p) && count($p) >= 2 && (int) $p[0] > 0 && (int) $p[1] > 0)
            ->map(fn ($p) => [(int) $p[0], (int) $p[1]])
            ->unique(fn ($p) => $p[0].'_'.$p[1])
            ->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        $q = OperacaoDadosCliente::query();
        $q->where(function ($outer) use ($pairs) {
            foreach ($pairs as $p) {
                $outer->orWhere(function ($w) use ($p) {
                    $w->where('cliente_id', $p[0])->where('operacao_id', $p[1]);
                });
            }
        });

        return $q->get()->keyBy(fn ($f) => $f->cliente_id.'_'.$f->operacao_id);
    }

    /**
     * @return Collection<int, array{0: int, 1: int}>
     */
    public static function pairsFromParcelas(iterable $parcelas): Collection
    {
        return collect($parcelas)
            ->filter(fn ($p) => $p->emprestimo
                && (int) $p->emprestimo->cliente_id > 0
                && (int) $p->emprestimo->operacao_id > 0)
            ->map(fn ($p) => [(int) $p->emprestimo->cliente_id, (int) $p->emprestimo->operacao_id]);
    }

    /**
     * @return Collection<int, array{0: int, 1: int}>
     */
    public static function pairsFromEmprestimos(iterable $emprestimos): Collection
    {
        return collect($emprestimos)
            ->filter(fn ($e) => (int) $e->cliente_id > 0 && (int) $e->operacao_id > 0)
            ->map(fn ($e) => [(int) $e->cliente_id, (int) $e->operacao_id]);
    }
}
