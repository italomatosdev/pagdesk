<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Core\Models\Produto;
use App\Modules\Core\Models\ProdutoCustoHistorico;
use Illuminate\Support\Facades\DB;

class ProdutoCustoService
{
    /**
     * Registra nova vigência de custo (histórico) e atualiza o espelho no produto.
     */
    public function definirCustoVigente(Produto $produto, float $custoUnitario, ?string $observacao, User $user): void
    {
        DB::transaction(function () use ($produto, $custoUnitario, $observacao, $user) {
            $now = now();

            ProdutoCustoHistorico::where('produto_id', $produto->id)
                ->whereNull('valido_ate')
                ->update(['valido_ate' => $now]);

            ProdutoCustoHistorico::create([
                'produto_id' => $produto->id,
                'custo_unitario' => $custoUnitario,
                'valido_de' => $now,
                'valido_ate' => null,
                'user_id' => $user->id,
                'observacao' => $observacao !== null && $observacao !== '' ? mb_substr($observacao, 0, 500) : null,
            ]);

            $produto->forceFill([
                'custo_unitario_vigente' => $custoUnitario,
                'custo_vigente_atualizado_em' => $now,
            ])->save();
        });
    }
}
