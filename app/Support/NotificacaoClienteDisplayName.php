<?php

namespace App\Support;

use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;

/**
 * @deprecated Preferir {@see ClienteNomeExibicao} em código novo; mantido para compatibilidade de imports.
 * Nome em notificações in-app (Fase D2) — mesma regra que exibição D1.
 */
final class NotificacaoClienteDisplayName
{
    public static function forEmprestimo(Emprestimo $emprestimo): string
    {
        return ClienteNomeExibicao::forEmprestimo($emprestimo);
    }

    public static function forClienteOperacao(Cliente $cliente, int $operacaoId): string
    {
        return ClienteNomeExibicao::forClienteOperacao($cliente, $operacaoId);
    }
}
