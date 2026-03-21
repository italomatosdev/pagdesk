<?php

namespace App\Support;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Loans\Models\Emprestimo;

/**
 * Nome exibido em notificações in-app ligadas a empréstimo (Fase D2).
 * Prioriza o nome da ficha em {@see OperacaoDadosCliente} da operação do contrato;
 * caso contrário usa o nome do cadastro do cliente.
 */
final class NotificacaoClienteDisplayName
{
    public static function forEmprestimo(Emprestimo $emprestimo): string
    {
        $cliente = $emprestimo->cliente;
        if (! $cliente && (int) $emprestimo->cliente_id > 0) {
            $cliente = Cliente::query()->find((int) $emprestimo->cliente_id);
        }

        if (! $cliente) {
            return 'Cliente';
        }

        return self::forClienteOperacao($cliente, (int) $emprestimo->operacao_id);
    }

    public static function forClienteOperacao(Cliente $cliente, int $operacaoId): string
    {
        if ($operacaoId <= 0) {
            return $cliente->nome ?? 'Cliente';
        }

        $nomeFicha = OperacaoDadosCliente::query()
            ->where('cliente_id', $cliente->id)
            ->where('operacao_id', $operacaoId)
            ->value('nome');

        if (is_string($nomeFicha) && trim($nomeFicha) !== '') {
            return trim($nomeFicha);
        }

        return $cliente->nome ?? 'Cliente';
    }
}
