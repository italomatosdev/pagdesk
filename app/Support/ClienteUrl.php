<?php

namespace App\Support;

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
}
