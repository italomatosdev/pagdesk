<?php

namespace App\Support;

use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Support\Collection;

/**
 * Detecta contexto de "renovação": cliente já possui empréstimo finalizado na mesma operação.
 */
final class ClienteRenovacaoCreditoLookup
{
    /**
     * @param  Collection<int, Emprestimo>  $emprestimos
     * @return array<int, bool> emprestimo_id => true se houver outro empréstimo finalizado do mesmo cliente na mesma operação
     */
    public static function mapEhRenovacaoPorEmprestimoId(Collection $emprestimos): array
    {
        if ($emprestimos->isEmpty()) {
            return [];
        }

        $clienteIds = $emprestimos->pluck('cliente_id')->unique()->filter()->values();
        $operacaoIds = $emprestimos->pluck('operacao_id')->unique()->filter()->values();

        $finalizados = Emprestimo::query()
            ->where('status', 'finalizado')
            ->whereIn('cliente_id', $clienteIds)
            ->whereIn('operacao_id', $operacaoIds)
            ->get(['id', 'cliente_id', 'operacao_id']);

        $finalPorPar = [];
        foreach ($finalizados as $row) {
            $k = (int) $row->cliente_id.':'.(int) $row->operacao_id;
            $finalPorPar[$k] ??= [];
            $finalPorPar[$k][] = (int) $row->id;
        }

        $out = [];
        foreach ($emprestimos as $e) {
            if (! $e->cliente_id || ! $e->operacao_id) {
                $out[(int) $e->id] = false;

                continue;
            }
            $k = (int) $e->cliente_id.':'.(int) $e->operacao_id;
            $lista = $finalPorPar[$k] ?? [];
            $currentId = (int) $e->id;
            $out[$currentId] = collect($lista)->contains(fn (int $fid) => $fid !== $currentId);
        }

        return $out;
    }

    public static function emprestimoEhRenovacao(Emprestimo $emprestimo): bool
    {
        if (! $emprestimo->cliente_id || ! $emprestimo->operacao_id) {
            return false;
        }

        return Emprestimo::query()
            ->where('cliente_id', $emprestimo->cliente_id)
            ->where('operacao_id', $emprestimo->operacao_id)
            ->where('status', 'finalizado')
            ->where('id', '!=', $emprestimo->id)
            ->exists();
    }
}
