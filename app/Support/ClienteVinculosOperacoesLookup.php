<?php

namespace App\Support;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\OperationClient;

class ClienteVinculosOperacoesLookup
{
    /**
     * Normaliza documento para CPF (11 dígitos). Retorna null se não for CPF.
     */
    public static function cpfFromDocumento(?string $documento): ?string
    {
        if (!$documento) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $documento) ?? '';
        if (strlen($digits) !== 11) {
            return null;
        }

        return $digits;
    }

    /**
     * Mapa CPF -> lista de operacao_id em que o cliente (mesmo CPF) tem vínculo (operation_clients).
     * Escopo: respeita o escopo global de empresa ativo na aplicação.
     *
     * @param array<int, string> $cpfs Lista de CPFs (apenas dígitos, 11 chars)
     * @return array<string, array<int, int>> ex.: ['123...'=> [1,2,3]]
     */
    public static function operacoesIdsPorCpf(array $cpfs): array
    {
        $cpfs = array_values(array_unique(array_filter($cpfs, fn ($c) => is_string($c) && strlen($c) === 11)));
        if (empty($cpfs)) {
            return [];
        }

        /** @var array<string, int> $clienteIdPorCpf */
        $clienteIdPorCpf = Cliente::query()
            ->whereIn('documento', $cpfs)
            ->pluck('id', 'documento')
            ->all();

        if (empty($clienteIdPorCpf)) {
            return [];
        }

        $clienteIds = array_values(array_unique(array_map('intval', array_values($clienteIdPorCpf))));

        $rows = OperationClient::query()
            ->whereIn('cliente_id', $clienteIds)
            ->select(['cliente_id', 'operacao_id'])
            ->get();

        /** @var array<int, array<int, int>> $operacoesPorClienteId */
        $operacoesPorClienteId = [];
        foreach ($rows as $r) {
            $cid = (int) $r->cliente_id;
            $oid = (int) $r->operacao_id;
            $operacoesPorClienteId[$cid] ??= [];
            $operacoesPorClienteId[$cid][$oid] = $oid;
        }

        $operacoesPorCpf = [];
        foreach ($clienteIdPorCpf as $cpf => $clienteId) {
            $opsSet = $operacoesPorClienteId[(int) $clienteId] ?? [];
            $operacoesPorCpf[$cpf] = array_values($opsSet);
        }

        return $operacoesPorCpf;
    }
}

