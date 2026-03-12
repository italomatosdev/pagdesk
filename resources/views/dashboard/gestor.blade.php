@extends('layouts.master')
@section('title')
    Dashboard - Gestor
@endsection
@section('page-title')
    Dashboard - Gestor
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
                                <label for="operacao_id" class="form-label">Operação</label>
                                <select name="operacao_id" id="operacao_id" class="form-select">
                                    <option value="">Todas as Operações</option>
                                    @foreach($operacoes as $operacao)
                                        <option value="{{ $operacao->id }}" {{ request('operacao_id') == $operacao->id ? 'selected' : '' }}>
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
            <!-- Liberações Pendentes -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="font-size-15">Liberações Pendentes</h6>
                                <h4 class="mt-3 pt-1 mb-0 font-size-22">{{ number_format($stats['liberacoes_pendentes'], 0, ',', '.') }}</h4>
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
                                <h6 class="mb-0 font-size-15">Valor Pendente</h6>
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

            <!-- Parcelas Vencidas -->
            <div class="col-md-6 col-xl-3 mb-3">
                <a href="{{ route('parcelas.atrasadas') }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0 font-size-15">Parcelas Vencidas</h6>
                                    <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['parcelas_vencidas'], 0, ',', '.') }}</h4>
                                </div>
                                <div class="">
                                    <div class="avatar">
                                        <div class="avatar-title rounded bg-danger-subtle">
                                            <i class="bx bx-calendar-x font-size-24 mb-0 text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
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

        <div class="row">
            <!-- Valor Total a Receber -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Total a Receber</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_a_receber'], 2, ',', '.') }}</h4>
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

            <!-- Valor Liberado Hoje -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Liberado Hoje</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_liberado_hoje'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-success-subtle">
                                        <i class="bx bx-check font-size-24 mb-0 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Liberado Esta Semana -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Liberado (Semana)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_liberado_semana'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-calendar font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
        </div>
        <!-- END ROW -->

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
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-start">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-0">Liberações Aguardando</h5>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="{{ route('liberacoes.index') }}" class="btn btn-sm btn-primary">
                                    Ver Todas
                                </a>
                            </div>
                        </div>
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
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($liberacoesPendentes as $liberacao)
                                        <tr>
                                            <td>#{{ $liberacao->id }}</td>
                                            <td>{{ $liberacao->emprestimo->cliente->nome }}</td>
                                            <td>{{ $liberacao->consultor->name }}</td>
                                            <td>R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}</td>
                                            <td>{{ $liberacao->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <a href="{{ route('liberacoes.index') }}" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="bx bx-check"></i> Liberar
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhuma liberação pendente.</td>
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
                        <h5 class="card-title mb-0">Empréstimos Aprovados Recentemente</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="mx-n4" data-simplebar style="max-height: 400px;">
                            @forelse($emprestimosAprovados as $emprestimo)
                                <div class="border-bottom p-2">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="font-size-14 mb-1">{{ $emprestimo->cliente->nome }}</h6>
                                            <p class="text-muted mb-0 font-size-12">
                                                R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}
                                            </p>
                                            <small class="text-muted">
                                                {{ $emprestimo->consultor->name ?? '-' }} - 
                                                {{ $emprestimo->aprovado_em ? $emprestimo->aprovado_em->format('d/m/Y') : 'Aprovado automaticamente' }}
                                            </small>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <a href="{{ route('emprestimos.show', $emprestimo->id) }}" 
                                               class="btn btn-sm btn-info">
                                                <i class="bx bx-show"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center p-3">
                                    <p class="text-muted mb-0">Nenhum empréstimo aprovado recentemente.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

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
                                            <td>{{ $parcela->emprestimo->cliente->nome }}</td>
                                            <td>R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-danger">{{ $parcela->dias_atraso }} dias</span>
                                            </td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bx bx-show"></i>
                                                </a>
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
    @endsection
    @section('scripts')
        @if(($stats['total_cheques'] ?? 0) > 0 && !empty($chequesPorStatus))
        <script src="{{ URL::asset('build/libs/apexcharts/apexcharts.min.js') }}"></script>
        @endif
        <script>
            document.addEventListener('DOMContentLoaded', function() {
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
