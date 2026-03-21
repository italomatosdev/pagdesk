@extends('layouts.master')
@section('title')
    Dashboard - Consultor
@endsection
@section('page-title')
    Dashboard - Consultor
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Filtro de Período e Operação -->
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
                            @if($operacoes->isNotEmpty())
                            <div class="flex-grow-1">
                                <label for="operacao_id" class="form-label">Operação</label>
                                <select name="operacao_id" id="operacao_id" class="form-select">
                                    <option value="">Todas as Operações</option>
                                    @foreach($operacoes as $operacao)
                                        <option value="{{ $operacao->id }}" {{ (string)request('operacao_id') === (string)$operacao->id ? 'selected' : '' }}>
                                            {{ $operacao->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
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
            <!-- Meus Empréstimos Ativos -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="font-size-15">Meus Empréstimos Ativos</h6>
                                <h4 class="mt-3 pt-1 mb-0 font-size-22">{{ number_format($stats['meus_emprestimos_ativos'], 0, ',', '.') }}</h4>
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

            <!-- Cobranças de Hoje -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Cobranças de Hoje</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['cobrancas_hoje'], 0, ',', '.') }}</h4>
                                <small class="text-muted">R$ {{ number_format($stats['valor_a_receber_hoje'], 2, ',', '.') }}</small>
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

            <!-- Parcelas Atrasadas -->
            <div class="col-md-6 col-xl-3 mb-3">
                <a href="{{ route('parcelas.atrasadas') }}" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="mb-0 font-size-15">Parcelas Atrasadas</h6>
                                    <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['parcelas_atrasadas'], 0, ',', '.') }}</h4>
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

            <!-- Liberações Pendentes -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Liberações Pendentes</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['minhas_liberacoes_pendentes'], 0, ',', '.') }}</h4>
                                <small class="text-muted">{{ $stats['minhas_liberacoes_liberadas'] }} liberadas</small>
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
                                <h6 class="mb-0 font-size-15">Total a Receber</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['valor_total_a_receber'], 2, ',', '.') }}</h4>
                                <div class="mt-2 pt-2 border-top border-light small text-muted">
                                    <div>Total emprestado: R$ {{ number_format($stats['valor_total_emprestado_a_receber'] ?? 0, 2, ',', '.') }}</div>
                                    <div>Total a receber de juros: R$ {{ number_format($stats['valor_total_juros_a_receber'] ?? 0, 2, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-wallet font-size-24 mb-0 text-warning"></i>
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
            <!-- Saldo em Caixa -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Saldo em Caixa</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($stats['saldo_caixa'], 2, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-{{ $stats['saldo_caixa'] >= 0 ? 'success' : 'danger' }}-subtle">
                                        <i class="bx bx-{{ $stats['saldo_caixa'] >= 0 ? 'wallet' : 'error' }} font-size-24 mb-0 text-{{ $stats['saldo_caixa'] >= 0 ? 'success' : 'danger' }}"></i>
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

            <!-- Taxa de Recebimento -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa de Recebimento</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['taxa_recebimento'], 2, ',', '.') }}%</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-trending-up font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Próximas Cobranças -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Próximas Cobranças (7d)</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($stats['proximas_cobrancas'], 0, ',', '.') }}</h4>
                                <small class="text-muted">R$ {{ number_format($stats['valor_proximas_cobrancas'], 2, ',', '.') }}</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-calendar-check font-size-24 mb-0 text-warning"></i>
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
                        <div class="d-flex align-items-start">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-0">Cobranças de Hoje</h5>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="{{ route('cobrancas.index') }}" class="btn btn-sm btn-primary">
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
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cobrancasHoje as $parcela)
                                        <tr>
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($parcela->emprestimo->cliente_id, $parcela->emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($parcela, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            </td>
                                            <td>R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-warning">{{ ucfirst($parcela->status) }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('pagamentos.create', ['parcela_id' => $parcela->id]) }}" 
                                                       class="btn btn-sm btn-success" title="Registrar Pagamento">
                                                        <i class="bx bx-money"></i> Registrar
                                                    </a>
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Empréstimo">
                                                        <i class="bx bx-show"></i> Ver Empréstimo
                                                    </a>
                                                    @php
                                                        $fichaWaDash = ($fichasContatoPorClienteOperacao ?? collect())->get($parcela->emprestimo->cliente_id.'_'.$parcela->emprestimo->operacao_id);
                                                    @endphp
                                                    @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaWaDash, $parcela->emprestimo->cliente))
                                                        <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaWaDash, $parcela->emprestimo->cliente) }}"
                                                           target="_blank"
                                                           class="btn btn-sm btn-success"
                                                           title="Falar no WhatsApp">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <p class="mb-0">Nenhuma cobrança para hoje! 🎉</p>
                                            </td>
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
                        <h5 class="card-title mb-0">Minhas Liberações</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="mx-n4" data-simplebar style="max-height: 400px;">
                            @forelse($minhasLiberacoes as $liberacao)
                                <div class="border-bottom p-2">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="font-size-14 mb-1">
                                                <a href="{{ \App\Support\ClienteUrl::show($liberacao->emprestimo->cliente_id, $liberacao->emprestimo->operacao_id) }}" class="text-reset">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($liberacao->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            </h6>
                                            <p class="text-muted mb-0 font-size-12">
                                                R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}
                                            </p>
                                            <small class="text-muted">
                                                @php
                                                    $badgeClass = match($liberacao->status) {
                                                        'aguardando' => 'warning',
                                                        'liberado' => 'info',
                                                        'pago_ao_cliente' => 'success',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ ucfirst(str_replace('_', ' ', $liberacao->status)) }}
                                                </span>
                                                - {{ $liberacao->created_at->format('d/m/Y') }}
                                            </small>
                                        </div>
                                        <div class="flex-shrink-0">
                                            @if($liberacao->status === 'liberado')
                                                <a href="{{ route('liberacoes.minhas') }}" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="bx bx-check"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center p-3">
                                    <p class="text-muted mb-0">Nenhuma liberação no momento.</p>
                                </div>
                            @endforelse
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
                        <h5 class="card-title mb-0">Parcelas Atrasadas</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Dias</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($parcelasAtrasadas as $parcela)
                                        <tr class="table-danger">
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($parcela->emprestimo->cliente_id, $parcela->emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($parcela, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            </td>
                                            <td>R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-danger">{{ $parcela->dias_atraso }} dias</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('pagamentos.create', ['parcela_id' => $parcela->id]) }}" 
                                                       class="btn btn-sm btn-success" title="Registrar Pagamento">
                                                        <i class="bx bx-money"></i> Registrar
                                                    </a>
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Empréstimo">
                                                        <i class="bx bx-show"></i> Ver Empréstimo
                                                    </a>
                                                    @php
                                                        $fichaWaDash = ($fichasContatoPorClienteOperacao ?? collect())->get($parcela->emprestimo->cliente_id.'_'.$parcela->emprestimo->operacao_id);
                                                    @endphp
                                                    @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaWaDash, $parcela->emprestimo->cliente))
                                                        <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaWaDash, $parcela->emprestimo->cliente) }}"
                                                           target="_blank"
                                                           class="btn btn-sm btn-success"
                                                           title="Falar no WhatsApp">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Nenhuma parcela atrasada! 🎉</td>
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
                        <h5 class="card-title mb-0">Meus Empréstimos Recentes</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($meusEmprestimos as $emprestimo)
                                        <tr>
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($emprestimo->cliente_id, $emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            </td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>
                                                @php
                                                    $badgeClass = match($emprestimo->status) {
                                                        'ativo' => 'success',
                                                        'pendente' => 'warning',
                                                        'aprovado' => 'info',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ ucfirst($emprestimo->status) }}
                                                </span>
                                            </td>
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
                                            <td colspan="5" class="text-center">Nenhum empréstimo encontrado.</td>
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

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Próximas Cobranças (Próximos 7 Dias)</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($proximasCobrancasLista as $parcela)
                                        <tr>
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($parcela->emprestimo->cliente_id, $parcela->emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($parcela, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            </td>
                                            <td>R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>
                                                @php
                                                    $hoje = \Carbon\Carbon::today();
                                                    $vencimento = $parcela->data_vencimento;
                                                    $dias = $hoje->diffInDays($vencimento, false);
                                                    
                                                    if ($dias < 0) {
                                                        // Atrasada (não deveria aparecer aqui, mas por segurança)
                                                        $badgeClass = 'danger';
                                                        $badgeText = abs($dias) . ' dias atrás';
                                                    } elseif ($dias == 0) {
                                                        $badgeClass = 'danger';
                                                        $badgeText = 'Hoje';
                                                    } elseif ($dias == 1) {
                                                        $badgeClass = 'warning';
                                                        $badgeText = 'Amanhã';
                                                    } elseif ($dias >= 2 && $dias <= 6) {
                                                        $badgeClass = 'info';
                                                        $badgeText = 'Em ' . $dias . ' dias';
                                                    } elseif ($dias == 7) {
                                                        $badgeClass = 'secondary';
                                                        $badgeText = 'Em 1 semana';
                                                    } else {
                                                        $badgeClass = 'secondary';
                                                        $badgeText = 'Em ' . $dias . ' dias';
                                                    }
                                                @endphp
                                                <div class="d-flex align-items-center gap-2">
                                                    <span>{{ $parcela->data_vencimento->format('d/m/Y') }}</span>
                                                    <span class="badge bg-{{ $badgeClass }}">{{ $badgeText }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $parcela->status_cor }}">
                                                    {{ $parcela->status_nome }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('pagamentos.create', ['parcela_id' => $parcela->id]) }}" 
                                                       class="btn btn-sm btn-success" title="Registrar Pagamento">
                                                        <i class="bx bx-money"></i> Registrar
                                                    </a>
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Empréstimo">
                                                        <i class="bx bx-show"></i> Ver Empréstimo
                                                    </a>
                                                    @php
                                                        $fichaWaDash = ($fichasContatoPorClienteOperacao ?? collect())->get($parcela->emprestimo->cliente_id.'_'.$parcela->emprestimo->operacao_id);
                                                    @endphp
                                                    @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaWaDash, $parcela->emprestimo->cliente))
                                                        <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaWaDash, $parcela->emprestimo->cliente) }}"
                                                           target="_blank"
                                                           class="btn btn-sm btn-success"
                                                           title="Falar no WhatsApp">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <p class="mb-0">Nenhuma cobrança nos próximos 7 dias! 🎉</p>
                                            </td>
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
    @endsection
    @section('scripts')
    @endsection
