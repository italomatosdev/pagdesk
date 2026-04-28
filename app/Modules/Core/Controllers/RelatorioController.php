<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDadosCliente;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\QuitacaoService;
use App\Support\ClienteNomeExibicao;
use App\Support\FichaContatoLookup;
use App\Support\OperacaoPreferida;
use App\Support\RelatorioCsvStream;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Eager load da repartição juros/investido (withCount evita N+1 em participaCadeiaRenovacaoRelatorio).
     *
     * @return array<string, mixed>
     */
    private static function withPagamentoParaReparticaoRelatorio(): array
    {
        return [
            'consultor',
            'parcela.emprestimo' => static function ($query) {
                $query->withCount('renovacoes');
            },
        ];
    }

    /**
     * @return string vazio ou diaria|semanal|mensal
     */
    private static function normalizarFrequenciaComissoes(mixed $raw): string
    {
        $val = is_string($raw) ? $raw : '';

        return in_array($val, ['diaria', 'semanal', 'mensal'], true) ? $val : '';
    }

    /**
     * Relatório de comissões: restringe pagamentos pelo empréstimo da parcela (operação + frequência).
     *
     * @param  Builder<Pagamento>  $query
     */
    private function aplicarFiltroComissoesParcelaEmprestimo(
        Builder $query,
        $user,
        ?int $operacaoId,
        array $operacoesIds,
        string $frequencia
    ): void {
        $temFiltroOperacao = ! $user->isSuperAdmin() || $operacaoId;
        $temFiltroFrequencia = $frequencia !== '';

        if (! $temFiltroOperacao && ! $temFiltroFrequencia) {
            return;
        }

        $query->whereHas('parcela.emprestimo', function ($q) use ($operacaoId, $operacoesIds, $temFiltroOperacao, $temFiltroFrequencia, $frequencia) {
            if ($temFiltroOperacao) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } elseif (! empty($operacoesIds)) {
                    $q->whereIn('operacao_id', $operacoesIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            }
            if ($temFiltroFrequencia) {
                $q->where('frequencia', $frequencia);
            }
        });
    }

    /**
     * Restringe pagamentos a empréstimos com quitação total (sem renovação gerada) cuja data de quitação
     * — maior data_pagamento entre parcelas — está no intervalo [dateFrom, dateTo] (comparado como data Y-m-d).
     *
     * @param  Builder<Pagamento>  $query
     */
    private function aplicarFiltroComissoesQuitacaoTotalPorDataQuitacaoNoPeriodo(
        Builder $query,
        Carbon $dateFrom,
        Carbon $dateTo
    ): void {
        $ini = $dateFrom->format('Y-m-d');
        $fim = $dateTo->format('Y-m-d');

        $query->whereHas('parcela.emprestimo', function ($q) use ($ini, $fim) {
            $q->where('status', 'finalizado')
                ->whereDoesntHave('renovacoes')
                ->whereRaw(
                    '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) BETWEEN ? AND ?',
                    [$ini, $fim]
                );
        });
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
     * - Cadeia de renovação (é renovação OU contrato que já gerou renovação) + pagamento só juros do contrato
     *   (nominal ≤ juros da parcela, com tolerância): não usar a proporção da linha; nominal vira juros de contrato.
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

            $emp = $p->parcela->emprestimo;
            $epsilon = 0.02;
            $renovacaoSoJurosContrato = $emp
                && $emp->participaCadeiaRenovacaoRelatorio()
                && $valorJurosPar > 0
                && $valorParcela > 0
                && $valorSemAtraso <= $valorJurosPar + $epsilon;

            if ($renovacaoSoJurosContrato) {
                $jurosContrato = $valorSemAtraso;
            } elseif ($valorJurosPar > 0 && $valorParcela > 0) {
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
     * Detalhe do relatório de comissões: empréstimos que compõem os pagamentos, com mesma repartição principal/juros.
     *
     * @param  Collection<int, Pagamento>  $pagamentos
     * @return array{linhas: list<array<string, mixed>>, totais: array<string, float|int>}
     */
    private function montarDetalheComissoesEmprestimos(Collection $pagamentos): array
    {
        $acumPorEmprestimo = [];

        foreach ($pagamentos as $p) {
            $valor = (float) $p->valor;
            $partes = self::repartirInvestidoJurosParaRelatorio($p);
            $emprestimoId = $p->parcela?->emprestimo_id;
            if (! $emprestimoId) {
                continue;
            }
            if (! isset($acumPorEmprestimo[$emprestimoId])) {
                $acumPorEmprestimo[$emprestimoId] = [
                    'total_pago' => 0.0,
                    'valor_quitado' => 0.0,
                    'juros_recebidos' => 0.0,
                ];
            }
            $acumPorEmprestimo[$emprestimoId]['total_pago'] += $valor;
            $acumPorEmprestimo[$emprestimoId]['valor_quitado'] += $partes['investido'];
            $acumPorEmprestimo[$emprestimoId]['juros_recebidos'] += $partes['juros'];
        }

        if ($acumPorEmprestimo === []) {
            return [
                'linhas' => [],
                'totais' => [
                    'qtd_emprestimos' => 0,
                    'soma_valor_total_contratos' => 0.0,
                    'soma_total_pago_periodo' => 0.0,
                    'soma_valor_quitado' => 0.0,
                    'soma_juros_recebidos' => 0.0,
                ],
            ];
        }

        $idsEmprestimos = array_keys($acumPorEmprestimo);

        $emprestimos = Emprestimo::with(['cliente', 'consultor', 'parcelas'])
            ->whereIn('id', $idsEmprestimos)
            ->get()
            ->keyBy('id');

        $maxDataPorEmprestimo = Pagamento::query()
            ->join('parcelas', 'parcelas.id', '=', 'pagamentos.parcela_id')
            ->whereIn('parcelas.emprestimo_id', $idsEmprestimos)
            ->whereNull('pagamentos.deleted_at')
            ->whereNull('parcelas.deleted_at')
            ->groupBy('parcelas.emprestimo_id')
            ->selectRaw('parcelas.emprestimo_id as eid, MAX(pagamentos.data_pagamento) as dmax')
            ->pluck('dmax', 'eid');

        $linhas = [];
        foreach ($acumPorEmprestimo as $emprestimoId => $acc) {
            $emp = $emprestimos->get($emprestimoId);
            if (! $emp) {
                continue;
            }
            $dataQuitacao = null;
            if ($emp->isFinalizado() || $emp->todasParcelasPagas()) {
                $raw = $maxDataPorEmprestimo->get($emprestimoId);
                if ($raw !== null && $raw !== '') {
                    $dataQuitacao = Carbon::parse($raw)->format('d/m/Y');
                }
            }
            $linhas[] = [
                'emprestimo_id' => (int) $emprestimoId,
                'cliente_nome' => $emp->cliente->nome ?? '—',
                'consultor_nome' => $emp->consultor->name ?? '—',
                'valor_total' => round((float) $emp->valor_total, 2),
                'total_pago_periodo' => round((float) $acc['total_pago'], 2),
                'valor_quitado' => round((float) $acc['valor_quitado'], 2),
                'juros_recebidos' => round((float) $acc['juros_recebidos'], 2),
                'data_quitacao' => $dataQuitacao,
            ];
        }

        usort($linhas, function ($a, $b) {
            $c = strcmp($a['cliente_nome'], $b['cliente_nome']);

            return $c !== 0 ? $c : ($a['emprestimo_id'] <=> $b['emprestimo_id']);
        });

        $totais = [
            'qtd_emprestimos' => count($linhas),
            'soma_valor_total_contratos' => round((float) array_sum(array_column($linhas, 'valor_total')), 2),
            'soma_total_pago_periodo' => round((float) array_sum(array_column($linhas, 'total_pago_periodo')), 2),
            'soma_valor_quitado' => round((float) array_sum(array_column($linhas, 'valor_quitado')), 2),
            'soma_juros_recebidos' => round((float) array_sum(array_column($linhas, 'juros_recebidos')), 2),
        ];

        return compact('linhas', 'totais');
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
        return view('relatorios.recebimento-juros-dia', $this->buildRecebimentoJurosDiaDataset($request));
    }

    public function exportRecebimentoJurosDia(Request $request): StreamedResponse
    {
        $d = $this->buildRecebimentoJurosDiaDataset($request);

        return RelatorioCsvStream::download('recebimento_juros_dia', function ($out) use ($d) {
            fputcsv($out, ['Data', 'Consultor', 'Recebido', 'Investido', 'Juros'], ';');
            foreach ($d['porDiaPorUsuario'] as $dia => $porUsuario) {
                foreach ($porUsuario as $consultorId => $v) {
                    $nome = $d['totalizadoresPorUsuario'][$consultorId]['nome'] ?? ('#'.$consultorId);
                    fputcsv($out, [
                        Carbon::parse($dia)->format('d/m/Y'),
                        $nome,
                        number_format($v['recebido'], 2, ',', '.'),
                        number_format($v['investido'] ?? 0, 2, ',', '.'),
                        number_format($v['juros'], 2, ',', '.'),
                    ], ';');
                }
            }
            fputcsv($out, [
                'TOTAIS',
                '',
                number_format($d['totalizadores']['recebido'], 2, ',', '.'),
                number_format($d['totalizadores']['investido'], 2, ',', '.'),
                number_format($d['totalizadores']['juros'], 2, ',', '.'),
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecebimentoJurosDiaDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
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
            $query = Pagamento::with(self::withPagamentoParaReparticaoRelatorio())
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

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'porDiaPorUsuario',
            'totalizadores',
            'totalizadoresPorUsuario'
        );
    }

    /**
     * Relatório: Parcelas atrasadas (situação em uma data, com filtros)
     */
    public function parcelasAtrasadas(Request $request): View
    {
        return view('relatorios.parcelas-atrasadas', $this->buildParcelasAtrasadasDataset($request));
    }

    public function exportParcelasAtrasadas(Request $request): StreamedResponse
    {
        $d = $this->buildParcelasAtrasadasDataset($request);
        $map = $d['fichasPorClienteOperacao'] ?? collect();
        $parcelas = $d['parcelas'];

        return RelatorioCsvStream::download('parcelas_atrasadas', function ($out) use ($d, $map, $parcelas) {
            fputcsv($out, [
                'Cliente', 'Operação', 'Consultor', 'Parcela', 'Vencimento', 'Dias atraso', 'Valor', 'Valor pago', 'Saldo', 'Status',
            ], ';');
            foreach ($parcelas as $p) {
                $nome = $p->emprestimo && $p->emprestimo->cliente
                    ? ClienteNomeExibicao::fromParcelaMap($p, $map)
                    : '';
                fputcsv($out, [
                    $nome,
                    $p->emprestimo && $p->emprestimo->operacao ? $p->emprestimo->operacao->nome : '',
                    $p->emprestimo && $p->emprestimo->consultor ? $p->emprestimo->consultor->name : '',
                    $p->emprestimo ? $p->numero.'/'.$p->emprestimo->numero_parcelas : (string) $p->numero,
                    $p->data_vencimento ? $p->data_vencimento->format('d/m/Y') : '',
                    (string) ($p->dias_na_data_ref ?? ''),
                    number_format((float) $p->valor, 2, ',', '.'),
                    number_format((float) ($p->valor_pago ?? 0), 2, ',', '.'),
                    number_format((float) ($p->saldo_receber ?? 0), 2, ',', '.'),
                    (string) ($p->status ?? ''),
                ], ';');
            }
            $tv = $parcelas->sum('valor');
            $tp = $parcelas->sum(fn ($x) => (float) ($x->valor_pago ?? 0));
            $ts = $parcelas->sum('saldo_receber');
            fputcsv($out, [
                'TOTAIS', '', '', '', '', '',
                number_format((float) $tv, 2, ',', '.'),
                number_format((float) $tp, 2, ',', '.'),
                number_format((float) $ts, 2, ',', '.'),
                '',
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParcelasAtrasadasDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
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

        $dataRefCarbon = Carbon::parse($dataRef);
        $parcelas->each(function ($parcela) use ($dataRefCarbon) {
            $parcela->dias_na_data_ref = $parcela->data_vencimento->diffInDays($dataRefCarbon);
            $parcela->saldo_receber = (float) $parcela->valor - (float) ($parcela->valor_pago ?? 0);
        });

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

        return compact(
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
        );
    }

    /**
     * Relatório: Quitações (empréstimos finalizados no período)
     * Filtros: período, frequência, tipo (quitação total / renovação)
     */
    public function quitacoes(Request $request): View
    {
        return view('relatorios.quitacoes', $this->buildQuitacoesDataset($request));
    }

    public function exportQuitacoes(Request $request): StreamedResponse
    {
        $d = $this->buildQuitacoesDataset($request);
        $map = $d['fichasContatoPorClienteOperacao'] ?? collect();

        return RelatorioCsvStream::download('quitacoes', function ($out) use ($d, $map) {
            fputcsv($out, [
                'ID', 'Cliente', 'Operação', 'Consultor', 'Valor total', 'Total pago bruto', 'Lucro relatório', 'Data quitação', 'Frequência', 'Tipo quitação',
            ], ';');
            foreach ($d['emprestimos'] as $e) {
                $nome = $e->cliente
                    ? ClienteNomeExibicao::fromEmprestimoMap($e, $map)
                    : '';
                $tipoQuit = ((int) ($e->renovacoes_count ?? 0) > 0) ? 'Renovação' : 'Total';
                fputcsv($out, [
                    $e->id,
                    $nome,
                    $e->operacao?->nome ?? '',
                    $e->consultor?->name ?? '',
                    number_format((float) $e->valor_total, 2, ',', '.'),
                    number_format((float) ($e->valor_total_pago_bruto ?? 0), 2, ',', '.'),
                    number_format((float) ($e->lucro_relatorio_quitacao ?? 0), 2, ',', '.'),
                    $e->data_quitacao ? Carbon::parse($e->data_quitacao)->format('d/m/Y') : '',
                    $e->frequencia ? ucfirst((string) $e->frequencia) : '',
                    $tipoQuit,
                ], ';');
            }
            fputcsv($out, [
                '',
                'TOTAIS',
                '',
                '',
                number_format($d['totalPrincipalQuitadoRelatorio'], 2, ',', '.'),
                number_format($d['totalPagoBrutoRelatorio'], 2, ',', '.'),
                number_format($d['totalLucroRelatorioQuitacoes'], 2, ',', '.'),
                '', '', '',
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuitacoesDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
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

        $query->whereRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) BETWEEN ? AND ?',
            [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]
        );

        $emprestimos = $query->orderByRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) DESC'
        )->get();

        $emprestimos->each(function ($e) {
            $e->data_quitacao = $e->parcelas->max('data_pagamento');
            $bruto = round($e->parcelas->sum(fn ($p) => (float) ($p->valor_pago ?? 0)), 2);
            $e->valor_total_pago_bruto = $bruto;
            $e->lucro_relatorio_quitacao = round($bruto - (float) $e->valor_total, 2);
        });

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($emprestimos)
        );

        $totalPrincipalQuitadoRelatorio = round($emprestimos->sum(fn ($e) => (float) $e->valor_total), 2);
        $totalPagoBrutoRelatorio = round($emprestimos->sum(fn ($e) => (float) ($e->valor_total_pago_bruto ?? 0)), 2);
        $totalLucroRelatorioQuitacoes = round($totalPagoBrutoRelatorio - $totalPrincipalQuitadoRelatorio, 2);

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'frequencia',
            'tipoQuitacao',
            'emprestimos',
            'fichasContatoPorClienteOperacao',
            'totalPrincipalQuitadoRelatorio',
            'totalPagoBrutoRelatorio',
            'totalLucroRelatorioQuitacoes'
        );
    }

    /**
     * Relatório: A receber por cliente (vencimento no período).
     * Baseado em parcelas não pagas de empréstimos ativos.
     */
    public function receberPorCliente(Request $request): View
    {
        return view('relatorios.receber-por-cliente', $this->buildReceberPorClienteDataset($request));
    }

    public function exportReceberPorCliente(Request $request): StreamedResponse
    {
        $d = $this->buildReceberPorClienteDataset($request);

        return RelatorioCsvStream::download('receber_por_cliente', function ($out) use ($d) {
            fputcsv($out, [
                'Cliente', 'Documento', 'Total a receber no período', 'Contrato sem juros', 'Principal (com juros)', 'Somente juros',
            ], ';');
            foreach ($d['rows'] as $r) {
                fputcsv($out, [
                    $r->cliente_nome,
                    $r->cliente_documento,
                    number_format((float) $r->total_a_receber_periodo, 2, ',', '.'),
                    number_format((float) $r->parcela_sem_juros_contrato_no_periodo, 2, ',', '.'),
                    number_format((float) $r->principal_com_juros_no_periodo, 2, ',', '.'),
                    number_format((float) $r->somente_juros_no_periodo, 2, ',', '.'),
                ], ';');
            }
            $t = $d['totais'];
            fputcsv($out, [
                'TOTAIS',
                '',
                number_format($t['total_a_receber_periodo'], 2, ',', '.'),
                number_format($t['parcela_sem_juros_contrato_no_periodo'], 2, ',', '.'),
                number_format($t['principal_com_juros_no_periodo'], 2, ',', '.'),
                number_format($t['somente_juros_no_periodo'], 2, ',', '.'),
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReceberPorClienteDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);

        $consultoresIds = $request->input('consultor_id', []);
        if (! is_array($consultoresIds)) {
            $consultoresIds = $consultoresIds ? [$consultoresIds] : [];
        }
        $consultoresIds = array_values(array_filter(array_map('intval', $consultoresIds)));
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

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'consultores',
            'consultoresIds',
            'somenteSemJuros',
            'rows',
            'totais'
        );
    }

    /**
     * Relatório: Cálculo de comissões por consultor
     * Filtros: período, operação. Lista consultores com bases (valor quitado, juros recebidos) e permite escolher tipo de comissão + taxa % para calcular.
     */
    public function comissoes(Request $request): View
    {
        return view('relatorios.comissoes', $this->buildComissoesDataset($request));
    }

    public function exportComissoes(Request $request): StreamedResponse
    {
        $d = $this->buildComissoesDataset($request);

        return RelatorioCsvStream::download('comissoes', function ($out) use ($d) {
            fputcsv($out, ['Consultor', 'Valor quitado (principal)', 'Juros recebidos'], ';');
            $sq = 0.0;
            $sj = 0.0;
            foreach ($d['consultoresComTotais'] as $row) {
                fputcsv($out, [
                    $row['nome'],
                    number_format((float) $row['valor_quitado'], 2, ',', '.'),
                    number_format((float) $row['juros_recebidos'], 2, ',', '.'),
                ], ';');
                $sq += (float) $row['valor_quitado'];
                $sj += (float) $row['juros_recebidos'];
            }
            fputcsv($out, ['TOTAIS', number_format($sq, 2, ',', '.'), number_format($sj, 2, ',', '.')], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComissoesDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
        $frequencia = self::normalizarFrequenciaComissoes($request->input('frequencia'));

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

        $query = Pagamento::with(self::withPagamentoParaReparticaoRelatorio())
            ->whereBetween('data_pagamento', [$dateFrom, $dateTo]);

        $this->aplicarFiltroComissoesParcelaEmprestimo($query, $user, $operacaoId, $operacoesIds, $frequencia);

        $pagamentos = $query->get();

        foreach ($pagamentos as $p) {
            $consultorId = $p->consultor_id;
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

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'frequencia',
            'consultoresComTotais'
        );
    }

    /**
     * Tela de detalhe: empréstimos que compõem a linha do consultor no relatório de comissões (mesmo período, filtros e regra de repartição).
     */
    public function comissoesDetalheConsultor(Request $request): View
    {
        return view('relatorios.comissoes-detalhe', $this->buildComissoesDetalheConsultorDataset($request));
    }

    public function exportComissoesDetalheConsultor(Request $request): StreamedResponse
    {
        $d = $this->buildComissoesDetalheConsultorDataset($request);

        return RelatorioCsvStream::download('comissoes_detalhe', function ($out) use ($d) {
            fputcsv($out, [
                'Empréstimo ID', 'Cliente', 'Consultor', 'Valor total contrato', 'Total pago período', 'Valor quitado', 'Juros recebidos', 'Data quitação',
            ], ';');
            foreach ($d['linhas'] as $linha) {
                fputcsv($out, [
                    $linha['emprestimo_id'],
                    $linha['cliente_nome'],
                    $linha['consultor_nome'],
                    number_format((float) $linha['valor_total'], 2, ',', '.'),
                    number_format((float) $linha['total_pago_periodo'], 2, ',', '.'),
                    number_format((float) $linha['valor_quitado'], 2, ',', '.'),
                    number_format((float) $linha['juros_recebidos'], 2, ',', '.'),
                    $linha['data_quitacao'] ?? '',
                ], ';');
            }
            $t = $d['totais'];
            fputcsv($out, [
                'TOTAIS',
                '',
                '',
                number_format((float) $t['soma_valor_total_contratos'], 2, ',', '.'),
                number_format((float) $t['soma_total_pago_periodo'], 2, ',', '.'),
                number_format((float) $t['soma_valor_quitado'], 2, ',', '.'),
                number_format((float) $t['soma_juros_recebidos'], 2, ',', '.'),
                '',
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildComissoesDetalheConsultorDataset(Request $request): array
    {
        $user = auth()->user();

        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            abort(403, 'Acesso negado.');
        }

        $consultorId = (int) $request->query('consultor_id', 0);
        if ($consultorId <= 0) {
            abort(404);
        }

        $dateFrom = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        if ($dateFrom->gt($dateTo)) {
            $dateTo = $dateFrom->copy()->endOfDay();
        }

        $operacoesIds = $user->isSuperAdmin()
            ? Operacao::where('ativo', true)->pluck('id')->toArray()
            : $user->getOperacoesIds();
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
        $frequencia = self::normalizarFrequenciaComissoes($request->input('frequencia'));
        $quitacaoTotalPorDataQuitacao = $request->boolean('quitacao_total_periodo_quitacao');

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $consultoresPermitidos = $this->getConsultoresParaRelatorio($operacaoId, $operacoesIds, $user);
        $naLista = $consultoresPermitidos->contains(fn ($c) => (int) $c->id === $consultorId);

        $consultor = \App\Models\User::find($consultorId);
        if (! $consultor) {
            abort(404);
        }

        $query = Pagamento::with(self::withPagamentoParaReparticaoRelatorio())
            ->where('consultor_id', $consultorId);

        if ($quitacaoTotalPorDataQuitacao) {
            $this->aplicarFiltroComissoesQuitacaoTotalPorDataQuitacaoNoPeriodo($query, $dateFrom, $dateTo);
        } else {
            $query->whereBetween('data_pagamento', [$dateFrom, $dateTo]);
        }

        $this->aplicarFiltroComissoesParcelaEmprestimo($query, $user, $operacaoId, $operacoesIds, $frequencia);

        $pagamentos = $query->get();

        if (! $naLista && $pagamentos->isEmpty()) {
            abort(403, 'Consultor não disponível para o seu acesso.');
        }

        $detalhe = $this->montarDetalheComissoesEmprestimos($pagamentos);
        $linhas = $detalhe['linhas'];
        $totais = $detalhe['totais'];

        $consultorNome = $consultor->name.($consultorId === $user->id ? ' (Você)' : '');

        $filtrosVoltar = array_filter([
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'operacao_id' => $operacaoId,
            'frequencia' => $frequencia !== '' ? $frequencia : null,
        ], fn ($v) => $v !== null && $v !== '');

        $urlVoltarComissoes = route('relatorios.comissoes', $filtrosVoltar);

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'frequencia',
            'consultorId',
            'consultorNome',
            'linhas',
            'totais',
            'urlVoltarComissoes',
            'quitacaoTotalPorDataQuitacao'
        );
    }

    /**
     * Relatório: valor emprestado (principal) no período, pela data de início do contrato.
     * Inclui empréstimos aprovados, ativos ou finalizados; exclui rascunho, pendente e cancelado.
     */
    public function valorEmprestadoPrincipal(Request $request): View
    {
        return view('relatorios.valor-emprestado-principal', $this->buildValorEmprestadoPrincipalDataset($request));
    }

    public function exportValorEmprestadoPrincipal(Request $request): StreamedResponse
    {
        $d = $this->buildValorEmprestadoPrincipalDataset($request);
        $map = $d['fichasContatoPorClienteOperacao'] ?? collect();
        $metricas = $d['metricasPorEmprestimoId'];

        return RelatorioCsvStream::download('valor_emprestado_principal', function ($out) use ($d, $map, $metricas) {
            fputcsv($out, [
                'ID', 'Cliente', 'Operação', 'Consultor', 'Data início', 'Principal', 'Juros contrato', 'Total a pagar', 'Total pago', 'Saldo devedor', 'Status',
            ], ';');
            foreach ($d['emprestimos'] as $e) {
                $m = $metricas[$e->id] ?? ['juros_contrato' => 0, 'total_a_pagar' => 0, 'total_pago' => 0, 'saldo_devedor' => 0];
                $nome = $e->cliente ? ClienteNomeExibicao::fromEmprestimoMap($e, $map) : '';
                fputcsv($out, [
                    $e->id,
                    $nome,
                    $e->operacao?->nome ?? '',
                    $e->consultor?->name ?? '',
                    $e->data_inicio ? Carbon::parse($e->data_inicio)->format('d/m/Y') : '',
                    number_format((float) $e->valor_total, 2, ',', '.'),
                    number_format((float) $m['juros_contrato'], 2, ',', '.'),
                    number_format((float) ($m['total_a_pagar'] ?? 0), 2, ',', '.'),
                    number_format((float) ($m['total_pago'] ?? 0), 2, ',', '.'),
                    number_format((float) ($m['saldo_devedor'] ?? 0), 2, ',', '.'),
                    (string) $e->status,
                ], ';');
            }
            fputcsv($out, [
                'TOTAIS', '', '', '', '',
                number_format($d['totalPrincipal'], 2, ',', '.'),
                number_format($d['totalJurosContrato'], 2, ',', '.'),
                number_format($d['totalAPagarCronograma'], 2, ',', '.'),
                number_format($d['totalPagoParcelas'], 2, ',', '.'),
                number_format($d['totalSaldoDevedor'], 2, ',', '.'),
                '',
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildValorEmprestadoPrincipalDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);

        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $statusPermitidosRelatorio = ['aprovado', 'ativo', 'finalizado'];
        $statusInput = $request->input('status');
        $statusesFiltro = [];
        if ($statusInput !== null && $statusInput !== '') {
            $candidatos = is_array($statusInput) ? $statusInput : [$statusInput];
            foreach ($candidatos as $s) {
                $s = is_string($s) ? strtolower(trim($s)) : '';
                if ($s !== '' && in_array($s, $statusPermitidosRelatorio, true)) {
                    $statusesFiltro[] = $s;
                }
            }
            $statusesFiltro = array_values(array_unique($statusesFiltro));
        }
        if ($statusesFiltro === []) {
            $statusesFiltro = $statusPermitidosRelatorio;
        }

        $query = Emprestimo::with([
            'cliente',
            'operacao',
            'consultor',
            'parcelas' => fn ($q) => $q->orderBy('numero'),
        ])
            ->whereIn('status', $statusesFiltro)
            ->whereBetween('data_inicio', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (! $user->isSuperAdmin() || $operacaoId) {
            if ($operacaoId) {
                $query->where('operacao_id', $operacaoId);
            } elseif (! empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $emprestimos = $query->orderBy('data_inicio')->orderBy('id')->get();

        $quitacaoService = app(QuitacaoService::class);
        $metricasPorEmprestimoId = [];
        $totalJurosContrato = 0.0;
        $totalPagoParcelas = 0.0;
        $totalSaldoDevedor = 0.0;

        foreach ($emprestimos as $e) {
            $parcelas = $e->parcelas;
            $somaValorParcelas = round((float) $parcelas->sum(fn ($p) => (float) $p->valor), 2);
            $principal = round((float) $e->valor_total, 2);
            $jurosContrato = $parcelas->isEmpty()
                ? 0.0
                : round(max(0.0, $somaValorParcelas - $principal), 2);
            $totalAPagar = round($principal + $jurosContrato, 2);
            $totalPago = round((float) $parcelas->sum(fn ($p) => (float) ($p->valor_pago ?? 0)), 2);
            $saldoDevedor = round((float) $quitacaoService->getSaldoDevedor($e), 2);

            $metricasPorEmprestimoId[$e->id] = [
                'juros_contrato' => $jurosContrato,
                'total_a_pagar' => $totalAPagar,
                'total_pago' => $totalPago,
                'saldo_devedor' => $saldoDevedor,
            ];
            $totalJurosContrato += $jurosContrato;
            $totalPagoParcelas += $totalPago;
            $totalSaldoDevedor += $saldoDevedor;
        }

        $totalPrincipal = round((float) $emprestimos->sum(fn (Emprestimo $e) => (float) $e->valor_total), 2);
        $qtdEmprestimos = $emprestimos->count();

        $totalJurosContrato = round($totalJurosContrato, 2);
        $totalAPagarCronograma = round($totalPrincipal + $totalJurosContrato, 2);
        $totalPagoParcelas = round($totalPagoParcelas, 2);
        $totalSaldoDevedor = round($totalSaldoDevedor, 2);

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($emprestimos)
        );

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'emprestimos',
            'totalPrincipal',
            'qtdEmprestimos',
            'fichasContatoPorClienteOperacao',
            'statusPermitidosRelatorio',
            'statusesFiltro',
            'metricasPorEmprestimoId',
            'totalJurosContrato',
            'totalAPagarCronograma',
            'totalPagoParcelas',
            'totalSaldoDevedor'
        );
    }

    /**
     * Relatório: Entradas e saídas por categoria
     * Filtros: período e operação.
     */
    public function entradasSaidasPorCategoria(Request $request): View
    {
        return view('relatorios.entradas-saidas-categoria', $this->buildEntradasSaidasPorCategoriaDataset($request));
    }

    public function exportEntradasSaidasPorCategoria(Request $request): StreamedResponse
    {
        $d = $this->buildEntradasSaidasPorCategoriaDataset($request);

        return RelatorioCsvStream::download('entradas_saidas_categoria', function ($out) use ($d) {
            fputcsv($out, ['Categoria', 'Entradas', 'Saídas'], ';');
            foreach ($d['porCategoria'] as $row) {
                fputcsv($out, [
                    $row['nome'],
                    number_format((float) $row['entradas'], 2, ',', '.'),
                    number_format((float) $row['saidas'], 2, ',', '.'),
                ], ';');
            }
            fputcsv($out, [
                'TOTAIS',
                number_format($d['totalEntradas'], 2, ',', '.'),
                number_format($d['totalSaidas'], 2, ',', '.'),
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEntradasSaidasPorCategoriaDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);

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

        uasort($porCategoria, fn ($a, $b) => strcasecmp($a['nome'], $b['nome']));

        return compact(
            'dateFrom',
            'dateTo',
            'operacoes',
            'operacaoId',
            'porCategoria',
            'totalEntradas',
            'totalSaidas'
        );
    }

    /**
     * Relatório: Juros e Valores por Quitação
     * Mostra empréstimos finalizados com detalhamento de juros contratuais e de atraso.
     */
    public function jurosQuitacoes(Request $request): View
    {
        return view('relatorios.juros-quitacoes', $this->buildJurosQuitacoesDataset($request));
    }

    public function exportJurosQuitacoes(Request $request): StreamedResponse
    {
        $d = $this->buildJurosQuitacoesDataset($request);
        $map = $d['fichasContatoPorClienteOperacao'] ?? collect();

        return RelatorioCsvStream::download('juros_quitacoes', function ($out) use ($d, $map) {
            fputcsv($out, [
                'Cliente', 'Operação', 'Tipo', 'Frequência', 'Emprestado', 'Recebido', 'Juros contrato', 'Juros atraso', 'Total juros', 'Data quitação',
            ], ';');
            foreach ($d['emprestimos'] as $e) {
                $nome = $e->cliente ? ClienteNomeExibicao::fromEmprestimoMap($e, $map) : '';
                fputcsv($out, [
                    $nome,
                    $e->operacao?->nome ?? '',
                    $e->tipo_label ?? '',
                    $e->frequencia_label ?? '',
                    number_format((float) $e->valor_emprestado, 2, ',', '.'),
                    number_format((float) $e->valor_recebido, 2, ',', '.'),
                    number_format((float) $e->juros_contrato, 2, ',', '.'),
                    number_format((float) $e->juros_atraso, 2, ',', '.'),
                    number_format((float) $e->total_juros, 2, ',', '.'),
                    $e->data_quitacao ? Carbon::parse($e->data_quitacao)->format('d/m/Y') : '',
                ], ';');
            }
            $t = $d['totais'];
            fputcsv($out, [
                'TOTAIS', '', '', '',
                number_format((float) $t['valor_emprestado'], 2, ',', '.'),
                number_format((float) $t['valor_recebido'], 2, ',', '.'),
                number_format((float) $t['juros_contrato'], 2, ',', '.'),
                number_format((float) $t['juros_atraso'], 2, ',', '.'),
                number_format((float) $t['total_juros'], 2, ',', '.'),
                '',
            ], ';');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJurosQuitacoesDataset(Request $request): array
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
        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoesIds, $user);
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

        $query->whereRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) BETWEEN ? AND ?',
            [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]
        );

        $emprestimos = $query->orderByRaw(
            '(SELECT MAX(p.data_pagamento) FROM parcelas p WHERE p.emprestimo_id = emprestimos.id AND p.deleted_at IS NULL) DESC'
        )->get();

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

        $totais['valor_emprestado'] = round($totais['valor_emprestado'], 2);
        $totais['valor_recebido'] = round($totais['valor_recebido'], 2);
        $totais['juros_contrato'] = round($totais['juros_contrato'], 2);
        $totais['juros_atraso'] = round($totais['juros_atraso'], 2);
        $totais['total_juros'] = round($totais['total_juros'], 2);

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($emprestimos)
        );

        return compact(
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
        );
    }
}
