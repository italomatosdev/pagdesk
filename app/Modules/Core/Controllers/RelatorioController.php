<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use App\Support\FichaContatoLookup;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RelatorioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Usuários que podem ser responsáveis por empréstimo (consultor_id): consultor, gestor ou administrador na operação.
     * Quando há operação selecionada: apenas os daquela operação. Sem operação: Super Admin vê todos; demais vêem das suas operações.
     * Nas views, exibir "(Você)" para o usuário logado.
     */
    private function getConsultoresParaRelatorio(?int $operacaoId, array $operacoesIds, $user): \Illuminate\Database\Eloquent\Collection
    {
        $papeisResponsavel = ['consultor', 'gestor', 'administrador'];

        $query = \App\Models\User::whereHas('operacoes', function ($q) use ($operacaoId, $operacoesIds, $user, $papeisResponsavel) {
            $q->whereIn('operacao_user.role', $papeisResponsavel);
            if ($operacaoId) {
                $q->where('operacoes.id', $operacaoId);
            } elseif (! $user->isSuperAdmin() && ! empty($operacoesIds)) {
                $q->whereIn('operacoes.id', $operacoesIds);
            }
        });

        return $query->orderBy('name')->get();
    }

    /**
     * JSON: consultores (papéis responsável) filtrados por operação — para AJAX nos filtros dos relatórios.
     */
    public function consultoresPorOperacao(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();

        $operacaoRaw = $request->query('operacao_id');
        $operacaoId = ($operacaoRaw !== null && $operacaoRaw !== '') ? (int) $operacaoRaw : null;

        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            abort(403, 'Operação inválida.');
        }

        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        return response()->json([
            'consultores' => $consultores->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->id === $user->id ? $c->name.' (Você)' : $c->name,
            ])->values(),
        ]);
    }

    /**
     * Separa o valor do pagamento em investido (amortização) e juros (contrato + atraso) para relatórios.
     *
     * Regra anterior (incorreta para comissão): escalava juros de contrato por (valor/valorParcela),
     * misturando juros de atraso com juros de contrato.
     *
     * Nova regra:
     * - Juros de atraso = pagamento.valor_juros (explícito).
     * - O restante (valor - juros de atraso) é o que entra no "bucket" da parcela (nominal).
     * - Sobre esse valor (limitado ao valor da parcela), aplica-se a proporção da parcela:
     *   juros contrato = valorParaRateio * (parcela.valor_juros / parcela.valor),
     *   investido = valorParaRateio * (parcela.valor_amortizacao / parcela.valor).
     * - Garantia: recebido = investido + juros.
     *
     * @return array{juros: float, investido: float}
     */
    private static function repartirInvestidoJurosParaRelatorio(Pagamento $p): array
    {
        $valor = round((float) $p->valor, 2);
        $jurosAtraso = round((float) ($p->valor_juros ?? 0), 2);
        $jurosContrato = 0.0;
        $jurosIncorporadosProporcional = 0.0;

        if ($p->parcela && (float) $p->parcela->valor > 0) {
            $valorParcela = (float) $p->parcela->valor;
            $valorSemAtraso = max(0, $valor - $jurosAtraso);
            $valorParaRateio = min($valorSemAtraso, $valorParcela);

            $valorJurosPar = (float) ($p->parcela->valor_juros ?? 0);
            $valorAmortPar = (float) ($p->parcela->valor_amortizacao ?? 0);
            if ($valorAmortPar <= 0 && $valorJurosPar > 0 && $valorParcela > 0) {
                $valorAmortPar = max(0, $valorParcela - $valorJurosPar);
            }

            if ($valorJurosPar > 0 && $valorParcela > 0) {
                $jurosContrato = $valorParaRateio * ($valorJurosPar / $valorParcela);
            } elseif ($valorParcela > 0 && ($emp = $p->parcela->emprestimo)) {
                $principalTotal = (float) ($emp->valor_total ?? 0);
                if ($emp && (int) $emp->numero_parcelas > 0 && $principalTotal > 0) {
                    $principalParcela = $principalTotal / (int) $emp->numero_parcelas;
                    $investidoParcela = ($valorParaRateio / $valorParcela) * $principalParcela;
                    $jurosContrato = max(0, $valorParaRateio - $investidoParcela);
                }
            }

            if ($p->parcela->emprestimo) {
                $emp = $p->parcela->emprestimo;
                $jurosIncorporados = (float) ($emp->juros_incorporados ?? 0);

                if ($jurosIncorporados > 0) {
                    $valorTotalParcelas = $emp->parcelas()->sum('valor');
                    if ($valorTotalParcelas > 0) {
                        $jurosIncorporadosProporcional = $valorSemAtraso * ($jurosIncorporados / $valorTotalParcelas);
                    }
                }
            }
        } else {
            $jurosContrato = 0;
        }

        $juros = round($jurosContrato + $jurosAtraso + $jurosIncorporadosProporcional, 2);
        $investido = round($valor - $juros, 2);

        return [
            'juros' => $juros,
            'investido' => $investido,
            'juros_contrato' => round($jurosContrato, 2),
            'juros_atraso' => $jurosAtraso,
            'juros_incorporados' => round($jurosIncorporadosProporcional, 2),
        ];
    }

    /**
     * Lista de relatórios (menu do módulo)
     */
    public function index(): View
    {
        return view('relatorios.index');
    }

    /**
     * Relatório: Recebimento e juros por dia (período + filtro por usuário/consultor)
     * Totalizadores; multiselect de consultores; resultado dividido por usuário.
     */
    public function recebimentoJurosDia(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }
        $consultoresIds = $request->input('consultor_id', []);
        if (! is_array($consultoresIds)) {
            $consultoresIds = $consultoresIds ? [$consultoresIds] : [];
        }
        $consultoresIds = array_filter(array_map('intval', $consultoresIds));

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        $porDiaPorUsuario = [];
        $totalizadores = ['recebido' => 0, 'juros' => 0, 'investido' => 0];
        $totalizadoresPorUsuario = [];

        foreach ($consultoresIds as $cid) {
            $c = $consultores->firstWhere('id', $cid);
            $totalizadoresPorUsuario[$cid] = ['recebido' => 0, 'juros' => 0, 'investido' => 0, 'nome' => $c ? $c->name : 'Usuário #'.$cid];
        }

        if (count($consultoresIds) > 0) {
            $query = Pagamento::with(['consultor', 'parcela.emprestimo'])
                ->whereBetween('data_pagamento', [$dateFrom, $dateTo])
                ->whereIn('consultor_id', $consultoresIds);

            if (! $user->isSuperAdmin() || $operacaoId) {
                $query->whereHas('parcela.emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                    if ($operacaoId) {
                        $q->where('operacao_id', $operacaoId);
                    } elseif (! empty($operacoesIds)) {
                        $q->whereIn('operacao_id', $operacoesIds);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });
            }

            $pagamentos = $query->get();

            foreach ($pagamentos as $p) {
                $dia = $p->data_pagamento->format('Y-m-d');
                $consultorId = $p->consultor_id;
                $valor = (float) $p->valor;
                $partes = self::repartirInvestidoJurosParaRelatorio($p);
                $juros = $partes['juros'];
                $investido = $partes['investido'];

                if (! isset($porDiaPorUsuario[$dia])) {
                    $porDiaPorUsuario[$dia] = [];
                }
                if (! isset($porDiaPorUsuario[$dia][$consultorId])) {
                    $porDiaPorUsuario[$dia][$consultorId] = ['recebido' => 0, 'juros' => 0, 'investido' => 0];
                }
                $porDiaPorUsuario[$dia][$consultorId]['recebido'] += $valor;
                $porDiaPorUsuario[$dia][$consultorId]['juros'] += $juros;
                $porDiaPorUsuario[$dia][$consultorId]['investido'] += $investido;

                $totalizadores['recebido'] += $valor;
                $totalizadores['juros'] += $juros;
                $totalizadores['investido'] += $investido;

                if (isset($totalizadoresPorUsuario[$consultorId])) {
                    $totalizadoresPorUsuario[$consultorId]['recebido'] += $valor;
                    $totalizadoresPorUsuario[$consultorId]['juros'] += $juros;
                    $totalizadoresPorUsuario[$consultorId]['investido'] += $investido;
                } else {
                    $totalizadoresPorUsuario[$consultorId] = [
                        'recebido' => $valor,
                        'juros' => $juros,
                        'investido' => $investido,
                        'nome' => $p->consultor->name ?? 'N/A',
                    ];
                }
            }

            ksort($porDiaPorUsuario);
        }

        return view('relatorios.recebimento-juros-dia', compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'porDiaPorUsuario',
            'totalizadores',
            'totalizadoresPorUsuario'
        ));
    }

    /**
     * Relatório: Parcelas atrasadas (situação em uma data, com filtros)
     */
    public function parcelasAtrasadas(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dataRef = $request->input('data_ref')
            ? Carbon::parse($request->input('data_ref'))->startOfDay()
            : Carbon::today();
        $vencimentoDe = $request->input('vencimento_de') ? Carbon::parse($request->input('vencimento_de'))->startOfDay() : null;
        $vencimentoAte = $request->input('vencimento_ate') ? Carbon::parse($request->input('vencimento_ate'))->endOfDay() : null;
        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }
        $consultoresIds = $request->input('consultor_id', []);
        if (! is_array($consultoresIds)) {
            $consultoresIds = $consultoresIds ? [$consultoresIds] : [];
        }
        $consultoresIds = array_filter(array_map('intval', $consultoresIds));
        $diasAtrasoMin = $request->input('dias_atraso_min') !== null && $request->input('dias_atraso_min') !== ''
            ? (int) $request->input('dias_atraso_min') : null;

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        $query = Parcela::with(['emprestimo.operacao', 'emprestimo.cliente', 'emprestimo.consultor'])
            ->whereIn('status', ['pendente', 'atrasada'])
            ->where('data_vencimento', '<', $dataRef);

        $query->whereHas('emprestimo', function ($q) use ($operacaoId, $operacoesIds, $consultoresIds, $user) {
            $q->where('status', 'ativo');
            if ($user->isSuperAdmin() && ! $operacaoId) {
                // Super Admin sem operação: sem filtro
            } else {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (! empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            }
            if (count($consultoresIds) > 0) {
                $q->whereIn('consultor_id', $consultoresIds);
            }
        });

        if ($vencimentoDe) {
            $query->where('data_vencimento', '>=', $vencimentoDe);
        }
        if ($vencimentoAte) {
            $query->where('data_vencimento', '<=', $vencimentoAte);
        }
        if ($diasAtrasoMin !== null && $diasAtrasoMin > 0) {
            $query->whereRaw('DATEDIFF(?, data_vencimento) >= ?', [$dataRef->format('Y-m-d'), $diasAtrasoMin]);
        }

        $parcelas = $query->orderBy('data_vencimento', 'asc')->get();

        // Calcular dias de atraso na data de referência e saldo a receber
        $dataRefCarbon = Carbon::parse($dataRef);
        $parcelas->each(function ($parcela) use ($dataRefCarbon) {
            $parcela->dias_na_data_ref = $parcela->data_vencimento->diffInDays($dataRefCarbon);
            $parcela->saldo_receber = (float) $parcela->valor - (float) ($parcela->valor_pago ?? 0);
        });

        // Fichas por (cliente_id, operacao_id) para endereço na aba Rota (Fase 4 — dados por operação)
        $pairRows = $parcelas->filter(fn ($p) => $p->emprestimo && $p->emprestimo->cliente_id && $p->emprestimo->operacao_id)
            ->map(fn ($p) => [
                'cliente_id' => (int) $p->emprestimo->cliente_id,
                'operacao_id' => (int) $p->emprestimo->operacao_id,
            ])
            ->unique(fn ($r) => $r['cliente_id'].'_'.$r['operacao_id'])
            ->values();

        $fichasPorClienteOperacao = collect();
        if ($pairRows->isNotEmpty()) {
            $q = OperacaoDadosCliente::query();
            $q->where(function ($outer) use ($pairRows) {
                foreach ($pairRows as $r) {
                    $outer->orWhere(function ($w) use ($r) {
                        $w->where('cliente_id', $r['cliente_id'])
                            ->where('operacao_id', $r['operacao_id']);
                    });
                }
            });
            $fichasPorClienteOperacao = $q->get()->keyBy(fn ($f) => $f->cliente_id.'_'.$f->operacao_id);
        }

        return view('relatorios.parcelas-atrasadas', compact(
            'dataRef',
            'vencimentoDe',
            'vencimentoAte',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'diasAtrasoMin',
            'parcelas',
            'fichasPorClienteOperacao'
        ));
    }

    /**
     * Relatório: Quitações (empréstimos finalizados no período)
     * Filtros: período, frequência, tipo (quitação total / renovação)
     */
    public function quitacoes(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }
        $consultoresIds = $request->input('consultor_id', []);
        if (! is_array($consultoresIds)) {
            $consultoresIds = $consultoresIds ? [$consultoresIds] : [];
        }
        $consultoresIds = array_filter(array_map('intval', $consultoresIds));
        $frequencia = $request->input('frequencia');
        $tipoQuitacao = $request->input('tipo_quitacao');

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        $query = Emprestimo::with(['cliente', 'operacao', 'consultor', 'parcelas'])
            ->withCount('renovacoes')
            ->where('status', 'finalizado');

        if (! $user->isSuperAdmin() || $operacaoId) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (! empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($frequencia !== null && $frequencia !== '') {
            $query->where('frequencia', $frequencia);
        }

        if ($tipoQuitacao === 'total') {
            $query->whereDoesntHave('renovacoes');
        } elseif ($tipoQuitacao === 'renovacao') {
            $query->whereHas('renovacoes');
        }

        if (count($consultoresIds) > 0) {
            $query->whereIn('consultor_id', $consultoresIds);
        }

        // Data da quitação = maior data_pagamento das parcelas; filtrar por período
        $query->whereRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) BETWEEN ? AND ?',
            [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]
        );

        $emprestimos = $query->orderByRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) DESC'
        )->get();

        $emprestimos->each(function ($e) {
            $e->data_quitacao = $e->parcelas->max('data_pagamento');
        });

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($emprestimos)
        );

        return view('relatorios.quitacoes', compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'frequencia',
            'tipoQuitacao',
            'emprestimos',
            'fichasContatoPorClienteOperacao'
        ));
    }

    /**
     * Relatório: A receber por cliente (vencimento no período).
     * Baseado em parcelas não pagas de empréstimos ativos.
     */
    public function receberPorCliente(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }

        $consultoresIds = $request->input('consultor_id', []);
        if (! is_array($consultoresIds)) {
            $consultoresIds = $consultoresIds ? [$consultoresIds] : [];
        }
        $consultoresIds = array_values(array_filter(array_map('intval', $consultoresIds)));
        // Checkbox não envia parâmetro quando desmarcado; hidden "0" + checkbox "1" garante o valor no GET.
        $somenteSemJuros = $request->has('somente_sem_juros')
            ? $request->boolean('somente_sem_juros')
            : true;

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        $query = Parcela::query()
            ->join('emprestimos as e', function ($join) {
                $join->on('e.id', '=', 'parcelas.emprestimo_id')
                    ->whereNull('e.deleted_at');
            })
            ->join('clientes as c', function ($join) {
                $join->on('c.id', '=', 'e.cliente_id')
                    ->whereNull('c.deleted_at');
            })
            ->where('e.status', 'ativo')
            ->whereNull('parcelas.deleted_at')
            ->where('parcelas.status', '<>', 'paga')
            ->whereBetween('parcelas.data_vencimento', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (! $user->isSuperAdmin() || $operacaoId) {
            if ($operacaoId) {
                $query->where('e.operacao_id', $operacaoId);
            } elseif (! empty($operacoesIds)) {
                $query->whereIn('e.operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (count($consultoresIds) > 0) {
            $query->whereIn('e.consultor_id', $consultoresIds);
        }

        $query->selectRaw('
            e.cliente_id,
            c.nome as cliente_nome,
            c.documento as cliente_documento,
            COALESCE(SUM(parcelas.valor - COALESCE(parcelas.valor_pago, 0)), 0) as total_a_receber_periodo,
            COALESCE(SUM(CASE WHEN parcelas.valor > 0 THEN (COALESCE(parcelas.valor_juros, 0) / parcelas.valor) * (parcelas.valor - COALESCE(parcelas.valor_pago, 0)) ELSE 0 END), 0) as somente_juros_no_periodo,
            COALESCE(SUM(CASE WHEN COALESCE(e.taxa_juros, 0) = 0 THEN parcelas.valor - COALESCE(parcelas.valor_pago, 0) ELSE 0 END), 0) as parcela_sem_juros_contrato_no_periodo
        ')
            ->groupBy('e.cliente_id', 'c.nome', 'c.documento');

        if ($somenteSemJuros) {
            $query->havingRaw('COALESCE(SUM(CASE WHEN COALESCE(e.taxa_juros, 0) = 0 THEN parcelas.valor - COALESCE(parcelas.valor_pago, 0) ELSE 0 END), 0) > 0');
        }

        $rows = $query
            ->orderBy('parcela_sem_juros_contrato_no_periodo', 'desc')
            ->get()
            ->map(function ($r) {
                $total = (float) ($r->total_a_receber_periodo ?? 0);
                $juros = (float) ($r->somente_juros_no_periodo ?? 0);
                $semJuros = (float) ($r->parcela_sem_juros_contrato_no_periodo ?? 0);
                $r->principal_com_juros_no_periodo = round($total - $juros - $semJuros, 2);

                return $r;
            });

        $totais = [
            'clientes' => $rows->count(),
            'total_a_receber_periodo' => round((float) $rows->sum('total_a_receber_periodo'), 2),
            'somente_juros_no_periodo' => round((float) $rows->sum('somente_juros_no_periodo'), 2),
            'parcela_sem_juros_contrato_no_periodo' => round((float) $rows->sum('parcela_sem_juros_contrato_no_periodo'), 2),
            'principal_com_juros_no_periodo' => round((float) $rows->sum('principal_com_juros_no_periodo'), 2),
        ];

        return view('relatorios.receber-por-cliente', compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'somenteSemJuros',
            'rows',
            'totais'
        ));
    }

    /**
     * Relatório: Cálculo de comissões por consultor
     * Filtros: período, operação. Lista consultores com bases (valor quitado, juros recebidos) e permite escolher tipo de comissão + taxa % para calcular.
     */
    public function comissoes(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        $totaisPorConsultor = [];
        foreach ($consultores as $c) {
            $totaisPorConsultor[$c->id] = [
                'id' => $c->id,
                'nome' => $c->name.($c->id === $user->id ? ' (Você)' : ''),
                'valor_quitado' => 0,
                'juros_recebidos' => 0,
            ];
        }

        $query = Pagamento::with(['consultor', 'parcela.emprestimo'])
            ->whereBetween('data_pagamento', [$dateFrom, $dateTo]);

        if (! $user->isSuperAdmin() || $operacaoId) {
            $query->whereHas('parcela.emprestimo', function ($q) use ($operacaoId, $operacoesIds) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (! empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        $pagamentos = $query->get();

        foreach ($pagamentos as $p) {
            $consultorId = $p->consultor_id;
            $valor = (float) $p->valor;
            $partes = self::repartirInvestidoJurosParaRelatorio($p);
            $juros = $partes['juros'];
            $investido = $partes['investido'];

            if (! isset($totaisPorConsultor[$consultorId])) {
                $totaisPorConsultor[$consultorId] = [
                    'id' => $consultorId,
                    'nome' => $p->consultor->name ?? 'Consultor #'.$consultorId,
                    'valor_quitado' => 0,
                    'juros_recebidos' => 0,
                ];
            }
            $totaisPorConsultor[$consultorId]['valor_quitado'] += $investido;
            $totaisPorConsultor[$consultorId]['juros_recebidos'] += $juros;
        }

        $consultoresComTotais = array_values($totaisPorConsultor);

        return view('relatorios.comissoes', compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultoresComTotais'
        ));
    }

    /**
     * Relatório: Entradas e saídas por categoria
     * Filtros: período e operação.
     */
    public function entradasSaidasPorCategoria(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $query = CashLedgerEntry::with('categoria')
            ->whereBetween('data_movimentacao', [$dateFrom, $dateTo]);

        if (! $user->isSuperAdmin() || $operacaoId) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (! empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $entries = $query->orderBy('data_movimentacao')->get();

        $porCategoria = [];
        $totalEntradas = 0;
        $totalSaidas = 0;

        foreach ($entries as $e) {
            $valor = (float) $e->valor;
            $catId = $e->categoria_id ?? 'sem_categoria';
            $catNome = $e->categoria?->nome ?? 'Sem categoria';

            if (! isset($porCategoria[$catId])) {
                $porCategoria[$catId] = ['nome' => $catNome, 'entradas' => 0, 'saidas' => 0];
            }

            if ($e->tipo === 'entrada') {
                $porCategoria[$catId]['entradas'] += $valor;
                $totalEntradas += $valor;
            } else {
                $porCategoria[$catId]['saidas'] += $valor;
                $totalSaidas += $valor;
            }
        }

        // Ordenar por nome da categoria
        uasort($porCategoria, fn ($a, $b) => strcasecmp($a['nome'], $b['nome']));

        return view('relatorios.entradas-saidas-categoria', compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'porCategoria',
            'totalEntradas',
            'totalSaidas'
        ));
    }

    /**
     * Relatório: Juros e Valores por Quitação
     * Mostra empréstimos finalizados com detalhamento de juros contratuais e de atraso.
     */
    public function jurosQuitacoes(Request $request): View
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }
        $consultoresIds = $request->input('consultor_id', []);
        if (! is_array($consultoresIds)) {
            $consultoresIds = $consultoresIds ? [$consultoresIds] : [];
        }
        $consultoresIds = array_filter(array_map('intval', $consultoresIds));
        $tipoEmprestimo = $request->input('tipo_emprestimo');
        $frequencia = $request->input('frequencia');
        $tipoQuitacao = $request->input('tipo_quitacao');

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $consultores = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);

        $query = Emprestimo::with(['cliente', 'operacao', 'consultor', 'parcelas.pagamentos'])
            ->where('status', 'finalizado');

        if (! $user->isSuperAdmin() || $operacaoId) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (! empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($tipoEmprestimo !== null && $tipoEmprestimo !== '') {
            $query->where('tipo', $tipoEmprestimo);
        }

        if ($frequencia !== null && $frequencia !== '') {
            $query->where('frequencia', $frequencia);
        }

        if ($tipoQuitacao === 'total') {
            $query->whereDoesntHave('renovacoes');
        } elseif ($tipoQuitacao === 'renovacao') {
            $query->whereHas('renovacoes');
        }

        if (count($consultoresIds) > 0) {
            $query->whereIn('consultor_id', $consultoresIds);
        }

        // Filtrar por data de quitação (MAX data_pagamento das parcelas)
        $query->whereRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) BETWEEN ? AND ?',
            [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]
        );

        $emprestimos = $query->orderByRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) DESC'
        )->get();

        // Calcular juros para cada empréstimo
        $tipoLabels = [
            'dinheiro' => 'Dinheiro',
            'empenho' => 'Empenho',
            'troca_cheque' => 'Troca de Cheque',
            'price' => 'Sistema Price',
        ];
        $freqLabels = [
            'diaria' => 'Diária',
            'semanal' => 'Semanal',
            'mensal' => 'Mensal',
        ];

        $totais = [
            'quantidade' => 0,
            'valor_emprestado' => 0,
            'valor_recebido' => 0,
            'juros_contrato' => 0,
            'juros_atraso' => 0,
            'total_juros' => 0,
        ];

        $emprestimos->each(function ($e) use (&$totais, $tipoLabels, $freqLabels) {
            $e->data_quitacao = $e->parcelas->max('data_pagamento');
            $e->tipo_label = $tipoLabels[$e->tipo] ?? ucfirst($e->tipo);
            $e->frequencia_label = $freqLabels[$e->frequencia] ?? ucfirst($e->frequencia ?? '-');

            $valorEmprestado = (float) $e->valor_total;
            $valorRecebido = 0;
            $jurosAtraso = 0;
            $jurosContrato = 0;

            foreach ($e->parcelas as $parcela) {
                foreach ($parcela->pagamentos as $pag) {
                    $valorRecebido += (float) $pag->valor;
                    $jurosAtraso += (float) ($pag->valor_juros ?? 0);
                }
            }

            // Juros contratuais = (soma das parcelas) - valor emprestado
            $somaParcelas = $e->parcelas->sum('valor');
            $jurosContrato = max(0, $somaParcelas - $valorEmprestado);

            $e->valor_emprestado = $valorEmprestado;
            $e->valor_recebido = $valorRecebido;
            $e->juros_contrato = round($jurosContrato, 2);
            $e->juros_atraso = round($jurosAtraso, 2);
            $e->total_juros = round($jurosContrato + $jurosAtraso, 2);

            $totais['quantidade']++;
            $totais['valor_emprestado'] += $valorEmprestado;
            $totais['valor_recebido'] += $valorRecebido;
            $totais['juros_contrato'] += $jurosContrato;
            $totais['juros_atraso'] += $jurosAtraso;
            $totais['total_juros'] += $jurosContrato + $jurosAtraso;
        });

        // Arredondar totais
        $totais['valor_emprestado'] = round($totais['valor_emprestado'], 2);
        $totais['valor_recebido'] = round($totais['valor_recebido'], 2);
        $totais['juros_contrato'] = round($totais['juros_contrato'], 2);
        $totais['juros_atraso'] = round($totais['juros_atraso'], 2);
        $totais['total_juros'] = round($totais['total_juros'], 2);

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($emprestimos)
        );

        return view('relatorios.juros-quitacoes', compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'tipoEmprestimo',
            'frequencia',
            'tipoQuitacao',
            'emprestimos',
            'totais',
            'tipoLabels',
            'freqLabels',
            'fichasContatoPorClienteOperacao'
        ));
    }
}
