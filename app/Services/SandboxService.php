<?php

namespace App\Services;

use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\OperationClient;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Models\SolicitacaoQuitacao;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SandboxService
{
    /**
     * Nomes fictícios para clientes sandbox
     */
    private static array $nomesFicticios = [
        'João Silva', 'Maria Santos', 'Pedro Oliveira', 'Ana Costa', 'Carlos Souza',
        'Fernanda Lima', 'Ricardo Alves', 'Juliana Pereira', 'Lucas Martins', 'Amanda Rocha',
        'Bruno Carvalho', 'Camila Ferreira', 'Diego Rodrigues', 'Elena Nascimento', 'Felipe Barbosa',
    ];

    /**
     * Gerar documento único para sandbox (faixa 90000000001+ para evitar conflito)
     */
    public static function gerarDocumentoSandbox(int $empresaId, int $offset): string
    {
        $base = 90000000000 + ($empresaId * 10000) + $offset;
        return (string) min($base, 99999999999);
    }

    /**
     * Criar N clientes fictícios (sandbox).
     * Se $operacaoId for informado, vincula cada cliente à operação (vínculo cliente-operação).
     */
    public function criarClientesFicticios(int $empresaId, int $quantidade, string $prefixo = '[SANDBOX]', ?int $operacaoId = null): array
    {
        $created = [];
        $ultimoOffset = (int) Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('empresa_id', $empresaId)
            ->where('sandbox', true)
            ->where('documento', '>=', '90000000000')
            ->max(DB::raw('CAST(documento AS UNSIGNED)'));
        $offset = $ultimoOffset ? ($ultimoOffset - 90000000000 + 1) : 0;

        $consultorId = null;
        if ($operacaoId) {
            $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find($operacaoId);
            if ($operacao && $operacao->empresa_id) {
                $consultorId = $this->getConsultorParaEmpresa($operacao->empresa_id);
            }
        }

        for ($i = 0; $i < $quantidade; $i++) {
            $nome = trim($prefixo . ' ' . (self::$nomesFicticios[$i % count(self::$nomesFicticios)] ?? "Cliente {$i}"));
            $doc = self::gerarDocumentoSandbox($empresaId, $offset + $i);
            if (Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->where('documento', $doc)->exists()) {
                continue;
            }
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                'tipo_pessoa' => 'fisica',
                'documento' => $doc,
                'nome' => $nome,
                'telefone' => null,
                'email' => null,
                'empresa_id' => $empresaId,
                'sandbox' => true,
            ]);
            if ($operacaoId) {
                OperationClient::firstOrCreate(
                    ['cliente_id' => $cliente->id, 'operacao_id' => $operacaoId],
                    ['limite_credito' => 0, 'status' => 'ativo', 'consultor_id' => $consultorId]
                );
            }
            $created[] = $cliente;
        }
        return $created;
    }

    /**
     * Obter um consultor válido da empresa (para empréstimo sandbox)
     */
    private function getConsultorParaEmpresa(int $empresaId): int
    {
        $user = \App\Models\User::where('empresa_id', $empresaId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['consultor', 'gestor', 'administrador']))
            ->first();
        return $user ? $user->id : auth()->id();
    }

    /**
     * Criar cenário: empréstimo(s) com parcelas atrasadas (sandbox)
     */
    public function criarCenarioParcelasAtrasadas(
        int $operacaoId,
        int $quantidadeEmprestimos = 1,
        ?array $clienteIds = null,
        float $valorParcela = 100.00,
        int $numeroParcelas = 3,
        int $diasAtraso = 15
    ): array {
        $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->findOrFail($operacaoId);
        $empresaId = $operacao->empresa_id;
        $consultorId = $this->getConsultorParaEmpresa($empresaId);

        if ($clienteIds === null || empty($clienteIds)) {
            $clientes = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->where('empresa_id', $empresaId)
                ->where('sandbox', true)
                ->limit($quantidadeEmprestimos)
                ->get();
            if ($clientes->isEmpty()) {
                throw new \InvalidArgumentException('Nenhum cliente sandbox encontrado nesta empresa. Crie clientes fictícios primeiro.');
            }
            $clienteIds = $clientes->pluck('id')->toArray();
        }

        $emprestimos = [];
        $taxaJurosSandbox = 10; // 10% para visualização real no sandbox
        $valorTotal = round($valorParcela * $numeroParcelas, 2);
        $valorJurosEmprestimo = round($valorTotal * ($taxaJurosSandbox / 100), 2);
        $valorTotalAPagar = $valorTotal + $valorJurosEmprestimo;
        $valorParcelaComJuros = round($valorTotalAPagar / $numeroParcelas, 2);
        $valorJurosPorParcela = round($valorJurosEmprestimo / $numeroParcelas, 2);
        $dataInicio = Carbon::today()->subDays($diasAtraso + 30);

        return DB::transaction(function () use (
            $operacaoId,
            $empresaId,
            $consultorId,
            $clienteIds,
            $quantidadeEmprestimos,
            $valorTotal,
            $numeroParcelas,
            $valorParcela,
            $valorParcelaComJuros,
            $valorJurosPorParcela,
            $diasAtraso,
            $dataInicio,
            $taxaJurosSandbox,
            &$emprestimos
        ) {
            $limit = min(count($clienteIds), $quantidadeEmprestimos);
            for ($e = 0; $e < $limit; $e++) {
                $clienteId = $clienteIds[$e % count($clienteIds)];
                OperationClient::firstOrCreate(
                    ['cliente_id' => $clienteId, 'operacao_id' => $operacaoId],
                    ['limite_credito' => 0, 'status' => 'ativo', 'consultor_id' => $consultorId]
                );
                $emp = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                    'operacao_id' => $operacaoId,
                    'cliente_id' => $clienteId,
                    'consultor_id' => $consultorId,
                    'valor_total' => $valorTotal,
                    'numero_parcelas' => $numeroParcelas,
                    'frequencia' => 'mensal',
                    'data_inicio' => $dataInicio,
                    'taxa_juros' => $taxaJurosSandbox,
                    'tipo' => 'dinheiro',
                    'status' => 'ativo',
                    'empresa_id' => $empresaId,
                    'sandbox' => true,
                ]);

                LiberacaoEmprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                    'emprestimo_id' => $emp->id,
                    'consultor_id' => $consultorId,
                    'valor_liberado' => $valorTotal,
                    'status' => 'pago_ao_cliente',
                    'liberado_em' => now(),
                    'pago_ao_cliente_em' => now(),
                    'empresa_id' => $empresaId,
                ]);

                $vencimentoBase = Carbon::today()->subDays($diasAtraso);
                for ($n = 1; $n <= $numeroParcelas; $n++) {
                    $dataVenc = $vencimentoBase->copy()->subDays(($numeroParcelas - $n) * 30);
                    Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                        'emprestimo_id' => $emp->id,
                        'numero' => $n,
                        'valor' => $valorParcelaComJuros,
                        'valor_juros' => $valorJurosPorParcela,
                        'valor_amortizacao' => $valorParcela,
                        'valor_pago' => 0,
                        'data_vencimento' => $dataVenc,
                        'status' => 'atrasada',
                        'dias_atraso' => max(0, Carbon::today()->diffInDays($dataVenc, false)),
                        'empresa_id' => $empresaId,
                    ]);
                }
                $emprestimos[] = $emp->fresh(['cliente', 'parcelas']);
            }
            return $emprestimos;
        });
    }

    /**
     * Criar cenário: empréstimo diária com juros 30% (sandbox).
     * Gera um empréstimo ativo com frequência diária para testar quitação em lote etc.
     */
    public function criarCenarioEmprestimoDiaria(
        int $operacaoId,
        ?int $clienteId = null,
        float $valorTotal = 1000.00,
        int $numeroParcelas = 7,
        int $diasAtrasoPrimeiraParcela = 5
    ): Emprestimo {
        $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->findOrFail($operacaoId);
        $empresaId = $operacao->empresa_id;
        $consultorId = $this->getConsultorParaEmpresa($empresaId);

        if ($clienteId === null) {
            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->where('empresa_id', $empresaId)
                ->where('sandbox', true)
                ->first();
            if (!$cliente) {
                throw new \InvalidArgumentException('Nenhum cliente sandbox encontrado nesta empresa. Crie clientes fictícios primeiro.');
            }
            $clienteId = $cliente->id;
        }

        $taxaJuros = 30; // 30%
        $valorJurosEmprestimo = round($valorTotal * ($taxaJuros / 100), 2);
        $valorTotalAPagar = $valorTotal + $valorJurosEmprestimo;
        $valorParcelaComJuros = round($valorTotalAPagar / $numeroParcelas, 2);
        $valorAmortizacaoParcela = round($valorTotal / $numeroParcelas, 2);
        $valorJurosPorParcela = round($valorJurosEmprestimo / $numeroParcelas, 2);
        // data_inicio: de forma que a primeira parcela vença há $diasAtrasoPrimeiraParcela dias
        $dataPrimeiroVencimento = Carbon::today()->subDays($diasAtrasoPrimeiraParcela);
        $dataInicio = $dataPrimeiroVencimento->copy()->subDay();

        return DB::transaction(function () use (
            $operacaoId,
            $empresaId,
            $consultorId,
            $clienteId,
            $valorTotal,
            $numeroParcelas,
            $valorParcelaComJuros,
            $valorAmortizacaoParcela,
            $valorJurosPorParcela,
            $dataInicio,
            $dataPrimeiroVencimento,
            $taxaJuros
        ) {
            OperationClient::firstOrCreate(
                ['cliente_id' => $clienteId, 'operacao_id' => $operacaoId],
                ['limite_credito' => 0, 'status' => 'ativo', 'consultor_id' => $consultorId]
            );

            $emp = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                'operacao_id' => $operacaoId,
                'cliente_id' => $clienteId,
                'consultor_id' => $consultorId,
                'valor_total' => $valorTotal,
                'numero_parcelas' => $numeroParcelas,
                'frequencia' => 'diaria',
                'data_inicio' => $dataInicio,
                'taxa_juros' => $taxaJuros,
                'tipo' => 'dinheiro',
                'status' => 'ativo',
                'empresa_id' => $empresaId,
                'sandbox' => true,
            ]);

            LiberacaoEmprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                'emprestimo_id' => $emp->id,
                'consultor_id' => $consultorId,
                'valor_liberado' => $valorTotal,
                'status' => 'pago_ao_cliente',
                'liberado_em' => now(),
                'pago_ao_cliente_em' => now(),
                'empresa_id' => $empresaId,
            ]);

            $dataVenc = $dataPrimeiroVencimento->copy();
            for ($n = 1; $n <= $numeroParcelas; $n++) {
                $estaAtrasada = $dataVenc < Carbon::today();
                Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                    'emprestimo_id' => $emp->id,
                    'numero' => $n,
                    'valor' => $valorParcelaComJuros,
                    'valor_juros' => $valorJurosPorParcela,
                    'valor_amortizacao' => $valorAmortizacaoParcela,
                    'valor_pago' => 0,
                    'data_vencimento' => $dataVenc->copy(),
                    'status' => $estaAtrasada ? 'atrasada' : 'pendente',
                    'dias_atraso' => $estaAtrasada ? (int) $dataVenc->diffInDays(Carbon::today()) : 0,
                    'empresa_id' => $empresaId,
                ]);
                $dataVenc->addDay();
            }

            return $emp->fresh(['cliente', 'parcelas']);
        });
    }

    /**
     * Limpar todos os dados sandbox (empréstimos + parcelas + liberações, opcionalmente clientes).
     * Usa forceDelete porque Emprestimo/Parcela/Cliente/Pagamento usam SoftDeletes — só delete()
     * deixaria registros com deleted_at e quebraria relações (ex.: cliente->nome null).
     */
    public function limparSandbox(bool $incluirClientes = false): array
    {
        $count = ['emprestimos' => 0, 'clientes' => 0];

        DB::transaction(function () use (&$count, $incluirClientes) {
            // Inclui soft-deletados: limpezas antigas só faziam delete() e deixavam lixo com deleted_at
            $empQuery = Emprestimo::withoutGlobalScopes()
                ->withTrashed()
                ->where('sandbox', true);
            $empIds = $empQuery->pluck('id');
            if ($empIds->isEmpty()) {
                if ($incluirClientes) {
                    $clienteIds = Cliente::withoutGlobalScopes()->withTrashed()->where('sandbox', true)->pluck('id');
                    foreach ($clienteIds as $cid) {
                        OperationClient::where('cliente_id', $cid)->delete();
                    }
                    $count['clientes'] = Cliente::withoutGlobalScopes()
                        ->withTrashed()
                        ->where('sandbox', true)
                        ->forceDelete();
                }

                return;
            }

            $parcelaIds = Parcela::withoutGlobalScopes()
                ->withTrashed()
                ->whereIn('emprestimo_id', $empIds)
                ->pluck('id');

            // Pagamentos ligados às parcelas (SoftDeletes — forceDelete evita órfãos)
            if ($parcelaIds->isNotEmpty()) {
                Pagamento::withoutGlobalScopes()
                    ->withTrashed()
                    ->whereIn('parcela_id', $parcelaIds)
                    ->forceDelete();
            }

            SolicitacaoQuitacao::withoutGlobalScopes()
                ->whereIn('emprestimo_id', $empIds)
                ->delete();

            LiberacaoEmprestimo::withoutGlobalScopes()
                ->withTrashed()
                ->whereIn('emprestimo_id', $empIds)
                ->forceDelete();

            Parcela::withoutGlobalScopes()
                ->withTrashed()
                ->whereIn('emprestimo_id', $empIds)
                ->forceDelete();

            $count['emprestimos'] = Emprestimo::withoutGlobalScopes()
                ->withTrashed()
                ->where('sandbox', true)
                ->forceDelete();

            if ($incluirClientes) {
                $clienteIds = Cliente::withoutGlobalScopes()->withTrashed()->where('sandbox', true)->pluck('id');
                foreach ($clienteIds as $cid) {
                    OperationClient::where('cliente_id', $cid)->delete();
                }
                $count['clientes'] = Cliente::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('sandbox', true)
                    ->forceDelete();
            }
        });

        return $count;
    }
}
