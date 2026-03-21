<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Core\Models\Cliente;

/**
 * URLs do módulo de clientes com contexto de operação (ficha por operação).
 */
final class ClienteUrl
{
    /**
     * Rota `clientes.show` com `operacao_id` quando informado (contexto da ficha).
     */
    public static function show(int $clienteId, ?int $operacaoId = null): string
    {
        if ($operacaoId !== null && $operacaoId > 0) {
            return route('clientes.show', ['id' => $clienteId, 'operacao_id' => $operacaoId]);
        }

        return route('clientes.show', $clienteId);
    }

    /**
     * Se o usuário tem exatamente uma operação em comum com o cliente (vínculos filtrados), retorna esse id; senão null.
     */
    public static function operacaoIdUnicaAcessivel(Cliente $cliente, User $user): ?int
    {
        $ocs = $cliente->relationLoaded('operationClients')
            ? $cliente->operationClients
            : $cliente->operationClients()->get();

        if (! $user->isSuperAdmin()) {
            $allowed = $user->getOperacoesIds();
            if (empty($allowed)) {
                return null;
            }
            $ocs = $ocs->filter(fn ($oc) => in_array((int) $oc->operacao_id, $allowed, true));
        }

        $ids = $ocs->pluck('operacao_id')->unique()->values();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * URL da ficha alinhada ao usuário: com `operacao_id` quando há um único vínculo acessível.
     */
    public static function showForUser(Cliente $cliente, User $user): string
    {
        return self::show($cliente->id, self::operacaoIdUnicaAcessivel($cliente, $user));
    }
}
