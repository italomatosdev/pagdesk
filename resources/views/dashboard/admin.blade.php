@extends('layouts.master')
@section('title')
    Dashboard - Administrador
@endsection
@section('page-title')
    Dashboard - Administrador
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Filtro de Operação -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('dashboard.index') }}" class="d-flex flex-wrap gap-3 align-items-end">
                            <div>
                                <label for="date_from" class="form-label">Data inicial</label>
                                <input type="date" name="date_from" id="date_from" class="form-control"
                                    value="{{ $dateFrom->format('Y-m-d') }}">
                            </div>
                            <div>
                                <label for="date_to" class="form-label">Data final</label>
                                <input type="date" name="date_to" id="date_to" class="form-control"
                                    value="{{ $dateTo->format('Y-m-d') }}">
                            </div>
                            <div class="flex-grow-1">
                                @php
                                    $operacaoIdSelecionadaDash = request()->has('operacao_id')
                                        ? (request()->filled('operacao_id') ? (int) request('operacao_id') : null)
                                        : ($operacaoId ?? null);
                                @endphp
                                <label for="operacao_id" class="form-label">Operação</label>
                                <select name="operacao_id" id="operacao_id" class="form-select">
                                    <option value="" {{ $operacaoIdSelecionadaDash === null ? 'selected' : '' }}>Todas as Operações</option>
                                    @foreach($operacoes as $operacao)
                                        <option value="{{ $operacao->id }}" {{ (int) $operacaoIdSelecionadaDash === (int) $operacao->id ? 'selected' : '' }}>
                                            {{ $operacao->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-filter"></i> Filtrar
                                </button>
                                @if(request('operacao_id') || request('date_from') || request('date_to'))
                                    <a href="{{ route('dashboard.index') }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                @endif
                            </div>
                        </form>
                        <small class="text-muted mt-2 d-block">Período: {{ $dateFrom->format('d/m/Y') }} a {{ $dateTo->format('d/m/Y') }}. Máximo 1 ano.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Total de Clientes -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="font-size-15">Total de Clientes</h6>
                                <h4 class="mt-3 pt-1 mb-0 font-size-22">{{ number_format($stats['total_clientes'], 0, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-user font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total de Empréstimos -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border shadow-sm overflow-hidden">
                    <div class="card-body d-flex flex-column pb-2 px-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="pe-2">
                                <h6 class="mb-0 font-size-15">Total de Empréstimos</h6>
                                <h4 class="mt-2 mb-0 font-size-22">{{ number_format($stats['total_emprestimos'], 0, ',', '.') }}</h4>
                                <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Criados no período · por status</small>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-success-subtle">
                                        <i class="bx bx-money font-size-24 mb-0 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                                @php
                                    $labelsStatusEmp = [
                                        'draft' => 'Rascunho',
                                        'pendente' => 'Pendentes',
                                        'aguardando_aceite_retroativo' => 'Aguard. retroativo',
                                        'aprovado' => 'Aprovados',
                                        'ativo' => 'Ativos',
                                        'finalizado' => 'Finalizados',
                                        'cancelado' => 'Cancelados',
                                    ];
                                    $corStatusEmp = [
                                        'draft' => 'secondary',
                                        'pendente' => 'warning',
                                        'aguardando_aceite_retroativo' => 'info',
                                        'aprovado' => 'primary',
                                        'ativo' => 'success',
                                        'finalizado' => 'dark',
                                        'cancelado' => 'danger',
                                    ];
                                    $porStatus = is_array($emprestimosPorStatus ?? null)
                                        ? $emprestimosPorStatus
                                        : ($emprestimosPorStatus ?? collect())->toArray();
                                    $tilesStatusEmp = [];
                                    foreach ($labelsStatusEmp as $st => $label) {
                                        $tilesStatusEmp[] = [
                                            'label' => $label,
                                            'n' => (int) ($porStatus[$st] ?? 0),
                                            'cor' => $corStatusEmp[$st] ?? 'secondary',
                                        ];
                                    }
                                    foreach ($porStatus as $st => $n) {
                                        if (! array_key_exists($st, $labelsStatusEmp) && (int) $n > 0) {
                                            $tilesStatusEmp[] = [
                                                'label' => ucfirst(str_replace('_', ' ', $st)),
                                                'n' => (int) $n,
                                                'cor' => 'secondary',
                                            ];
                                        }
                                    }
                                    usort($tilesStatusEmp, fn ($a, $b) => $b['n'] <=> $a['n']);
                                @endphp
                        <div class="dashboard-emp-status-scroll-horizontal mt-2 pt-2 border-top mx-n3 px-3">
                            <div class="dashboard-emp-status-strip d-flex flex-nowrap gap-2 align-items-stretch pb-1">
                                        @foreach($tilesStatusEmp as $tile)
                                            <div class="dashboard-status-tile flex-shrink-0 rounded-3 px-2 py-1 text-center border-bottom border-{{ $tile['cor'] }} border-3 bg-body-secondary bg-opacity-10"
                                                 style="min-width: 4.25rem; max-width: 6.5rem;"
                                                 title="{{ $tile['label'] }}: {{ $tile['n'] }} empréstimo(s)">
                                                <div class="fw-bold text-{{ $tile['cor'] }} font-size-16 lh-1">{{ number_format($tile['n'], 0, ',', '.') }}</div>
                                                <div class="text-truncate text-muted mt-1" style="font-size: 0.62rem; line-height: 1.1;">{{ $tile['label'] }}</div>
                                            </div>
                                        @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empréstimos Pendentes -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Pendentes de Aprovação</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['emprestimos_pendentes'], 0, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-time font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Total Emprestado -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Total Emprestado</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_emprestado'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-trending-up font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <!-- Valor Total Recebido -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Total Recebido</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_recebido'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-success-subtle">
                                        <i class="bx bx-check-circle font-size-24 mb-0 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taxa de Inadimplência -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa de Inadimplência</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['taxa_inadimplencia'], 2, ',', '.') }}%</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-danger-subtle">
                                        <i class="bx bx-error font-size-24 mb-0 text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Médio por Empréstimo -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Médio/Empréstimo</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_medio_emprestimo'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-calculator font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taxa de Aprovação -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa de Aprovação</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['taxa_aprovacao'], 2, ',', '.') }}%</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-check font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <!-- Clientes Novos Este Mês -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Clientes Novos (Mês)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['clientes_novos_mes'], 0, ',', '.') }}</h4>
                                @if($stats['clientes_crescimento'] != 0)
                                    <small class="text-{{ $stats['clientes_crescimento'] > 0 ? 'success' : 'danger' }}">
                                        <i class="bx bx-{{ $stats['clientes_crescimento'] > 0 ? 'trending-up' : 'trending-down' }}"></i>
                                        {{ number_format(abs($stats['clientes_crescimento']), 2, ',', '.') }}%
                                    </small>
                                @endif
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-success-subtle">
                                        <i class="bx bx-user-plus font-size-24 mb-0 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <!-- Métricas do Gestor -->
        <div class="row">
            <!-- Liberações Pendentes -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Liberações Pendentes</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['liberacoes_pendentes'], 0, ',', '.') }}</h4>
                                <small class="text-muted">{{ $stats['liberacoes_hoje'] }} hoje</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-time font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Pendente de Liberação -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Pendente Liberação</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_pendente_liberacao'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-money font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Total a Receber -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Total a Receber</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_a_receber'], 2, ',', '.') }}</h4>
                                <div class="mt-2 pt-2 border-top border-light small text-muted">
                                    <div>Total emprestado: R$ {{ number_format($stats['valor_total_emprestado_a_receber'] ?? 0, 2, ',', '.') }}</div>
                                    <div>Total a receber de juros: R$ {{ number_format($stats['valor_total_juros_a_receber'] ?? 0, 2, ',', '.') }}</div>
                                </div>
                                <div class="mt-2 pt-2 border-top border-light small text-muted">
                                    <div class="fw-semibold text-body mb-1">No período ({{ $stats['receber_mes_label'] ?? '' }} — vencimento)</div>
                                    <div>Total no período: R$ {{ number_format($stats['receber_mes_total'] ?? 0, 2, ',', '.') }}</div>
                                    <div>Contrato sem juros: R$ {{ number_format($stats['receber_mes_sem_juros_contrato'] ?? 0, 2, ',', '.') }}</div>
                                    <div>Principal (contratos com juros): R$ {{ number_format($stats['receber_mes_principal_com_juros'] ?? 0, 2, ',', '.') }}</div>
                                    <div>Juros no período: R$ {{ number_format($stats['receber_mes_juros'] ?? 0, 2, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-wallet font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Parcelas Vencidas -->
            <div class="col-md-6 col-xl-3 mb-3">
                <a href="{{ route('parcelas.atrasadas') }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0 font-size-15">Valor em Atraso</h6>
                                    <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_parcelas_vencidas'], 2, ',', '.') }}</h4>
                                </div>
                                <div class="">
                                    <div class="avatar">
                                        <div class="avatar-title rounded bg-danger-subtle">
                                            <i class="bx bx-error font-size-24 mb-0 text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <!-- END ROW -->

        <!-- EMPENHOS ROW -->
        @if(($stats['total_empenhos'] ?? 0) > 0 || ($stats['total_garantias'] ?? 0) > 0)
        <div class="row">
            <div class="col-12 mb-2">
                <h5 class="text-warning"><i class="bx bx-shield-quarter"></i> Empréstimos Empenho</h5>
            </div>
            
            <!-- Total de Empenhos -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Total de Empenhos</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['total_empenhos'] ?? 0, 0, ',', '.') }}</h4>
                                <small class="text-muted">{{ $stats['empenhos_ativos'] ?? 0 }} ativos</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-shield-quarter font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Total em Empenhos -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor em Empenhos</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_empenhos'] ?? 0, 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-money font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total de Garantias -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Total de Garantias</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['total_garantias'] ?? 0, 0, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-package font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Total das Garantias -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor das Garantias</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_garantias'] ?? 0, 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-diamond font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END EMPENHOS ROW -->
        @endif

        <!-- CHEQUES ROW (Troca de Cheque) -->
        @if(($stats['total_cheques'] ?? 0) > 0)
        <div class="row">
            <div class="col-12 mb-2">
                <h5 class="text-info"><i class="bx bx-money"></i> Cheques (Troca de Cheque)</h5>
            </div>

            <!-- Total de Cheques -->
            <div class="col-md-6 col-xl-3 mb-3">
                <a href="{{ route('cheques.index') }}" class="text-decoration-none">
                    <div class="card h-100 border-info">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0 font-size-15">Total de Cheques</h6>
                                    <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['total_cheques'] ?? 0, 0, ',', '.') }}</h4>
                                </div>
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-receipt font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Valor Bruto dos Cheques -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Bruto</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_bruto_cheques'] ?? 0, 2, ',', '.') }}</h4>
                            </div>
                            <div class="avatar">
                                <div class="avatar-title rounded bg-info-subtle">
                                    <i class="bx bx-money font-size-24 mb-0 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Líquido dos Cheques -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Líquido</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_liquido_cheques'] ?? 0, 2, ',', '.') }}</h4>
                            </div>
                            <div class="avatar">
                                <div class="avatar-title rounded bg-success-subtle">
                                    <i class="bx bx-check-circle font-size-24 mb-0 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cheques Vencendo Hoje -->
            <div class="col-md-6 col-xl-3 mb-3">
                <a href="{{ route('cheques.hoje') }}" class="text-decoration-none">
                    <div class="card h-100 border-info">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0 font-size-15">Vencendo Hoje</h6>
                                    <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['cheques_vencidos_hoje'] ?? 0, 0, ',', '.') }}</h4>
                                </div>
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-calendar font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Gráfico Cheques por Status -->
            <div class="col-xl-6 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Cheques por Status</h5>
                        <a href="{{ route('cheques.index') }}" class="btn btn-sm btn-info">Ver todos</a>
                    </div>
                    <div class="card-body">
                        <div id="cheques-status-chart" style="min-height: 280px;"></div>
                        <div class="mt-2 row text-center small">
                            <div class="col"><span class="badge bg-primary">Aguardando {{ $stats['cheques_aguardando'] ?? 0 }}</span></div>
                            <div class="col"><span class="badge bg-info">Depositado {{ $stats['cheques_depositado'] ?? 0 }}</span></div>
                            <div class="col"><span class="badge bg-success">Compensado {{ $stats['cheques_compensado'] ?? 0 }}</span></div>
                            <div class="col"><span class="badge bg-danger">Devolvido {{ $stats['cheques_devolvido'] ?? 0 }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END CHEQUES ROW -->
        @endif

        <div class="row">
            <!-- Valor Recebido Hoje -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Recebido Hoje</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_recebido_hoje'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-success-subtle">
                                        <i class="bx bx-check-circle font-size-24 mb-0 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Recebido Esta Semana -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Recebido (Semana)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_recebido_semana'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-calendar font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Recebido Este Mês -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Recebido (Mês)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_recebido_mes'], 2, ',', '.') }}</h4>
                                @if($stats['crescimento_mensal'] != 0)
                                    <small class="text-{{ $stats['crescimento_mensal'] > 0 ? 'success' : 'danger' }}">
                                        <i class="bx bx-{{ $stats['crescimento_mensal'] > 0 ? 'trending-up' : 'trending-down' }}"></i>
                                        {{ number_format(abs($stats['crescimento_mensal']), 2, ',', '.') }}%
                                    </small>
                                @endif
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-money font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Liberado Este Mês -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Liberado (Mês)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_liberado_mes'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-check font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <!-- Taxa de Recuperação -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa de Recuperação</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['taxa_recuperacao'], 2, ',', '.') }}%</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-trending-up font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taxa de Pagamento ao Cliente -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa Pagamento Cliente</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['taxa_pagamento_cliente'], 2, ',', '.') }}%</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-{{ $stats['taxa_pagamento_cliente'] >= 80 ? 'success' : ($stats['taxa_pagamento_cliente'] >= 50 ? 'warning' : 'danger') }}-subtle">
                                        <i class="bx bx-user-check font-size-24 mb-0 text-{{ $stats['taxa_pagamento_cliente'] >= 80 ? 'success' : ($stats['taxa_pagamento_cliente'] >= 50 ? 'warning' : 'danger') }}"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tempo Médio de Liberação -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Tempo Médio Liberação</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['tempo_medio_liberacao'], 1, ',', '.') }}h</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-time-five font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fluxo de Caixa -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Fluxo de Caixa</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['fluxo_caixa'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-{{ $stats['fluxo_caixa'] >= 0 ? 'success' : 'danger' }}-subtle">
                                        <i class="bx bx-{{ $stats['fluxo_caixa'] >= 0 ? 'trending-up' : 'trending-down' }} font-size-24 mb-0 text-{{ $stats['fluxo_caixa'] >= 0 ? 'success' : 'danger' }}"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <!-- Projeção de Recebimentos -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Projeção (7 dias)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['projecao_recebimentos'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-calendar-check font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Empréstimos Recentes</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Consultor</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emprestimosRecentes as $emprestimo)
                                        <tr>
                                            <td>#{{ $emprestimo->id }}</td>
                                            <td>{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>
                                                @php
                                                    $badgeClass = match($emprestimo->status) {
                                                        'ativo' => 'success',
                                                        'pendente' => 'warning',
                                                        'aprovado' => 'info',
                                                        'finalizado' => 'secondary',
                                                        'cancelado' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ ucfirst($emprestimo->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $emprestimo->consultor->name }}</td>
                                            <td>{{ $emprestimo->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emprestimo->id) }}" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">Nenhum empréstimo encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ações Pendentes</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <!-- Liberações Pendentes -->
                        <div class="mb-3">
                            <h6 class="font-size-14 mb-2">
                                <i class="bx bx-money text-warning"></i> Liberações Pendentes
                            </h6>
                            <p class="mb-1">
                                <strong>{{ $stats['liberacoes_pendentes'] }}</strong> aguardando liberação
                            </p>
                            @if($stats['liberacoes_pendentes'] > 0)
                                <a href="{{ route('liberacoes.index') }}" class="btn btn-sm btn-warning">
                                    Ver Liberações
                                </a>
                            @endif
                        </div>

                        <hr>

                        <!-- Aprovações Pendentes -->
                        <div class="mb-3">
                            <h6 class="font-size-14 mb-2">
                                <i class="bx bx-check-circle text-info"></i> Aprovações Pendentes
                            </h6>
                            <p class="mb-1">
                                <strong>{{ $stats['emprestimos_pendentes'] }}</strong> empréstimos aguardando
                            </p>
                            @if($stats['emprestimos_pendentes'] > 0)
                                <a href="{{ route('aprovacoes.index') }}" class="btn btn-sm btn-info">
                                    Ver Aprovações
                                </a>
                            @endif
                        </div>

                        <hr>

                        <!-- Parcelas Vencidas -->
                        <div class="mb-3">
                            <h6 class="font-size-14 mb-2">
                                <i class="bx bx-calendar-x text-danger"></i> Parcelas Vencidas
                            </h6>
                            <p class="mb-1">
                                <strong>{{ $stats['parcelas_vencidas'] }}</strong> parcelas atrasadas
                            </p>
                            <a href="{{ route('parcelas.atrasadas') }}" class="btn btn-sm btn-danger">
                                Ver Parcelas Atrasadas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Top Consultores</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Consultor</th>
                                        <th>Valor Total</th>
                                        <th>Empréstimos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topConsultores as $item)
                                        <tr>
                                            <td>{{ $item->consultor->name ?? 'N/A' }}</td>
                                            <td>R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                                            <td>{{ $item->quantidade ?? 0 }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center">Nenhum dado disponível.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Empréstimos por Status</h5>
                    </div>
                    <div class="card-body">
                        <div id="emprestimos-status-chart" style="min-height: 300px;"></div>
                        <div class="mt-3">
                            <div class="row text-center">
                                @foreach($emprestimosPorStatus as $status => $total)
                                    <div class="col">
                                        <p class="mb-1 text-muted">{{ ucfirst($status) }}</p>
                                        <h5 class="mb-0">{{ $total }}</h5>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <!-- Ranking de Consultores -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ranking de Consultores (Top 10)</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Posição</th>
                                        <th>Consultor</th>
                                        <th>Valor Emprestado</th>
                                        <th>Valor Recebido</th>
                                        <th>Taxa Recebimento</th>
                                        <th>Empréstimos</th>
                                        <th>Taxa Inadimplência</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rankingConsultores as $index => $item)
                                        <tr>
                                            <td>
                                                @if($index === 0)
                                                    <span class="badge bg-warning">🥇 1º</span>
                                                @elseif($index === 1)
                                                    <span class="badge bg-secondary">🥈 2º</span>
                                                @elseif($index === 2)
                                                    <span class="badge bg-info">🥉 3º</span>
                                                @else
                                                    <strong>{{ $index + 1 }}º</strong>
                                                @endif
                                            </td>
                                            <td>{{ $item['consultor']->name }}</td>
                                            <td>R$ {{ number_format($item['total_emprestado'], 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($item['valor_recebido'], 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item['taxa_recebimento'] >= 80 ? 'success' : ($item['taxa_recebimento'] >= 50 ? 'warning' : 'danger') }}">
                                                    {{ number_format($item['taxa_recebimento'], 2, ',', '.') }}%
                                                </span>
                                            </td>
                                            <td>{{ $item['quantidade_emprestimos'] }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item['taxa_inadimplencia'] <= 10 ? 'success' : ($item['taxa_inadimplencia'] <= 20 ? 'warning' : 'danger') }}">
                                                    {{ number_format($item['taxa_inadimplencia'], 2, ',', '.') }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">Nenhum consultor encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <!-- Consultores com Liberações Não Pagas -->
        @if($consultoresLiberacoesNaoPagas->count() > 0)
        <div class="row">
            <div class="col-xl-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning-subtle">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-error text-warning"></i> 
                            Consultores com Liberações Não Pagas ao Cliente
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Consultor</th>
                                        <th>Quantidade</th>
                                        <th>Valor Total</th>
                                        <th>Tempo Médio (horas)</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($consultoresLiberacoesNaoPagas as $item)
                                        <tr>
                                            <td><strong>{{ $item['consultor']->name }}</strong></td>
                                            <td>{{ $item['quantidade'] }}</td>
                                            <td>R$ {{ number_format($item['valor_total'], 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item['tempo_medio_horas'] > 48 ? 'danger' : ($item['tempo_medio_horas'] > 24 ? 'warning' : 'info') }}">
                                                    {{ number_format($item['tempo_medio_horas'], 1, ',', '.') }}h
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('liberacoes.index') }}" class="btn btn-sm btn-warning">
                                                    <i class="bx bx-show"></i> Ver Detalhes
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->
        @endif

        <!-- Consultores com Alta Inadimplência -->
        @if($consultoresAltaInadimplencia->count() > 0)
        <div class="row">
            <div class="col-xl-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger-subtle">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-error-circle text-danger"></i> 
                            Consultores com Alta Inadimplência (>20%)
                        </h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Consultor</th>
                                        <th>Taxa Inadimplência</th>
                                        <th>Parcelas Vencidas</th>
                                        <th>Valor Emprestado</th>
                                        <th>Taxa Recebimento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($consultoresAltaInadimplencia as $item)
                                        <tr>
                                            <td><strong>{{ $item['consultor']->name }}</strong></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    {{ number_format($item['taxa_inadimplencia'], 2, ',', '.') }}%
                                                </span>
                                            </td>
                                            <td>{{ $item['parcelas_vencidas'] }}</td>
                                            <td>R$ {{ number_format($item['total_emprestado'], 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item['taxa_recebimento'] >= 50 ? 'warning' : 'danger' }}">
                                                    {{ number_format($item['taxa_recebimento'], 2, ',', '.') }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->
        @endif

        <!-- Resumo por Operação -->
        @if($resumoPorOperacao->count() > 0)
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Resumo por Operação</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Operação</th>
                                        <th>Quantidade</th>
                                        <th>Valor Total</th>
                                        <th>Valor Recebido</th>
                                        <th>Taxa Recuperação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($resumoPorOperacao as $item)
                                        <tr>
                                            <td><strong>{{ $item['operacao']->nome }}</strong></td>
                                            <td>{{ $item['quantidade'] }}</td>
                                            <td>R$ {{ number_format($item['valor_total'], 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($item['valor_recebido'], 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item['taxa_recuperacao'] >= 80 ? 'success' : ($item['taxa_recuperacao'] >= 50 ? 'warning' : 'danger') }}">
                                                    {{ number_format($item['taxa_recuperacao'], 2, ',', '.') }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->
        @endif

        <!-- Empréstimos Aprovados Recentemente -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Empréstimos Aprovados Recentemente</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Consultor</th>
                                        <th>Valor</th>
                                        <th>Data Aprovação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emprestimosAprovados as $emprestimo)
                                        <tr>
                                            <td>#{{ $emprestimo->id }}</td>
                                            <td>{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                            <td>{{ $emprestimo->consultor->name ?? '-' }}</td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>{{ $emprestimo->aprovado_em ? $emprestimo->aprovado_em->format('d/m/Y H:i') : '-' }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emprestimo->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhum empréstimo aprovado recentemente.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <!-- Parcelas Vencidas -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Parcelas Vencidas</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Dias Atraso</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($parcelasVencidas as $parcela)
                                        <tr class="table-danger">
                                            <td>{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($parcela, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                            <td>R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                @php
                                                    $diasAtraso = $parcela->dias_atraso ?? $parcela->calcularDiasAtraso();
                                                @endphp
                                                <span class="badge bg-danger">{{ $diasAtraso }} dias</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}"
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @php
                                                        $fichaWaAdm = ($fichasContatoPorClienteOperacao ?? collect())->get($parcela->emprestimo->cliente_id.'_'.$parcela->emprestimo->operacao_id);
                                                    @endphp
                                                    @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaWaAdm, $parcela->emprestimo->cliente))
                                                        <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaWaAdm, $parcela->emprestimo->cliente) }}"
                                                           target="_blank"
                                                           class="btn btn-sm btn-success"
                                                           title="WhatsApp (ficha da operação quando houver)">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Nenhuma parcela vencida.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->
        <style>
            /* Largura fixa da viewport de scroll = card; faixa interna pode ser maior → barra horizontal */
            .dashboard-emp-status-scroll-horizontal {
                display: block;
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
                overscroll-behavior-x: contain;
                scrollbar-width: thin;
                scrollbar-color: rgba(0, 0, 0, 0.25) transparent;
                -webkit-overflow-scrolling: touch;
                touch-action: pan-x;
            }
            .dashboard-emp-status-scroll-horizontal .dashboard-emp-status-strip {
                width: max-content;
                min-height: 2.75rem;
            }
            .dashboard-emp-status-scroll-horizontal::-webkit-scrollbar { height: 6px; }
            .dashboard-emp-status-scroll-horizontal::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.25);
                border-radius: 6px;
            }
            .dashboard-emp-status-scroll-horizontal::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.06);
                border-radius: 6px;
            }
            .dashboard-status-tile {
                transition: transform 0.12s ease, box-shadow 0.12s ease;
            }
            .dashboard-status-tile:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            }
        </style>
    @endsection
    @section('scripts')
        <!-- ApexCharts -->
        <script src="{{ URL::asset('build/libs/apexcharts/apexcharts.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Dados do gráfico de empréstimos por status
                @php
                    $statusArray = is_array($emprestimosPorStatus) ? $emprestimosPorStatus : $emprestimosPorStatus->toArray();
                @endphp
                var statusLabels = @json(array_keys($statusArray));
                var statusValues = @json(array_values($statusArray));
                
                // Formatar labels para primeira letra maiúscula
                statusLabels = statusLabels.map(function(label) {
                    return label.charAt(0).toUpperCase() + label.slice(1);
                });

                // Cores por status
                var statusColors = statusLabels.map(function(status) {
                    switch(status.toLowerCase()) {
                        case 'ativo': return '#28a745';
                        case 'finalizado': return '#17a2b8';
                        case 'pendente': return '#ffc107';
                        case 'aprovado': return '#007bff';
                        case 'cancelado': return '#dc3545';
                        case 'draft': return '#6c757d';
                        default: return '#6c757d';
                    }
                });

                var options = {
                    series: statusValues,
                    chart: {
                        type: 'pie',
                        height: 300,
                    },
                    labels: statusLabels,
                    colors: statusColors,
                    legend: {
                        position: 'bottom',
                        horizontalAlign: 'center'
                    },
                    responsive: [{
                        breakpoint: 480,
                        options: {
                            chart: {
                                width: 280
                            },
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }],
                    tooltip: {
                        y: {
                            formatter: function(value) {
                                return value + ' empréstimo(s)';
                            }
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val, opts) {
                            return opts.w.config.series[opts.seriesIndex];
                        }
                    }
                };

                var chart = new ApexCharts(document.querySelector("#emprestimos-status-chart"), options);
                chart.render();

                // Gráfico de Cheques por Status (se houver dados)
                @if(($stats['total_cheques'] ?? 0) > 0 && !empty($chequesPorStatus))
                var chequesPorStatusData = @json($chequesPorStatus);
                var chequesPorStatusLabels = Object.keys(chequesPorStatusData).map(function(s) {
                    return s.charAt(0).toUpperCase() + s.slice(1);
                });
                var chequesPorStatusValues = Object.values(chequesPorStatusData);
                var chequesStatusEl = document.querySelector("#cheques-status-chart");
                if (chequesStatusEl && chequesPorStatusLabels.length > 0) {
                    var chequesOptions = {
                        series: chequesPorStatusValues,
                        chart: { type: 'pie', height: 280 },
                        labels: chequesPorStatusLabels,
                        colors: ['#0d6efd', '#0dcaf0', '#198754', '#dc3545', '#6c757d'],
                        legend: { position: 'bottom', horizontalAlign: 'center' },
                        responsive: [{ breakpoint: 480, options: { chart: { width: 280 } } }],
                        tooltip: { y: { formatter: function(v) { return v + ' cheque(s)'; } } },
                        dataLabels: { enabled: true }
                    };
                    var chequesChart = new ApexCharts(chequesStatusEl, chequesOptions);
                    chequesChart.render();
                }
                @endif
            });
        </script>
    @endsection
