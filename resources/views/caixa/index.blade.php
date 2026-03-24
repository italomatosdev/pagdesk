@extends('layouts.master')
@section('title')
    Caixa
@endsection
@section('page-title')
    Movimentações de Caixa
@endsection
@section('body')

    <body>
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-12">
                <!-- Cards de Métricas Principais -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center flex-grow-1">
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-2">Saldo Atual</p>
                                        <h4 class="mb-0 text-primary">R$ {{ number_format($saldo ?? 0, 2, ',', '.') }}</h4>
                                        @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])) && ($consultorIdVal ?? '') === 'operacao')
                                            <small class="text-muted"><i class="bx bx-building"></i> Caixa da Operação{{ $operacaoId ? ' - ' . ($operacoes->firstWhere('id', $operacaoId)->nome ?? '') : '' }}</small>
                                        @elseif(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])) && ($consultorIdVal ?? '') === '')
                                            <small class="text-muted">Todas as Movimentações{{ $operacaoId ? ' - ' . ($operacoes->firstWhere('id', $operacaoId)->nome ?? '') : '' }}</small>
                                        @elseif($consultorSelecionado)
                                            <small class="text-muted">{{ $consultorSelecionado->name }}{{ $operacaoId ? ' - ' . ($operacoes->firstWhere('id', $operacaoId)->nome ?? '') : '' }}</small>
                                        @elseif($operacaoId)
                                            <small class="text-muted">Operação: {{ $operacoes->firstWhere('id', $operacaoId)->nome ?? 'Todas' }}</small>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="bx bx-wallet font-size-24 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center flex-grow-1">
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-2">Total Entradas</p>
                                        <h4 class="mb-0 text-success">R$ {{ number_format($totalEntradas ?? 0, 2, ',', '.') }}</h4>
                                        <small class="text-muted">No período filtrado</small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="bx bx-trending-up font-size-24 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center flex-grow-1">
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-2">Total Saídas</p>
                                        <h4 class="mb-0 text-danger">R$ {{ number_format($totalSaidas ?? 0, 2, ',', '.') }}</h4>
                                        <small class="text-muted">No período filtrado</small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="bx bx-trending-down font-size-24 text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center flex-grow-1">
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-2">Diferença do Período</p>
                                        <h4 class="mb-0 text-{{ ($diferencaPeriodo ?? 0) >= 0 ? 'success' : 'danger' }}">
                                            {{ ($diferencaPeriodo ?? 0) >= 0 ? '+' : '' }}R$ {{ number_format($diferencaPeriodo ?? 0, 2, ',', '.') }}
                                        </h4>
                                        <small class="text-muted">Entradas - Saídas</small>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="bx bx-bar-chart-alt-2 font-size-24 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-filter"></i> Filtros de Busca
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('caixa.index') }}">
                            <!-- Primeira linha de filtros -->
                            <div class="row g-3 mb-3">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-building text-muted me-1"></i> Operação
                                    </label>
                                    <select name="operacao_id" id="operacao-id-select" class="form-select">
                                        <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas as operações</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ (int) ($operacaoId ?? 0) === (int) $operacao->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-user text-muted me-1"></i> Consultor / Caixa
                                    </label>
                                    <select name="consultor_id" id="consultor-select" class="form-select">
                                        <option value="" {{ ($consultorIdVal ?? '') === '' ? 'selected' : '' }}>Todas as movimentações</option>
                                        <option value="operacao" {{ ($consultorIdVal ?? '') === 'operacao' ? 'selected' : '' }}>Caixa da operação</option>
                                        @if($operacaoId && !empty($usuariosPorOperacao[$operacaoId] ?? []))
                                            @foreach($usuariosPorOperacao[$operacaoId] as $u)
                                                <option value="{{ $u['id'] }}" {{ ($consultorIdVal ?? '') === (string)$u['id'] ? 'selected' : '' }}>{{ $u['name'] }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <small class="text-muted">Filtro por operação: usuários da operação selecionada (consultor, gestor, administrador)</small>
                                </div>
                                @endif
                                
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-category text-muted me-1"></i> Tipo de Referência
                                    </label>
                                    <select name="referencia_tipo" class="form-select">
                                        <option value="">Todos os tipos</option>
                                        <option value="manual" {{ request('referencia_tipo') === 'manual' ? 'selected' : '' }}>
                                            <i class="bx bx-edit"></i> Manual
                                        </option>
                                        <option value="settlement" {{ request('referencia_tipo') === 'settlement' ? 'selected' : '' }}>
                                            Prestação de Contas
                                        </option>
                                        <option value="liberacao_emprestimo" {{ request('referencia_tipo') === 'liberacao_emprestimo' ? 'selected' : '' }}>
                                            Liberação de Empréstimo
                                        </option>
                                        <option value="pagamento_cliente" {{ request('referencia_tipo') === 'pagamento_cliente' ? 'selected' : '' }}>
                                            Pagamento Cliente
                                        </option>
                                        <option value="venda" {{ request('referencia_tipo') === 'venda' ? 'selected' : '' }}>
                                            Venda
                                        </option>
                                        <option value="sangria_caixa_operacao" {{ request('referencia_tipo') === 'sangria_caixa_operacao' ? 'selected' : '' }}>
                                            Sangria (Caixa da Operação)
                                        </option>
                                        <option value="transferencia_caixa_operacao" {{ request('referencia_tipo') === 'transferencia_caixa_operacao' ? 'selected' : '' }}>
                                            Transferência (Caixa da Operação)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Segunda linha de filtros -->
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label">
                                        <i class="bx bx-calendar text-muted me-1"></i> Data Início
                                    </label>
                                    <input type="date" name="data_inicio" class="form-control" 
                                           value="{{ request('data_inicio') }}"
                                           placeholder="Selecione a data inicial">
                                </div>
                                
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label">
                                        <i class="bx bx-calendar-check text-muted me-1"></i> Data Fim
                                    </label>
                                    <input type="date" name="data_fim" class="form-control" 
                                           value="{{ request('data_fim') }}"
                                           placeholder="Selecione a data final">
                                </div>
                                
                                <div class="col-lg-3 col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-search me-1"></i> Aplicar Filtros
                                    </button>
                                </div>
                                
                                <div class="col-lg-3 col-md-12">
                                    <a href="{{ route('caixa.index') }}" class="btn btn-outline-secondary w-100">
                                        <i class="bx bx-x me-1"></i> Limpar Filtros
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Movimentações -->
                <div class="card mt-3">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Movimentações</h4>
                            @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('caixa.sangria.create') }}" class="btn btn-outline-primary btn-sm">
                                        <i class="bx bx-down-arrow-alt"></i> Sangria para Caixa da Operação
                                    </a>
                                    @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador'])))
                                        <a href="{{ route('caixa.transferencia_operacao.create') }}" class="btn btn-outline-secondary btn-sm">
                                            <i class="bx bx-transfer"></i> Transferência do Caixa da Operação
                                        </a>
                                    @endif
                                    <a href="{{ route('caixa.movimentacao.create') }}" class="btn btn-primary btn-sm">
                                        <i class="bx bx-plus"></i> Nova Movimentação Manual
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Valor</th>
                                        <th>Operação</th>
                                        <th>Responsável</th>
                                        <th>Origem</th>
                                        <th>Referência</th>
                                        <th>Comprovante</th>
                                        <th width="90">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($movimentacoes as $movimentacao)
                                        <tr>
                                            <td>{{ $movimentacao->data_movimentacao->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $movimentacao->isEntrada() ? 'success' : 'danger' }}">
                                                    {{ ucfirst($movimentacao->tipo) }}
                                                </span>
                                            </td>
                                            <td>{{ $movimentacao->descricao }}</td>
                                            <td class="{{ $movimentacao->isEntrada() ? 'text-success' : 'text-danger' }}">
                                                {{ $movimentacao->isEntrada() ? '+' : '-' }} 
                                                R$ {{ number_format($movimentacao->valor, 2, ',', '.') }}
                                            </td>
                                            <td>{{ $movimentacao->operacao->nome }}</td>
                                            <td>
                                                @if($movimentacao->consultor_id)
                                                    <span class="badge bg-primary">
                                                        {{ $movimentacao->consultor->name ?? 'N/A' }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">
                                                        <i class="bx bx-building"></i> Caixa da Operação
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $movimentacao->isManual() ? 'warning' : 'info' }}">
                                                    {{ $movimentacao->isManual() ? 'Manual' : 'Automática' }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($movimentacao->referencia_tipo)
                                                    <span class="badge bg-secondary">
                                                        @switch($movimentacao->referencia_tipo)
                                                            @case('settlement')
                                                                <i class="bx bx-receipt"></i> Prestação
                                                                @break
                                                            @case('liberacao_emprestimo')
                                                                <i class="bx bx-money"></i> Liberação
                                                                @break
                                                            @case('pagamento_cliente')
                                                                <i class="bx bx-user"></i> Pagamento
                                                                @break
                                                            @case('venda')
                                                            @case('App\Modules\Core\Models\Venda')
                                                                <i class="bx bx-cart"></i> Venda
                                                                @break
                                                            @case('sangria_caixa_operacao')
                                                                <i class="bx bx-down-arrow-alt"></i> Sangria
                                                                @break
                                                            @case('transferencia_caixa_operacao')
                                                                <i class="bx bx-transfer"></i> Transferência
                                                                @break
                                                            @default
                                                                {{ ucfirst(str_replace('_', ' ', $movimentacao->referencia_tipo)) }}
                                                        @endswitch
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-dark">
                                                        <i class="bx bx-edit"></i> Manual
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($movimentacao->comprovante_path)
                                                    <a href="{{ asset('storage/' . $movimentacao->comprovante_path) }}" target="_blank" class="btn btn-sm btn-info" title="Comprovante da movimentação">
                                                        <i class="bx bx-file"></i> Ver
                                                    </a>
                                                @elseif($movimentacao->referencia_tipo === 'liberacao_emprestimo' && isset($liberacoesById[$movimentacao->referencia_id]))
                                                    @php $lib = $liberacoesById[$movimentacao->referencia_id]; @endphp
                                                    @if($lib->comprovante_liberacao ?? null)
                                                        <a href="{{ asset('storage/' . $lib->comprovante_liberacao) }}" target="_blank" class="btn btn-sm btn-outline-info" title="Comprovante da liberação">
                                                            <i class="bx bx-file"></i> Ver
                                                        </a>
                                                    @else
                                                        -
                                                    @endif
                                                @elseif($movimentacao->referencia_tipo === 'pagamento_cliente' && isset($liberacoesPorEmprestimoId[$movimentacao->referencia_id]))
                                                    @php $lib = $liberacoesPorEmprestimoId[$movimentacao->referencia_id]; @endphp
                                                    @if($lib->comprovante_pagamento_cliente ?? null)
                                                        <a href="{{ asset('storage/' . $lib->comprovante_pagamento_cliente) }}" target="_blank" class="btn btn-sm btn-outline-info" title="Comprovante pagamento ao cliente">
                                                            <i class="bx bx-file"></i> Ver
                                                        </a>
                                                    @else
                                                        -
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('caixa.movimentacao.show', $movimentacao->id) }}" class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                                    <i class="bx bx-show"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center">Nenhuma movimentação encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-2">
                            {{ $movimentacoes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const operacaoSelect = document.getElementById('operacao-id-select');
                const consultorSelect = document.getElementById('consultor-select');
                if (!consultorSelect || !operacaoSelect) return;

                const usuariosPorOperacao = @json($usuariosPorOperacao ?? []);

                function preencherConsultorSelect(operacaoId) {
                    const valorAtual = consultorSelect.value;
                    consultorSelect.innerHTML = '';
                    const optTodas = document.createElement('option');
                    optTodas.value = '';
                    optTodas.textContent = 'Todas as movimentações';
                    consultorSelect.appendChild(optTodas);
                    const optOperacao = document.createElement('option');
                    optOperacao.value = 'operacao';
                    optOperacao.textContent = 'Caixa da operação';
                    consultorSelect.appendChild(optOperacao);

                    const usuarios = operacaoId && usuariosPorOperacao[operacaoId] ? usuariosPorOperacao[operacaoId] : [];
                    usuarios.forEach(function(u) {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.name;
                        consultorSelect.appendChild(opt);
                    });

                    if (valorAtual === '' || valorAtual === 'operacao' || usuarios.some(function(u) { return String(u.id) === valorAtual; })) {
                        consultorSelect.value = valorAtual;
                    } else {
                        consultorSelect.value = '';
                    }
                }

                operacaoSelect.addEventListener('change', function() {
                    preencherConsultorSelect(this.value ? parseInt(this.value, 10) : null);
                });
            });
        </script>
    @endsection