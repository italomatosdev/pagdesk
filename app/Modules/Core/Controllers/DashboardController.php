<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Empresa;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\EmprestimoCheque;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Support\FichaContatoLookup;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * TTL do cache do dashboard (segundos).
     * 
     * O cache funciona com qualquer driver (file, redis, etc.).
     * Para produção com alta carga, configure CACHE_DRIVER=redis no .env.
     * O cache é invalidado automaticamente após o TTL (60s), mantendo dados quase em tempo real.
     */
    public const DASHBOARD_CACHE_TTL = 60;

    /**
     * Período máximo permitido para o filtro de datas (dias).
     */
    public const DASHBOARD_DATE_RANGE_MAX_DAYS = 366;

    /**
     * Retorna [dateFrom, dateTo] a partir do request. Padrão: mês atual.
     * Limita o intervalo a DASHBOARD_DATE_RANGE_MAX_DAYS.
     */
    protected function getDateRangeFromRequest(Request $request): array
    {
        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }
        $maxEnd = $dateFrom->copy()->addDays(self::DASHBOARD_DATE_RANGE_MAX_DAYS);
        if ($dateTo->gt($maxEnd)) {
            $dateTo = $maxEnd;
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Exibir dashboard baseado no papel do usuário
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return $this->dashboardSuperAdmin($request);
        }
        if (!empty($user->getOperacoesIdsOndeTemPapel(['administrador']))) {
            return $this->dashboardAdmin($request);
        }
        if (!empty($user->getOperacoesIdsOndeTemPapel(['gestor']))) {
            return $this->dashboardGestor($request);
        }
        return $this->dashboardConsultor($request);
    }

    /**
     * Dashboard para Administrador
     */
    protected function dashboardAdmin(Request $request): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        [$dateFrom, $dateTo] = $this->getDateRangeFromRequest($request);

        if ($operacaoId && !$user->isSuperAdmin() && (empty($operacoesIds) || !in_array($operacaoId, $operacoesIds))) {
            $operacaoId = null;
        }

        $operacoes = !empty($operacoesIds) 
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
            : collect([]);
        
        // Helper para aplicar filtro de operações em queries de Emprestimo
        $aplicarFiltroOperacaoEmprestimo = function ($query) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0'); // Nenhuma operação = nenhum resultado
            }
        };
        
        // Helper para aplicar filtro de operações em queries de Parcela (via Emprestimo)
        $aplicarFiltroOperacaoParcela = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de Pagamento (via Parcela -> Emprestimo)
        $aplicarFiltroOperacaoPagamento = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('parcela.emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de LiberacaoEmprestimo (via Emprestimo)
        $aplicarFiltroOperacaoLiberacao = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de CashLedgerEntry
        $aplicarFiltroOperacaoCash = function ($query) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        };
        
        // Helper para aplicar filtro de operações em queries de Cliente (via OperationClient)
        $aplicarFiltroOperacaoCliente = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('operationClients', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };

        // Helper para aplicar filtro de operações em queries de EmprestimoCheque (via Emprestimo)
        $aplicarFiltroOperacaoCheque = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };

        $ops = $operacoesIds;
        sort($ops);
        $cacheKey = 'dashboard:admin:op:' . ($operacaoId ?? 'all') . ':ops:' . md5(implode(',', $ops)) . ':d:' . $dateFrom->format('Y-m-d') . ':' . $dateTo->format('Y-m-d');
        $stats = Cache::remember($cacheKey, self::DASHBOARD_CACHE_TTL, function () use (
            $aplicarFiltroOperacaoEmprestimo,
            $aplicarFiltroOperacaoParcela,
            $aplicarFiltroOperacaoPagamento,
            $aplicarFiltroOperacaoLiberacao,
            $aplicarFiltroOperacaoCash,
            $aplicarFiltroOperacaoCliente,
            $aplicarFiltroOperacaoCheque,
            $operacaoId,
            $operacoesIds,
            $dateFrom,
            $dateTo,
            $user
        ) {
        // Valores para cálculos (métricas do admin) — filtro de período
        $valorTotalEmprestado = Emprestimo::where('status', 'ativo')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->sum('valor_total');
        $valorTotalRecebido = Pagamento::when(true, $aplicarFiltroOperacaoPagamento)
            ->whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->sum('valor');
        $totalParcelas = Parcela::when(true, $aplicarFiltroOperacaoParcela)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        // Administrador: mesmo critério da página parcelas/atrasadas (sem filtro por operação)
        $parcelasVencidas = Parcela::where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->when(!$user->isSuperAdmin(), $aplicarFiltroOperacaoParcela)
            ->count();
        $totalEmprestimos = Emprestimo::whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->count();
        $emprestimosAprovados = Emprestimo::whereIn('status', ['aprovado', 'ativo'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->count();
        $clientesEsteMes = Cliente::whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoCliente)
            ->count();
        $periodoAnteriorFrom = $dateFrom->copy()->subDays($dateFrom->diffInDays($dateTo) + 1);
        $periodoAnteriorTo = $dateFrom->copy()->subDay();
        $clientesMesAnterior = Cliente::whereBetween('created_at', [$periodoAnteriorFrom, $periodoAnteriorTo])
            ->when(true, $aplicarFiltroOperacaoCliente)
            ->count();

        // Valores para cálculos (métricas do gestor) — no período
        $valorPendenteLiberacao = LiberacaoEmprestimo::where('status', 'aguardando')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorTotalAReceber = Parcela::where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago'));
        // Divisão total a receber: principal (emprestado) e juros
        $valorTotalEmprestadoAReceber = (float) (Parcela::where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->selectRaw("COALESCE(SUM(CASE WHEN valor > 0 THEN (COALESCE(valor_amortizacao, valor) / valor) * (valor - COALESCE(valor_pago, 0)) ELSE 0 END), 0) as tot")->value('tot') ?? 0);
        $valorTotalJurosAReceber = (float) (Parcela::where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->selectRaw("COALESCE(SUM(CASE WHEN valor > 0 THEN (COALESCE(valor_juros, 0) / valor) * (valor - COALESCE(valor_pago, 0)) ELSE 0 END), 0) as tot")->value('tot') ?? 0);
        $valorLiberadoHoje = LiberacaoEmprestimo::where('status', 'liberado')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorLiberadoSemana = LiberacaoEmprestimo::where('status', 'liberado')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorLiberadoMes = LiberacaoEmprestimo::where('status', 'liberado')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorRecebidoHoje = Pagamento::whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoSemana = Pagamento::whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoMes = Pagamento::whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoMesAnterior = Pagamento::whereBetween('data_pagamento', [$periodoAnteriorFrom, $periodoAnteriorTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        
        // Taxa de pagamento ao cliente (no período)
        $totalLiberadas = LiberacaoEmprestimo::where('status', '!=', 'aguardando')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->count();
        $liberacoesPagas = LiberacaoEmprestimo::where('status', 'pago_ao_cliente')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->count();
        $taxaPagamentoCliente = $totalLiberadas > 0 ? round(($liberacoesPagas / $totalLiberadas) * 100, 2) : 0;
        
        // Tempo médio de liberação (no período)
        $liberacoesComTempo = LiberacaoEmprestimo::whereNotNull('liberado_em')
            ->whereNotNull('created_at')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->get();
        $tempos = $liberacoesComTempo->map(function ($lib) {
            return $lib->created_at->diffInHours($lib->liberado_em);
        });
        $tempoMedioLiberacao = $tempos->count() > 0 
            ? round($tempos->avg(), 1) 
            : 0;
        
        // Crescimento mensal
        $crescimentoMensal = $valorRecebidoMesAnterior > 0 
            ? round((($valorRecebidoMes - $valorRecebidoMesAnterior) / $valorRecebidoMesAnterior) * 100, 2) 
            : ($valorRecebidoMes > 0 ? 100 : 0);
        
        // Fluxo de caixa (no período)
        $entradas = CashLedgerEntry::where('tipo', 'entrada')
            ->whereBetween('data_movimentacao', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoCash)
            ->sum('valor');
        $saidas = CashLedgerEntry::where('tipo', 'saida')
            ->whereBetween('data_movimentacao', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoCash)
            ->sum('valor');
        $fluxoCaixa = $entradas - $saidas;
        
        // Projeção de recebimentos
        $projecaoRecebimentos = Parcela::where('status', '!=', 'paga')
            ->whereBetween('data_vencimento', [today(), Carbon::now()->addDays(7)])
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago'));

        // Estatísticas gerais (no período)
        $stats = [
            // Métricas do admin
            'total_clientes' => $clientesEsteMes,
            'total_emprestimos' => $totalEmprestimos,
            'total_operacoes' => !empty($operacoesIds) ? count($operacoesIds) : 0,
            'emprestimos_pendentes' => Emprestimo::where('status', 'pendente')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->when(true, $aplicarFiltroOperacaoEmprestimo)
                ->count(),
            'emprestimos_ativos' => Emprestimo::where('status', 'ativo')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->when(true, $aplicarFiltroOperacaoEmprestimo)
                ->count(),
            'valor_total_emprestado' => $valorTotalEmprestado,
            'valor_total_recebido' => $valorTotalRecebido,
            'parcelas_vencidas' => $parcelasVencidas,
            'liberacoes_pendentes' => LiberacaoEmprestimo::where('status', 'aguardando')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->when(true, $aplicarFiltroOperacaoLiberacao)
                ->count(),
            'taxa_inadimplencia' => $totalParcelas > 0 ? round(($parcelasVencidas / $totalParcelas) * 100, 2) : 0,
            'valor_medio_emprestimo' => $totalEmprestimos > 0 ? round($valorTotalEmprestado / $totalEmprestimos, 2) : 0,
            'taxa_aprovacao' => $totalEmprestimos > 0 ? round(($emprestimosAprovados / $totalEmprestimos) * 100, 2) : 0,
            'clientes_novos_mes' => $clientesEsteMes,
            'clientes_crescimento' => $clientesMesAnterior > 0 
                ? round((($clientesEsteMes - $clientesMesAnterior) / $clientesMesAnterior) * 100, 2) 
                : ($clientesEsteMes > 0 ? 100 : 0),
            // Métricas do gestor
            'liberacoes_hoje' => LiberacaoEmprestimo::where('status', 'aguardando')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->when(true, $aplicarFiltroOperacaoLiberacao)
                ->count(),
            'valor_pendente_liberacao' => $valorPendenteLiberacao,
            'valor_parcelas_vencidas' => Parcela::where('status', 'atrasada')
                ->whereHas('emprestimo', function ($q) {
                    $q->where('status', 'ativo'); // Apenas empréstimos ativos
                })
                ->when(!$user->isSuperAdmin(), $aplicarFiltroOperacaoParcela)
                ->sum(DB::raw('valor - valor_pago')),
            'valor_total_a_receber' => $valorTotalAReceber,
            'valor_total_emprestado_a_receber' => $valorTotalEmprestadoAReceber,
            'valor_total_juros_a_receber' => $valorTotalJurosAReceber,
            'valor_liberado_hoje' => $valorLiberadoHoje,
            'valor_liberado_semana' => $valorLiberadoSemana,
            'valor_liberado_mes' => $valorLiberadoMes,
            'taxa_recuperacao' => $valorTotalEmprestado > 0 
                ? round(($valorTotalRecebido / $valorTotalEmprestado) * 100, 2) 
                : 0,
            'valor_recebido_hoje' => $valorRecebidoHoje,
            'valor_recebido_semana' => $valorRecebidoSemana,
            'valor_recebido_mes' => $valorRecebidoMes,
            'taxa_pagamento_cliente' => $taxaPagamentoCliente,
            'tempo_medio_liberacao' => $tempoMedioLiberacao,
            'crescimento_mensal' => $crescimentoMensal,
            'fluxo_caixa' => $fluxoCaixa,
            'projecao_recebimentos' => $projecaoRecebimentos,
        ];

        // Estatísticas de Empenho
        $emprestimosEmpenho = Emprestimo::where('tipo', 'empenho')
            ->when(true, $aplicarFiltroOperacaoEmprestimo);
        $totalEmpenhos = $emprestimosEmpenho->count();
        $empenhosAtivos = Emprestimo::where('tipo', 'empenho')
            ->where('status', 'ativo')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->count();
        $valorTotalEmpenhos = Emprestimo::where('tipo', 'empenho')
            ->whereIn('status', ['ativo', 'aprovado'])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->sum('valor_total');
        $totalGarantias = \App\Modules\Loans\Models\EmprestimoGarantia::whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $q->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $q->whereIn('operacao_id', $operacoesIds);
            } else {
                $q->whereRaw('1 = 0');
            }
        })->count();
        $valorTotalGarantias = \App\Modules\Loans\Models\EmprestimoGarantia::whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $q->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $q->whereIn('operacao_id', $operacoesIds);
            } else {
                $q->whereRaw('1 = 0');
            }
        })->sum('valor_avaliado');
        
        $stats['total_empenhos'] = $totalEmpenhos;
        $stats['empenhos_ativos'] = $empenhosAtivos;
        $stats['valor_total_empenhos'] = $valorTotalEmpenhos;
        $stats['total_garantias'] = $totalGarantias;
        $stats['valor_total_garantias'] = $valorTotalGarantias;

        // Estatísticas de Cheques (Troca de Cheque)
        $stats['total_cheques'] = EmprestimoCheque::when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['valor_bruto_cheques'] = EmprestimoCheque::when(true, $aplicarFiltroOperacaoCheque)->sum('valor_cheque');
        $stats['valor_liquido_cheques'] = EmprestimoCheque::when(true, $aplicarFiltroOperacaoCheque)->sum('valor_liquido');
        $stats['cheques_aguardando'] = EmprestimoCheque::where('status', 'aguardando')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_depositado'] = EmprestimoCheque::where('status', 'depositado')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_compensado'] = EmprestimoCheque::where('status', 'compensado')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_devolvido'] = EmprestimoCheque::where('status', 'devolvido')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_vencidos_hoje'] = EmprestimoCheque::where('status', 'aguardando')
            ->whereDate('data_vencimento', today())
            ->when(true, $aplicarFiltroOperacaoCheque)
            ->count();

            return $stats;
        });

        // Cheques por status (para gráfico)
        $chequesPorStatus = EmprestimoCheque::select('status', DB::raw('count(*) as total'))
            ->when(true, $aplicarFiltroOperacaoCheque)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Empréstimos por status (no período)
        $emprestimosPorStatus = Emprestimo::select('status', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Empréstimos recentes (no período)
        $emprestimosRecentes = Emprestimo::with(['cliente', 'operacao', 'consultor'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Liberações pendentes
        $liberacoesPendentes = LiberacaoEmprestimo::with(['emprestimo.cliente', 'consultor'])
            ->where('status', 'aguardando')
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Aprovações pendentes (empréstimos pendentes que ainda não foram aprovados/rejeitados)
        $aprovacoesPendentes = Emprestimo::with(['cliente', 'consultor'])
            ->where('status', 'pendente')
            ->whereDoesntHave('aprovacao')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Top consultores (por valor emprestado) - versão simples
        $topConsultores = Emprestimo::select('consultor_id', DB::raw('SUM(valor_total) as total'), DB::raw('COUNT(*) as quantidade'))
            ->where('status', 'ativo')
            ->whereNotNull('consultor_id')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->groupBy('consultor_id')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $item->consultor = \App\Models\User::find($item->consultor_id);
                return $item;
            });

        // Ranking completo de consultores (igual ao gestor)
        $rankingConsultores = Emprestimo::select('consultor_id', 
                DB::raw('SUM(valor_total) as total_emprestado'),
                DB::raw('COUNT(*) as quantidade_emprestimos'))
            ->where('status', 'ativo')
            ->whereNotNull('consultor_id')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->groupBy('consultor_id')
            ->orderBy('total_emprestado', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) use ($aplicarFiltroOperacaoPagamento, $aplicarFiltroOperacaoParcela) {
                $consultor = \App\Models\User::find($item->consultor_id);
                if (!$consultor) return null;
                
                $valorRecebido = Pagamento::where('consultor_id', $item->consultor_id)
                    ->when(true, $aplicarFiltroOperacaoPagamento)
                    ->sum('valor');
                $taxaRecebimento = $item->total_emprestado > 0 
                    ? round(($valorRecebido / $item->total_emprestado) * 100, 2) 
                    : 0;
                
                $parcelasVencidas = Parcela::whereHas('emprestimo', function ($q) use ($item) {
                    $q->where('consultor_id', $item->consultor_id)
                      ->where('status', 'ativo'); // Apenas empréstimos ativos
                })->when(true, $aplicarFiltroOperacaoParcela)
                ->where('status', 'atrasada')->count();
                
                $totalParcelas = Parcela::whereHas('emprestimo', function ($q) use ($item) {
                    $q->where('consultor_id', $item->consultor_id);
                })->when(true, $aplicarFiltroOperacaoParcela)
                ->count();
                
                $taxaInadimplencia = $totalParcelas > 0 
                    ? round(($parcelasVencidas / $totalParcelas) * 100, 2) 
                    : 0;
                
                return [
                    'consultor' => $consultor,
                    'total_emprestado' => $item->total_emprestado,
                    'quantidade_emprestimos' => $item->quantidade_emprestimos,
                    'valor_recebido' => $valorRecebido,
                    'taxa_recebimento' => $taxaRecebimento,
                    'parcelas_vencidas' => $parcelasVencidas,
                    'taxa_inadimplencia' => $taxaInadimplencia,
                ];
            })
            ->filter()
            ->values();

        // Consultores com liberações não pagas
        $consultoresLiberacoesNaoPagas = LiberacaoEmprestimo::select('consultor_id', 
                DB::raw('COUNT(*) as quantidade'),
                DB::raw('SUM(valor_liberado) as valor_total'))
            ->where('status', 'liberado')
            ->whereNotNull('consultor_id')
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->groupBy('consultor_id')
            ->get()
            ->map(function ($item) use ($aplicarFiltroOperacaoLiberacao) {
                $consultor = \App\Models\User::find($item->consultor_id);
                if (!$consultor) return null;
                
                $liberacoes = LiberacaoEmprestimo::where('consultor_id', $item->consultor_id)
                    ->where('status', 'liberado')
                    ->whereNotNull('liberado_em')
                    ->when(true, $aplicarFiltroOperacaoLiberacao)
                    ->get();
                $tempos = $liberacoes->map(function ($lib) {
                    return $lib->liberado_em ? now()->diffInHours($lib->liberado_em) : 0;
                });
                $tempoMedio = $tempos->count() > 0 
                    ? round($tempos->avg(), 1)
                    : 0;
                
                return [
                    'consultor' => $consultor,
                    'quantidade' => $item->quantidade,
                    'valor_total' => $item->valor_total,
                    'tempo_medio_horas' => $tempoMedio,
                ];
            })
            ->filter()
            ->sortByDesc('valor_total')
            ->values();

        // Consultores com alta inadimplência
        $consultoresAltaInadimplencia = $rankingConsultores
            ->filter(function ($item) {
                return $item['taxa_inadimplencia'] > 20;
            })
            ->sortByDesc('taxa_inadimplencia')
            ->values();

        // Resumo por operação
        $resumoPorOperacao = Emprestimo::select('operacao_id',
                DB::raw('COUNT(*) as quantidade'),
                DB::raw('SUM(valor_total) as valor_total'))
            ->where('status', 'ativo')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->groupBy('operacao_id')
            ->get()
            ->map(function ($item) {
                $operacao = Operacao::find($item->operacao_id);
                if (!$operacao) return null;
                
                $valorRecebido = Pagamento::whereHas('parcela.emprestimo', function ($q) use ($item) {
                    $q->where('operacao_id', $item->operacao_id);
                })->sum('valor');
                
                return [
                    'operacao' => $operacao,
                    'quantidade' => $item->quantidade,
                    'valor_total' => $item->valor_total,
                    'valor_recebido' => $valorRecebido,
                    'taxa_recuperacao' => $item->valor_total > 0 
                        ? round(($valorRecebido / $item->valor_total) * 100, 2) 
                        : 0,
                ];
            })
            ->filter()
            ->sortByDesc('valor_total')
            ->values();

        // Empréstimos aprovados recentemente (para gestão)
        $emprestimosAprovados = Emprestimo::with(['cliente', 'consultor'])
            ->where('status', 'aprovado')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->orderBy('aprovado_em', 'desc')
            ->limit(10)
            ->get();

        // Parcelas vencidas (para gestão)
        $parcelasVencidas = Parcela::with(['emprestimo.cliente'])
            ->where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->orderBy('data_vencimento', 'asc')
            ->limit(10)
            ->get();

        return view('dashboard.admin', compact(
            'stats',
            'emprestimosPorStatus',
            'chequesPorStatus',
            'emprestimosRecentes',
            'liberacoesPendentes',
            'aprovacoesPendentes',
            'topConsultores',
            'rankingConsultores',
            'consultoresLiberacoesNaoPagas',
            'consultoresAltaInadimplencia',
            'resumoPorOperacao',
            'emprestimosAprovados',
            'parcelasVencidas',
            'operacoes',
            'operacaoId',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Dashboard para Gestor
     */
    protected function dashboardGestor(Request $request): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        [$dateFrom, $dateTo] = $this->getDateRangeFromRequest($request);

        // Validar operação selecionada (gestor só acessa suas operações)
        if ($operacaoId && (empty($operacoesIds) || !in_array($operacaoId, $operacoesIds))) {
            $operacaoId = null;
        }

        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
            : collect([]);

        // Helper para aplicar filtro de operações em queries de Emprestimo
        $aplicarFiltroOperacaoEmprestimo = function ($query) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        };
        
        // Helper para aplicar filtro de operações em queries de Parcela (via Emprestimo)
        $aplicarFiltroOperacaoParcela = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de Pagamento (via Parcela -> Emprestimo)
        $aplicarFiltroOperacaoPagamento = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('parcela.emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de LiberacaoEmprestimo (via Emprestimo)
        $aplicarFiltroOperacaoLiberacao = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de CashLedgerEntry
        $aplicarFiltroOperacaoCash = function ($query) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        };

        // Helper para aplicar filtro de operações em queries de EmprestimoCheque (via Emprestimo)
        $aplicarFiltroOperacaoCheque = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };

        $opsGestor = $operacoesIds;
        sort($opsGestor);
        $cacheKeyGestor = 'dashboard:gestor:op:' . ($operacaoId ?? 'all') . ':ops:' . md5(implode(',', $opsGestor)) . ':d:' . $dateFrom->format('Y-m-d') . ':' . $dateTo->format('Y-m-d');
        $stats = Cache::remember($cacheKeyGestor, self::DASHBOARD_CACHE_TTL, function () use (
            $aplicarFiltroOperacaoEmprestimo,
            $aplicarFiltroOperacaoParcela,
            $aplicarFiltroOperacaoPagamento,
            $aplicarFiltroOperacaoLiberacao,
            $aplicarFiltroOperacaoCash,
            $aplicarFiltroOperacaoCheque,
            $operacaoId,
            $operacoesIds,
            $dateFrom,
            $dateTo
        ) {
        $periodoAnteriorFrom = $dateFrom->copy()->subDays($dateFrom->diffInDays($dateTo) + 1);
        $periodoAnteriorTo = $dateFrom->copy()->subDay();
        // Valores para cálculos (no período)
        $valorTotalEmprestado = Emprestimo::where('status', 'ativo')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->sum('valor_total');
        $valorTotalRecebido = Pagamento::when(true, $aplicarFiltroOperacaoPagamento)
            ->whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->sum('valor');
        $valorPendenteLiberacao = LiberacaoEmprestimo::where('status', 'aguardando')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorTotalAReceber = Parcela::where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago'));
        // Divisão total a receber: principal (emprestado) e juros
        $valorTotalEmprestadoAReceber = (float) (Parcela::where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->selectRaw("COALESCE(SUM(CASE WHEN valor > 0 THEN (COALESCE(valor_amortizacao, valor) / valor) * (valor - COALESCE(valor_pago, 0)) ELSE 0 END), 0) as tot")->value('tot') ?? 0);
        $valorTotalJurosAReceber = (float) (Parcela::where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->selectRaw("COALESCE(SUM(CASE WHEN valor > 0 THEN (COALESCE(valor_juros, 0) / valor) * (valor - COALESCE(valor_pago, 0)) ELSE 0 END), 0) as tot")->value('tot') ?? 0);
        $valorLiberadoHoje = LiberacaoEmprestimo::where('status', 'liberado')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorLiberadoSemana = LiberacaoEmprestimo::where('status', 'liberado')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorLiberadoMes = LiberacaoEmprestimo::where('status', 'liberado')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->sum('valor_liberado');
        $valorRecebidoHoje = Pagamento::whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoSemana = Pagamento::whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoMes = Pagamento::whereBetween('data_pagamento', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoMesAnterior = Pagamento::whereBetween('data_pagamento', [$periodoAnteriorFrom, $periodoAnteriorTo])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        
        // Taxa de pagamento ao cliente (no período)
        $totalLiberadas = LiberacaoEmprestimo::where('status', '!=', 'aguardando')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->count();
        $liberacoesPagas = LiberacaoEmprestimo::where('status', 'pago_ao_cliente')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->count();
        $taxaPagamentoCliente = $totalLiberadas > 0 ? round(($liberacoesPagas / $totalLiberadas) * 100, 2) : 0;
        
        // Tempo médio de liberação (no período)
        $liberacoesComTempo = LiberacaoEmprestimo::whereNotNull('liberado_em')
            ->whereNotNull('created_at')
            ->whereBetween('liberado_em', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->get();
        $tempos = $liberacoesComTempo->map(function ($lib) {
            return $lib->created_at->diffInHours($lib->liberado_em);
        });
        $tempoMedioLiberacao = $tempos->count() > 0 
            ? round($tempos->avg(), 1) 
            : 0;
        
        // Crescimento (período vs período anterior)
        $crescimentoMensal = $valorRecebidoMesAnterior > 0 
            ? round((($valorRecebidoMes - $valorRecebidoMesAnterior) / $valorRecebidoMesAnterior) * 100, 2) 
            : ($valorRecebidoMes > 0 ? 100 : 0);
        
        // Fluxo de caixa (no período)
        $entradas = CashLedgerEntry::where('tipo', 'entrada')
            ->whereBetween('data_movimentacao', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoCash)
            ->sum('valor');
        $saidas = CashLedgerEntry::where('tipo', 'saida')
            ->whereBetween('data_movimentacao', [$dateFrom, $dateTo])
            ->when(true, $aplicarFiltroOperacaoCash)
            ->sum('valor');
        $fluxoCaixa = $entradas - $saidas;
        
        // Projeção de recebimentos (próximos 7 dias) — sem filtro de período
        $projecaoRecebimentos = Parcela::where('status', '!=', 'paga')
            ->whereBetween('data_vencimento', [today(), Carbon::now()->addDays(7)])
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago'));

        // Estatísticas do gestor (no período)
        $stats = [
            'liberacoes_pendentes' => LiberacaoEmprestimo::where('status', 'aguardando')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->when(true, $aplicarFiltroOperacaoLiberacao)
                ->count(),
            'liberacoes_hoje' => LiberacaoEmprestimo::where('status', 'aguardando')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->when(true, $aplicarFiltroOperacaoLiberacao)
                ->count(),
            'valor_pendente_liberacao' => $valorPendenteLiberacao,
            'parcelas_vencidas' => Parcela::where('status', 'atrasada')
                ->whereHas('emprestimo', function ($q) {
                    $q->where('status', 'ativo'); // Apenas empréstimos ativos
                })
                ->when(true, $aplicarFiltroOperacaoParcela)
                ->count(),
            'valor_parcelas_vencidas' => Parcela::where('status', 'atrasada')
                ->whereHas('emprestimo', function ($q) {
                    $q->where('status', 'ativo'); // Apenas empréstimos ativos
                })
                ->when(true, $aplicarFiltroOperacaoParcela)
                ->sum(DB::raw('valor - valor_pago')),
            // Métricas existentes
            'valor_total_a_receber' => $valorTotalAReceber,
            'valor_total_emprestado_a_receber' => $valorTotalEmprestadoAReceber,
            'valor_total_juros_a_receber' => $valorTotalJurosAReceber,
            'valor_liberado_hoje' => $valorLiberadoHoje,
            'valor_liberado_semana' => $valorLiberadoSemana,
            'taxa_recuperacao' => $valorTotalEmprestado > 0 
                ? round(($valorTotalRecebido / $valorTotalEmprestado) * 100, 2) 
                : 0,
            // Novas métricas
            'valor_liberado_mes' => $valorLiberadoMes,
            'valor_recebido_hoje' => $valorRecebidoHoje,
            'valor_recebido_semana' => $valorRecebidoSemana,
            'valor_recebido_mes' => $valorRecebidoMes,
            'taxa_pagamento_cliente' => $taxaPagamentoCliente,
            'tempo_medio_liberacao' => $tempoMedioLiberacao,
            'crescimento_mensal' => $crescimentoMensal,
            'fluxo_caixa' => $fluxoCaixa,
            'projecao_recebimentos' => $projecaoRecebimentos,
        ];

        // Estatísticas de Cheques (Troca de Cheque) - Gestor
        $stats['total_cheques'] = EmprestimoCheque::when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['valor_bruto_cheques'] = EmprestimoCheque::when(true, $aplicarFiltroOperacaoCheque)->sum('valor_cheque');
        $stats['valor_liquido_cheques'] = EmprestimoCheque::when(true, $aplicarFiltroOperacaoCheque)->sum('valor_liquido');
        $stats['cheques_aguardando'] = EmprestimoCheque::where('status', 'aguardando')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_depositado'] = EmprestimoCheque::where('status', 'depositado')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_compensado'] = EmprestimoCheque::where('status', 'compensado')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_devolvido'] = EmprestimoCheque::where('status', 'devolvido')->when(true, $aplicarFiltroOperacaoCheque)->count();
        $stats['cheques_vencidos_hoje'] = EmprestimoCheque::where('status', 'aguardando')
            ->whereDate('data_vencimento', today())
            ->when(true, $aplicarFiltroOperacaoCheque)
            ->count();

            return $stats;
        });

        $chequesPorStatus = EmprestimoCheque::select('status', DB::raw('count(*) as total'))
            ->when(true, $aplicarFiltroOperacaoCheque)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Liberações aguardando
        $liberacoesPendentes = LiberacaoEmprestimo::with(['emprestimo.cliente', 'consultor'])
            ->where('status', 'aguardando')
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Empréstimos aprovados recentemente
        $emprestimosAprovados = Emprestimo::with(['cliente', 'consultor'])
            ->whereIn('status', ['aprovado', 'ativo'])
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->orderByRaw('COALESCE(aprovado_em, created_at) DESC')
            ->limit(10)
            ->get();

        // Parcelas vencidas (para acompanhamento)
        $parcelasVencidas = Parcela::with(['emprestimo.cliente'])
            ->where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->orderBy('data_vencimento', 'asc')
            ->limit(10)
            ->get();

        // Ranking de consultores (por valor emprestado)
        $rankingConsultores = Emprestimo::select('consultor_id', 
                DB::raw('SUM(valor_total) as total_emprestado'),
                DB::raw('COUNT(*) as quantidade_emprestimos'))
            ->where('status', 'ativo')
            ->whereNotNull('consultor_id')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->groupBy('consultor_id')
            ->orderBy('total_emprestado', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) use ($aplicarFiltroOperacaoPagamento, $aplicarFiltroOperacaoParcela) {
                $consultor = \App\Models\User::find($item->consultor_id);
                if (!$consultor) return null;
                
                // Valor recebido pelo consultor
                $valorRecebido = Pagamento::where('consultor_id', $item->consultor_id)
                    ->when(true, $aplicarFiltroOperacaoPagamento)
                    ->sum('valor');
                
                // Taxa de recebimento
                $taxaRecebimento = $item->total_emprestado > 0 
                    ? round(($valorRecebido / $item->total_emprestado) * 100, 2) 
                    : 0;
                
                // Parcelas vencidas do consultor
                $parcelasVencidas = Parcela::whereHas('emprestimo', function ($q) use ($item) {
                    $q->where('consultor_id', $item->consultor_id)
                      ->where('status', 'ativo'); // Apenas empréstimos ativos
                })->when(true, $aplicarFiltroOperacaoParcela)
                ->where('status', 'atrasada')->count();
                
                // Total de parcelas do consultor
                $totalParcelas = Parcela::whereHas('emprestimo', function ($q) use ($item) {
                    $q->where('consultor_id', $item->consultor_id);
                })->when(true, $aplicarFiltroOperacaoParcela)
                ->count();
                
                // Taxa de inadimplência
                $taxaInadimplencia = $totalParcelas > 0 
                    ? round(($parcelasVencidas / $totalParcelas) * 100, 2) 
                    : 0;
                
                return [
                    'consultor' => $consultor,
                    'total_emprestado' => $item->total_emprestado,
                    'quantidade_emprestimos' => $item->quantidade_emprestimos,
                    'valor_recebido' => $valorRecebido,
                    'taxa_recebimento' => $taxaRecebimento,
                    'parcelas_vencidas' => $parcelasVencidas,
                    'taxa_inadimplencia' => $taxaInadimplencia,
                ];
            })
            ->filter()
            ->values();

        // Consultores com liberações não pagas ao cliente
        $consultoresLiberacoesNaoPagas = LiberacaoEmprestimo::select('consultor_id', 
                DB::raw('COUNT(*) as quantidade'),
                DB::raw('SUM(valor_liberado) as valor_total'))
            ->where('status', 'liberado')
            ->whereNotNull('consultor_id')
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->groupBy('consultor_id')
            ->get()
            ->map(function ($item) use ($aplicarFiltroOperacaoLiberacao) {
                $consultor = \App\Models\User::find($item->consultor_id);
                if (!$consultor) return null;
                
                // Tempo médio desde liberação
                $liberacoes = LiberacaoEmprestimo::where('consultor_id', $item->consultor_id)
                    ->where('status', 'liberado')
                    ->whereNotNull('liberado_em')
                    ->when(true, $aplicarFiltroOperacaoLiberacao)
                    ->get();
                $tempos = $liberacoes->map(function ($lib) {
                    return $lib->liberado_em ? now()->diffInHours($lib->liberado_em) : 0;
                });
                $tempoMedio = $tempos->count() > 0 
                    ? round($tempos->avg(), 1)
                    : 0;
                
                return [
                    'consultor' => $consultor,
                    'quantidade' => $item->quantidade,
                    'valor_total' => $item->valor_total,
                    'tempo_medio_horas' => $tempoMedio,
                ];
            })
            ->filter()
            ->sortByDesc('valor_total')
            ->values();

        // Consultores com alta inadimplência (acima de 20%)
        $consultoresAltaInadimplencia = $rankingConsultores
            ->filter(function ($item) {
                return $item['taxa_inadimplencia'] > 20;
            })
            ->sortByDesc('taxa_inadimplencia')
            ->values();

        // Resumo por operação
        $resumoPorOperacao = Emprestimo::select('operacao_id',
                DB::raw('COUNT(*) as quantidade'),
                DB::raw('SUM(valor_total) as valor_total'))
            ->where('status', 'ativo')
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->groupBy('operacao_id')
            ->get()
            ->map(function ($item) {
                $operacao = Operacao::find($item->operacao_id);
                if (!$operacao) return null;
                
                $valorRecebido = Pagamento::whereHas('parcela.emprestimo', function ($q) use ($item) {
                    $q->where('operacao_id', $item->operacao_id);
                })->sum('valor');
                
                return [
                    'operacao' => $operacao,
                    'quantidade' => $item->quantidade,
                    'valor_total' => $item->valor_total,
                    'valor_recebido' => $valorRecebido,
                    'taxa_recuperacao' => $item->valor_total > 0 
                        ? round(($valorRecebido / $item->valor_total) * 100, 2) 
                        : 0,
                ];
            })
            ->filter()
            ->sortByDesc('valor_total')
            ->values();

        return view('dashboard.gestor', compact(
            'stats',
            'chequesPorStatus',
            'liberacoesPendentes',
            'emprestimosAprovados',
            'parcelasVencidas',
            'rankingConsultores',
            'operacoes',
            'operacaoId',
            'consultoresLiberacoesNaoPagas',
            'consultoresAltaInadimplencia',
            'resumoPorOperacao',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Dashboard para Consultor
     */
    protected function dashboardConsultor(Request $request): View
    {
        $user = auth()->user();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        [$dateFrom, $dateTo] = $this->getDateRangeFromRequest($request);
        
        if ($operacaoId && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null;
        }
        
        // Filtrar operações disponíveis para o usuário (sempre apenas as vinculadas)
        $operacoesIds = $user->getOperacoesIds();
        $operacoes = !empty($operacoesIds) 
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
            : collect([]);

        // Helper para aplicar filtro de operações em queries de Emprestimo
        $aplicarFiltroOperacaoEmprestimo = function ($query) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        };
        
        // Helper para aplicar filtro de operações em queries de Parcela (via Emprestimo)
        $aplicarFiltroOperacaoParcela = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de Pagamento (via Parcela -> Emprestimo)
        $aplicarFiltroOperacaoPagamento = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('parcela.emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de LiberacaoEmprestimo (via Emprestimo)
        $aplicarFiltroOperacaoLiberacao = function ($query) use ($operacaoId, $operacoesIds) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (!empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        };
        
        // Helper para aplicar filtro de operações em queries de CashLedgerEntry
        $aplicarFiltroOperacaoCash = function ($query) use ($operacaoId, $operacoesIds) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        };

        // Valores para cálculos
        $meusEmprestimosAtivosQuery = Emprestimo::where('consultor_id', $user->id)
            ->where('status', 'ativo')
            ->when(true, $aplicarFiltroOperacaoEmprestimo);
        $valorTotalEmprestado = (clone $meusEmprestimosAtivosQuery)->sum('valor_total');
        $quantidadeEmprestimos = (clone $meusEmprestimosAtivosQuery)->count();
        
        $valorRecebidoHoje = Pagamento::where('consultor_id', $user->id)
            ->whereDate('data_pagamento', today())
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoSemana = Pagamento::where('consultor_id', $user->id)
            ->whereBetween('data_pagamento', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        $valorRecebidoMes = Pagamento::where('consultor_id', $user->id)
            ->whereMonth('data_pagamento', Carbon::now()->month)
            ->whereYear('data_pagamento', Carbon::now()->year)
            ->when(true, $aplicarFiltroOperacaoPagamento)
            ->sum('valor');
        
        $valorTotalAReceber = Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago'));
        // Divisão total a receber: principal (emprestado) e juros
        $valorTotalEmprestadoAReceber = (float) (Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->selectRaw("COALESCE(SUM(CASE WHEN valor > 0 THEN (COALESCE(valor_amortizacao, valor) / valor) * (valor - COALESCE(valor_pago, 0)) ELSE 0 END), 0) as tot")->value('tot') ?? 0);
        $valorTotalJurosAReceber = (float) (Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->selectRaw("COALESCE(SUM(CASE WHEN valor > 0 THEN (COALESCE(valor_juros, 0) / valor) * (valor - COALESCE(valor_pago, 0)) ELSE 0 END), 0) as tot")->value('tot') ?? 0);
        
        // Saldo em caixa (entradas - saídas)
        $entradas = CashLedgerEntry::where('consultor_id', $user->id)
            ->where('tipo', 'entrada')
            ->when(true, $aplicarFiltroOperacaoCash)
            ->sum('valor');
        $saidas = CashLedgerEntry::where('consultor_id', $user->id)
            ->where('tipo', 'saida')
            ->when(true, $aplicarFiltroOperacaoCash)
            ->sum('valor');
        $saldoCaixa = $entradas - $saidas;

        // Próximas cobranças (próximos 7 dias)
        $proximasCobrancas = Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->where('status', '!=', 'paga')
            ->whereBetween('data_vencimento', [today(), Carbon::now()->addDays(7)])
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->count();
        $valorProximasCobrancas = Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->where('status', '!=', 'paga')
            ->whereBetween('data_vencimento', [today(), Carbon::now()->addDays(7)])
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago'));

        // Estatísticas do consultor
        $stats = [
            'meus_emprestimos_ativos' => $quantidadeEmprestimos,
            'cobrancas_hoje' => Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->whereDate('data_vencimento', today())
            ->where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->count(),
            'parcelas_atrasadas' => Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id)
                      ->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->count(),
            'valor_a_receber_hoje' => Parcela::whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->whereDate('data_vencimento', today())
            ->where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->sum(DB::raw('valor - valor_pago')),
            'minhas_liberacoes_pendentes' => LiberacaoEmprestimo::where('consultor_id', $user->id)
                ->where('status', 'aguardando')
                ->when(true, $aplicarFiltroOperacaoLiberacao)
                ->count(),
            'minhas_liberacoes_liberadas' => LiberacaoEmprestimo::where('consultor_id', $user->id)
                ->where('status', 'liberado')
                ->when(true, $aplicarFiltroOperacaoLiberacao)
                ->count(),
            // Novas métricas
            'valor_recebido_hoje' => $valorRecebidoHoje,
            'valor_recebido_semana' => $valorRecebidoSemana,
            'valor_recebido_mes' => $valorRecebidoMes,
            'valor_total_a_receber' => $valorTotalAReceber,
            'valor_total_emprestado_a_receber' => $valorTotalEmprestadoAReceber,
            'valor_total_juros_a_receber' => $valorTotalJurosAReceber,
            'saldo_caixa' => $saldoCaixa,
            'valor_medio_emprestimo' => $quantidadeEmprestimos > 0 
                ? round($valorTotalEmprestado / $quantidadeEmprestimos, 2) 
                : 0,
            'taxa_recebimento' => $valorTotalEmprestado > 0 
                ? round(($valorRecebidoMes / $valorTotalEmprestado) * 100, 2) 
                : 0,
            'proximas_cobrancas' => $proximasCobrancas,
            'valor_proximas_cobrancas' => $valorProximasCobrancas,
        ];

        // Cobranças do dia
        $cobrancasHoje = Parcela::with(['emprestimo.cliente'])
            ->whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->whereDate('data_vencimento', today())
            ->where('status', '!=', 'paga')
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->orderBy('data_vencimento', 'asc')
            ->get();

        // Parcelas atrasadas
        $parcelasAtrasadas = Parcela::with(['emprestimo.cliente'])
            ->whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id)
                      ->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->orderBy('data_vencimento', 'asc')
            ->limit(10)
            ->get();

        // Meus empréstimos recentes
        $meusEmprestimos = Emprestimo::with(['cliente', 'operacao'])
            ->where('consultor_id', $user->id)
            ->when(true, $aplicarFiltroOperacaoEmprestimo)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Minhas liberações
        $minhasLiberacoes = LiberacaoEmprestimo::with(['emprestimo.cliente'])
            ->where('consultor_id', $user->id)
            ->whereIn('status', ['aguardando', 'liberado'])
            ->when(true, $aplicarFiltroOperacaoLiberacao)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Próximas cobranças (próximos 7 dias) - lista
        $proximasCobrancasLista = Parcela::with(['emprestimo.cliente'])
            ->whereHas('emprestimo', function ($query) use ($user) {
                $query->where('consultor_id', $user->id);
            })
            ->where('status', '!=', 'paga')
            ->whereBetween('data_vencimento', [today(), Carbon::now()->addDays(7)])
            ->when(true, $aplicarFiltroOperacaoParcela)
            ->orderBy('data_vencimento', 'asc')
            ->limit(10)
            ->get();

        $parcelasParaFichaContato = $cobrancasHoje->concat($parcelasAtrasadas)->concat($proximasCobrancasLista);
        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromParcelas($parcelasParaFichaContato)
        );

        return view('dashboard.consultor', compact(
            'stats',
            'cobrancasHoje',
            'parcelasAtrasadas',
            'meusEmprestimos',
            'minhasLiberacoes',
            'proximasCobrancasLista',
            'operacoes',
            'operacaoId',
            'dateFrom',
            'dateTo',
            'fichasContatoPorClienteOperacao'
        ));
    }

    /**
     * Dashboard para Super Admin
     */
    protected function dashboardSuperAdmin(Request $request): View
    {
        // Estatísticas gerais do sistema (sem escopo de empresa)
        $totalEmpresas = Empresa::count();
        $empresasAtivas = Empresa::where('status', 'ativa')->count();
        $empresasSuspensas = Empresa::where('status', 'suspensa')->count();
        $empresasCanceladas = Empresa::where('status', 'cancelada')->count();

        $totalUsuarios = \App\Models\User::where('is_super_admin', false)->count();
        $usuariosPorPapel = \App\Models\User::where('is_super_admin', false)
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->select('roles.name as papel', DB::raw('count(DISTINCT users.id) as total'))
            ->groupBy('roles.name')
            ->pluck('total', 'papel');

        $totalClientes = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->count();
        $totalEmprestimos = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->count();
        $emprestimosAtivos = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('status', 'ativo')
            ->count();
        $valorTotalEmprestado = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('status', 'ativo')
            ->sum('valor_total');

        // Distribuição por plano
        $distribuicaoPorPlano = Empresa::select('plano', DB::raw('count(*) as total'))
            ->groupBy('plano')
            ->pluck('total', 'plano');

        // Top 10 empresas por volume
        $topEmpresas = Empresa::withCount(['operacoes', 'usuarios', 'clientes', 'emprestimos'])
            ->limit(10)
            ->get();
        
        // Ajustar contagem de clientes para incluir vinculados e ordenar
        foreach ($topEmpresas as $empresa) {
            $empresa->clientes_count = $empresa->todosClientes()->count();
        }
        $topEmpresas = $topEmpresas->sortByDesc('clientes_count')->take(10)->values();

        // Empresas recentes (últimas 5)
        $empresasRecentes = Empresa::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Crescimento (últimos 6 meses)
        $crescimentoEmpresas = [];
        $crescimentoUsuarios = [];
        $crescimentoClientes = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $mes = Carbon::now()->subMonths($i);
            $mesAno = $mes->format('Y-m');
            $mesLabel = $mes->format('M/Y');
            
            $crescimentoEmpresas[$mesLabel] = Empresa::whereYear('created_at', $mes->year)
                ->whereMonth('created_at', $mes->month)
                ->count();
            
            $crescimentoUsuarios[$mesLabel] = \App\Models\User::where('is_super_admin', false)
                ->whereYear('created_at', $mes->year)
                ->whereMonth('created_at', $mes->month)
                ->count();
            
            $crescimentoClientes[$mesLabel] = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->whereYear('created_at', $mes->year)
                ->whereMonth('created_at', $mes->month)
                ->count();
        }

        // Taxa de crescimento mensal
        $empresasMesAtual = $crescimentoEmpresas[array_key_last($crescimentoEmpresas)];
        $empresasMesAnterior = $crescimentoEmpresas[array_key_first($crescimentoEmpresas)];
        $taxaCrescimentoEmpresas = $empresasMesAnterior > 0 
            ? round((($empresasMesAtual - $empresasMesAnterior) / $empresasMesAnterior) * 100, 2) 
            : ($empresasMesAtual > 0 ? 100 : 0);

        // Alertas
        $empresasExpirando = Empresa::where('status', 'ativa')
            ->whereNotNull('data_expiracao')
            ->whereBetween('data_expiracao', [today(), Carbon::now()->addDays(30)])
            ->orderBy('data_expiracao', 'asc')
            ->get();

        // Empresas sem atividade recente (últimos 30 dias)
        $empresasSemAtividade = Empresa::where('status', 'ativa')
            ->whereDoesntHave('emprestimos', function($q) {
                $q->where('created_at', '>=', Carbon::now()->subDays(30));
            })
            ->whereDoesntHave('clientes', function($q) {
                $q->where('created_at', '>=', Carbon::now()->subDays(30));
            })
            ->get();

        // Estatísticas agregadas
        $totalOperacoes = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('ativo', true)
            ->count();
        
        $taxaInadimplencia = 0;
        $totalParcelas = \App\Modules\Loans\Models\Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->count();
        $parcelasAtrasadas = \App\Modules\Loans\Models\Parcela::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->where('status', 'atrasada')
            ->whereHas('emprestimo', function ($q) {
                $q->where('status', 'ativo'); // Apenas empréstimos ativos
            })
            ->count();
        if ($totalParcelas > 0) {
            $taxaInadimplencia = round(($parcelasAtrasadas / $totalParcelas) * 100, 2);
        }

        // Médias
        $mediaUsuariosPorEmpresa = $empresasAtivas > 0 ? round($totalUsuarios / $empresasAtivas, 1) : 0;
        $mediaClientesPorEmpresa = $empresasAtivas > 0 ? round($totalClientes / $empresasAtivas, 1) : 0;
        $valorMedioEmprestimo = $totalEmprestimos > 0 ? round($valorTotalEmprestado / $totalEmprestimos, 2) : 0;

        // Taxa de retenção
        $taxaRetencao = $totalEmpresas > 0 
            ? round(($empresasAtivas / $totalEmpresas) * 100, 2) 
            : 0;

        return view('dashboard.super-admin', compact(
            'totalEmpresas',
            'empresasAtivas',
            'empresasSuspensas',
            'empresasCanceladas',
            'totalUsuarios',
            'usuariosPorPapel',
            'totalClientes',
            'totalEmprestimos',
            'emprestimosAtivos',
            'valorTotalEmprestado',
            'distribuicaoPorPlano',
            'topEmpresas',
            'empresasRecentes',
            'crescimentoEmpresas',
            'crescimentoUsuarios',
            'crescimentoClientes',
            'taxaCrescimentoEmpresas',
            'empresasExpirando',
            'empresasSemAtividade',
            'totalOperacoes',
            'taxaInadimplencia',
            'mediaUsuariosPorEmpresa',
            'mediaClientesPorEmpresa',
            'valorMedioEmprestimo',
            'taxaRetencao'
        ));
    }
}
