<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Cliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClienteConsultaService
{
    /**
     * Buscar cliente por documento (CPF ou CNPJ) em todas as empresas (consulta cruzada)
     * Retorna resumo agregado sem dados sensíveis
     */
    public function consultarCruzada(string $documento, ?int $empresaConsultaId = null): ?array
    {
        // Remove formatação do documento
        $documentoLimpo = preg_replace('/[^0-9]/', '', $documento);

        // Buscar cliente SEM escopo de empresa (em todas as empresas)
        $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('documento', $documentoLimpo)
            ->first();

        if (!$cliente) {
            return null;
        }

        // Se empresaConsultaId foi informada e é a mesma do cliente, não precisa mostrar consulta cruzada
        if ($empresaConsultaId && $cliente->empresa_id == $empresaConsultaId) {
            return null;
        }

        $hoje = Carbon::today();

        // Buscar empréstimos ativos GLOBAIS (em todas as empresas) - "Serasa Interno"
        $emprestimosAtivos = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with([
                'operacao' => function ($q) {
                    $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                      ->with('empresa');
                }
            ])
            ->where('cliente_id', $cliente->id)
            ->where('status', 'ativo')
            // Removido filtro de empresa - busca GLOBAL para "Serasa interno"
            ->get();

        // Buscar parcelas pendentes/atrasadas GLOBAIS (em todas as empresas) - "Serasa Interno"
        // Incluindo as que vencem hoje
        $parcelasPendentes = Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with([
                'emprestimo' => function ($q) {
                    $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                      ->with([
                          'operacao' => function ($qOp) {
                              $qOp->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                                  ->with('empresa');
                          }
                      ]);
                }
            ])
            ->whereHas('emprestimo', function ($q) use ($cliente) {
                $q->withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                  ->where('cliente_id', $cliente->id)
                  ->where('status', 'ativo'); // Apenas empréstimos ativos
                // Removido filtro de empresa - busca GLOBAL para "Serasa interno"
            })
            ->where(function ($q) use ($hoje) {
                // Apenas parcelas atrasadas (status atrasada OU pendente com vencimento passado, mas não as que vencem hoje)
                $q->where('status', 'atrasada')
                  ->orWhere(function ($subQ) use ($hoje) {
                      $subQ->where('status', 'pendente')
                           ->whereDate('data_vencimento', '<', $hoje); // < ao invés de <= para excluir as que vencem hoje
                  });
            })
            ->whereRaw('(valor - COALESCE(valor_pago, 0)) > 0')
            ->get();

        // Agrupar por empresa
        $empresasComHistorico = collect();

        // Agrupar empréstimos ativos por empresa (filtrar os que têm operação válida)
        $emprestimosPorEmpresa = $emprestimosAtivos
            ->filter(function ($e) {
                return $e->operacao && $e->operacao->empresa_id;
            })
            ->groupBy('operacao.empresa_id');
            
        foreach ($emprestimosPorEmpresa as $empresaId => $grupo) {
            $primeiroEmprestimo = $grupo->first();
            $operacao = $primeiroEmprestimo->operacao;
            $empresa = $operacao?->empresa;
            
            $empresasComHistorico->push([
                'empresa_id' => $empresaId,
                'empresa_nome' => $empresa?->nome ?? 'Empresa #' . $empresaId,
                'emprestimos_ativos' => [
                    'quantidade' => $grupo->count(),
                    'valor_total' => $grupo->sum('valor_total'),
                ],
                'parcelas_atrasadas' => [
                    'quantidade' => 0,
                    'valor_total' => 0,
                ],
            ]);
        }

        // Agrupar parcelas pendentes por empresa (filtrar as que têm operação válida)
        $parcelasFiltradas = $parcelasPendentes->filter(function ($p) {
            return $p->emprestimo && $p->emprestimo->operacao && $p->emprestimo->operacao->empresa_id;
        });
        
        $parcelasPorEmpresa = $parcelasFiltradas->groupBy(function ($p) {
            return $p->emprestimo->operacao->empresa_id;
        });
            
        foreach ($parcelasPorEmpresa as $empresaId => $grupo) {
            // Todas as parcelas já são atrasadas (a query já filtra apenas atrasadas)
            $atrasadas = $grupo;
            $venceHoje = collect(); // Não há parcelas que vencem hoje
            
            $valorTotalAtrasadas = $atrasadas->sum(function ($p) {
                return max(0, (float) $p->valor - (float) $p->valor_pago);
            });
            
            $valorTotalVenceHoje = 0; // Sempre zero, pois não buscamos as que vencem hoje
            $valorTotal = $valorTotalAtrasadas; // Total é apenas das atrasadas

            $index = $empresasComHistorico->search(function ($item) use ($empresaId) {
                return $item['empresa_id'] == $empresaId;
            });
            
            if ($index !== false) {
                // Atualizar empresa existente usando put() para evitar erro de modificação indireta
                $item = $empresasComHistorico->get($index);
                $item['parcelas_atrasadas'] = [
                    'quantidade' => $atrasadas->count(),
                    'valor_total' => $valorTotalAtrasadas,
                ];
                $item['parcelas_vence_hoje'] = [
                    'quantidade' => $venceHoje->count(),
                    'valor_total' => $valorTotalVenceHoje,
                ];
                $item['parcelas_pendentes_total'] = [
                    'quantidade' => $grupo->count(),
                    'valor_total' => $valorTotal,
                ];
                $empresasComHistorico->put($index, $item);
            } else {
                $primeiraParcela = $grupo->first();
                $operacao = $primeiraParcela->emprestimo->operacao;
                $empresa = $operacao?->empresa;
                
                $empresasComHistorico->push([
                    'empresa_id' => $empresaId,
                    'empresa_nome' => $empresa?->nome ?? 'Empresa #' . $empresaId,
                    'emprestimos_ativos' => [
                        'quantidade' => 0,
                        'valor_total' => 0,
                    ],
                    'parcelas_atrasadas' => [
                        'quantidade' => $atrasadas->count(),
                        'valor_total' => $valorTotalAtrasadas,
                    ],
                    'parcelas_vence_hoje' => [
                        'quantidade' => $venceHoje->count(),
                        'valor_total' => $valorTotalVenceHoje,
                    ],
                    'parcelas_pendentes_total' => [
                        'quantidade' => $grupo->count(),
                        'valor_total' => $valorTotal,
                    ],
                ]);
            }
        }

        // Calcular se tem histórico (empréstimos ativos OU pendências)
        $temEmprestimosAtivos = $emprestimosAtivos->isNotEmpty();
        $temPendencias = $parcelasPendentes->isNotEmpty();
        $temHistorico = $temEmprestimosAtivos || $temPendencias || $empresasComHistorico->isNotEmpty();

        return [
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'cliente_documento' => $cliente->documento_formatado,
            'cliente_cpf' => $cliente->documento_formatado, // Mantido para compatibilidade
            'cliente_tipo_pessoa' => $cliente->tipo_pessoa,
            'empresa_origem_id' => $cliente->empresa_id,
            'empresas_com_historico' => $empresasComHistorico->values()->toArray(),
            'tem_historico' => $temHistorico,
        ];
    }

    /**
     * Vincular cliente existente de outra empresa à empresa atual
     * Cria vínculo operation_client sem duplicar o cliente
     */
    public function vincularClienteExistente(int $clienteId, int $operacaoId, ?float $limiteCredito = null): \App\Modules\Core\Models\OperationClient
    {
        $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->findOrFail($clienteId);

        // Verificar se já existe vínculo
        $vinculoExistente = \App\Modules\Core\Models\OperationClient::where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->first();

        if ($vinculoExistente) {
            return $vinculoExistente;
        }

        // Criar novo vínculo
        return \App\Modules\Core\Models\OperationClient::create([
            'cliente_id' => $clienteId,
            'operacao_id' => $operacaoId,
            'limite_credito' => $limiteCredito ?? 0,
            'status' => 'ativo',
        ]);
    }
}
