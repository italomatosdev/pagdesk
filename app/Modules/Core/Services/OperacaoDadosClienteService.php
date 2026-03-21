<?php

namespace App\Modules\Core\Services;

use App\Models\Scopes\EmpresaScope;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDadosCliente;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Fonte única para leitura/escrita de dados cadastrais do cliente no contexto de uma operação.
 * Usa atributos brutos de {@see Cliente} ao hidratar fallback (sem accessors de ClienteDadosEmpresa).
 */
class OperacaoDadosClienteService
{
    /** @var list<string> */
    private const CAMPOS_EDITAVEIS = [
        'empresa_id',
        'nome',
        'telefone',
        'email',
        'data_nascimento',
        'responsavel_nome',
        'responsavel_cpf',
        'responsavel_rg',
        'responsavel_cnh',
        'responsavel_cargo',
        'endereco',
        'numero',
        'cidade',
        'estado',
        'cep',
        'observacoes',
    ];

    /**
     * Registro persistido para o par (cliente, operação), ou null se ainda não existir.
     */
    public function obterParaOperacao(int $clienteId, int $operacaoId): ?OperacaoDadosCliente
    {
        return OperacaoDadosCliente::query()
            ->where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->first();
    }

    /**
     * Garante uma linha em operacao_dados_clientes: se não existir, cria a partir dos atributos brutos de clientes.
     *
     * @throws ModelNotFoundException se cliente ou operação não existir
     */
    public function garantirRegistro(int $clienteId, int $operacaoId, ?int $empresaIdOperacao = null): OperacaoDadosCliente
    {
        $existente = $this->obterParaOperacao($clienteId, $operacaoId);
        if ($existente) {
            return $existente;
        }

        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($clienteId);

        if (! $cliente) {
            throw (new ModelNotFoundException)->setModel(Cliente::class, [$clienteId]);
        }

        $operacao = Operacao::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($operacaoId);

        if (! $operacao) {
            throw (new ModelNotFoundException)->setModel(Operacao::class, [$operacaoId]);
        }

        $empresaId = $empresaIdOperacao ?? $operacao->empresa_id;
        $payload = $this->payloadBrutoFromCliente($cliente, $empresaId);

        return OperacaoDadosCliente::create(array_merge(
            [
                'cliente_id' => $clienteId,
                'operacao_id' => $operacaoId,
            ],
            $payload
        ));
    }

    /**
     * Cria ou atualiza os dados da ficha para o par (cliente, operação).
     * Apenas chaves editáveis são consideradas; demais entradas em $dados são ignoradas.
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws ModelNotFoundException se cliente ou operação não existir
     */
    public function salvarOuAtualizar(
        int $clienteId,
        int $operacaoId,
        array $dados,
        ?int $empresaIdOperacao = null
    ): OperacaoDadosCliente {
        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($clienteId);

        if (! $cliente) {
            throw (new ModelNotFoundException)->setModel(Cliente::class, [$clienteId]);
        }

        $operacao = Operacao::withoutGlobalScope(EmpresaScope::class)
            ->whereNull('deleted_at')
            ->find($operacaoId);

        if (! $operacao) {
            throw (new ModelNotFoundException)->setModel(Operacao::class, [$operacaoId]);
        }

        $empresaId = $empresaIdOperacao ?? $operacao->empresa_id;
        $filtrado = $this->filtrarCamposEditaveis($dados);

        $existente = $this->obterParaOperacao($clienteId, $operacaoId);

        if ($existente) {
            $existente->fill(array_merge(['empresa_id' => $empresaId], $filtrado));
            $existente->save();

            return $existente->fresh();
        }

        $base = $this->payloadBrutoFromCliente($cliente, $empresaId);
        $merged = array_merge($base, $filtrado);

        return OperacaoDadosCliente::create(array_merge(
            [
                'cliente_id' => $clienteId,
                'operacao_id' => $operacaoId,
            ],
            $merged
        ));
    }

    /**
     * Monta o payload a partir das colunas brutas de clientes (alinhado ao backfill).
     *
     * @return array<string, mixed>
     */
    public function payloadBrutoFromCliente(Cliente $cliente, ?int $empresaIdOperacao): array
    {
        $a = $cliente->getAttributes();

        return [
            'empresa_id' => $empresaIdOperacao,
            'nome' => $a['nome'] ?? '',
            'telefone' => $a['telefone'] ?? null,
            'email' => $a['email'] ?? null,
            'data_nascimento' => $a['data_nascimento'] ?? null,
            'responsavel_nome' => $a['responsavel_nome'] ?? null,
            'responsavel_cpf' => $a['responsavel_cpf'] ?? null,
            'responsavel_rg' => $a['responsavel_rg'] ?? null,
            'responsavel_cnh' => $a['responsavel_cnh'] ?? null,
            'responsavel_cargo' => $a['responsavel_cargo'] ?? null,
            'endereco' => $a['endereco'] ?? null,
            'numero' => $a['numero'] ?? null,
            'cidade' => $a['cidade'] ?? null,
            'estado' => $a['estado'] ?? null,
            'cep' => $a['cep'] ?? null,
            'observacoes' => $a['observacoes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function filtrarCamposEditaveis(array $dados): array
    {
        $allowed = array_flip(self::CAMPOS_EDITAVEIS);
        $out = [];
        foreach ($dados as $key => $value) {
            if (isset($allowed[$key])) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
