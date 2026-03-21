<?php

namespace App\Support;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Support\Collection;

/**
 * Nome do cliente em telas com **operação conhecida** (Fase D1 — exibição).
 * Prioriza {@see OperacaoDadosCliente}::nome para (cliente_id, operacao_id);
 * senão {@see Cliente}::nome (inclui accessors / cadastro).
 */
final class ClienteNomeExibicao
{
    public static function fromFicha(?OperacaoDadosCliente $ficha, ?Cliente $cliente): string
    {
        if ($ficha !== null) {
            $n = $ficha->nome;
            if (is_string($n) && trim($n) !== '') {
                return trim($n);
            }
        }

        return $cliente !== null ? ($cliente->nome ?? 'Cliente') : 'Cliente';
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

    /**
     * @param  Collection<string, OperacaoDadosCliente>  $fichasPorClienteOperacao  chave "clienteId_operacaoId"
     */
    public static function fromEmprestimoMap(Emprestimo $emprestimo, Collection $fichasPorClienteOperacao): string
    {
        $key = (int) $emprestimo->cliente_id.'_'.(int) $emprestimo->operacao_id;
        $ficha = $fichasPorClienteOperacao->get($key);

        return self::fromFicha($ficha instanceof OperacaoDadosCliente ? $ficha : null, $emprestimo->cliente);
    }

    /**
     * @param  Collection<string, OperacaoDadosCliente>  $fichasPorClienteOperacao
     */
    public static function fromParcelaMap(Parcela $parcela, Collection $fichasPorClienteOperacao): string
    {
        $emp = $parcela->emprestimo;
        if (! $emp) {
            return 'Cliente';
        }

        return self::fromEmprestimoMap($emp, $fichasPorClienteOperacao);
    }
}
