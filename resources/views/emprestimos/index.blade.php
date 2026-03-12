@extends('layouts.master')
@section('title')
    Empréstimos
@endsection
@section('page-title')
    Empréstimos
@endsection
@section('body')

    <body>
    <body>
    @endsection
    @section('content')
        <!-- Cards totalizadores (respeitam os filtros da listagem) -->
        <div class="row mb-3">
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-list-ul font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-primary">
                    <div class="card-body text-center">
                        <i class="bx bx-money font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">R$ {{ number_format($stats['valor_total_emprestado'], 2, ',', '.') }}</h4>
                        <small class="text-muted">Valor emprestado</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-check-circle font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['ativos'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Ativos</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center">
                        <i class="bx bx-time-five font-size-24 text-warning"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['pendentes'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Pendentes</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-body text-center">
                        <i class="bx bx-error-circle font-size-24 text-danger"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['com_parcela_atrasada'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Com parcela atrasada</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body text-center">
                        <i class="bx bx-wallet font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">R$ {{ number_format($stats['valor_a_receber'], 2, ',', '.') }}</h4>
                        <small class="text-muted">Valor a receber</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-secondary">
                    <div class="card-body text-center">
                        <i class="bx bx-calendar-plus font-size-24 text-secondary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['novos_mes'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Novos este mês</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Lista de Empréstimos</h4>
                            <div class="d-flex gap-2">
                                <a href="{{ route('emprestimos.export', request()->only(['operacao_id', 'status', 'tipo', 'cliente_id'])) }}" class="btn btn-outline-success">
                                    <i class="bx bx-download"></i> Exportar CSV
                                </a>
                                @if(!auth()->user()->isSuperAdmin())
                                    <a href="{{ route('emprestimos.create') }}" class="btn btn-primary">
                                        <i class="bx bx-plus"></i> Novo Empréstimo
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('emprestimos.index') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas as Operações</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ request('operacao_id') == $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="tipo" class="form-select">
                                        <option value="">Todos os Tipos</option>
                                        <option value="dinheiro" {{ request('tipo') == 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                                        <option value="price" {{ request('tipo') == 'price' ? 'selected' : '' }}>Price</option>
                                        <option value="empenho" {{ request('tipo') == 'empenho' ? 'selected' : '' }}>Empenho</option>
                                        <option value="troca_cheque" {{ request('tipo') == 'troca_cheque' ? 'selected' : '' }}>Troca de Cheque</option>
                                        <option value="crediario" {{ request('tipo') == 'crediario' ? 'selected' : '' }}>Crediário</option>
                                        <option value="outros" {{ request('tipo') == 'outros' ? 'selected' : '' }}>Outros</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-select">
                                        <option value="">Todos os Status</option>
                                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Rascunho</option>
                                        <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                        <option value="aprovado" {{ request('status') == 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                        <option value="ativo" {{ request('status') == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                        <option value="finalizado" {{ request('status') == 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                                        <option value="cancelado" {{ request('status') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="cliente_id" class="form-control" 
                                           placeholder="ID do Cliente" 
                                           value="{{ request('cliente_id') }}">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bx bx-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Operação</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Parcelas</th>
                                        <th>Status</th>
                                        <th>Consultor</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emprestimos as $emprestimo)
                                        <tr>
                                            <td>#{{ $emprestimo->id }}</td>
                                            <td>{{ $emprestimo->cliente?->nome ?? '—' }}</td>
                                            <td>{{ $emprestimo->operacao?->nome ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $tipoLabel = match($emprestimo->tipo) {
                                                        'dinheiro' => ['Dinheiro', 'primary', 'bx-money'],
                                                        'price' => ['Price', 'info', 'bx-table'],
                                                        'empenho' => ['Empenho', 'warning', 'bx-shield-quarter'],
                                                        'troca_cheque' => ['Troca de Cheque', 'info', 'bx-money'],
                                                        'crediario' => ['Crediário', 'success', 'bx-cart'],
                                                        default => ['Outro', 'secondary', 'bx-help-circle']
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $tipoLabel[1] }}" title="{{ $tipoLabel[0] }}">
                                                    <i class="bx {{ $tipoLabel[2] }}"></i> {{ $tipoLabel[0] }}
                                                </span>
                                            </td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>{{ $emprestimo->numero_parcelas }}x ({{ ucfirst($emprestimo->frequencia) }})</td>
                                            <td>
                                                @php
                                                    $badgeClass = match($emprestimo->status) {
                                                        'ativo' => 'success',
                                                        'pendente' => 'warning',
                                                        'finalizado' => 'info',
                                                        'cancelado' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ ucfirst($emprestimo->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $emprestimo->consultor?->name ?? '—' }}</td>
                                            <td>{{ $emprestimo->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="{{ route('emprestimos.show', $emprestimo->id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Detalhes">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @if($emprestimo->cliente && $emprestimo->cliente->temWhatsapp())
                                                        <a href="{{ $emprestimo->cliente->whatsapp_link }}" 
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
                                            <td colspan="9" class="text-center">Nenhum empréstimo encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div class="mt-3 d-flex justify-content-end">
                            {{ $emprestimos->withQueryString()->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js -->
    @endsection