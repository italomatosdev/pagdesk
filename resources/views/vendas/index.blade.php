@extends('layouts.master')
@section('title')
    Vendas
@endsection
@section('page-title')
    Vendas
@endsection
@section('body')
    <body>
@endsection
@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bx bx-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('vendas.create') }}" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Nova Venda
            </a>
        </div>
    </div>

    <!-- Totalizadores (respeitam os filtros da listagem) -->
    <div class="row mb-3">
        <div class="col-6 col-md-3 mb-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <i class="bx bx-cart font-size-24 text-primary"></i>
                    <h5 class="mt-1 mb-0">{{ number_format($stats['total_vendas'], 0, ',', '.') }}</h5>
                    <small class="text-muted">Total de vendas</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card h-100 border-primary">
                <div class="card-body text-center py-3">
                    <i class="bx bx-money font-size-24 text-primary"></i>
                    <h5 class="mt-1 mb-0">R$ {{ number_format((float)$stats['valor_total'], 2, ',', '.') }}</h5>
                    <small class="text-muted">Valor total (filtro)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card h-100 border-info">
                <div class="card-body text-center py-3">
                    <i class="bx bx-calendar font-size-24 text-info"></i>
                    <h5 class="mt-1 mb-0">{{ number_format($stats['vendas_mes'], 0, ',', '.') }}</h5>
                    <small class="text-muted">Vendas este mês</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card h-100 border-success">
                <div class="card-body text-center py-3">
                    <i class="bx bx-wallet font-size-24 text-success"></i>
                    <h5 class="mt-1 mb-0">R$ {{ number_format((float)$stats['valor_mes'], 2, ',', '.') }}</h5>
                    <small class="text-muted">Valor este mês</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bx bx-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('vendas.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Operação</label>
                    <select name="operacao_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($operacoes as $op)
                            <option value="{{ $op->id }}" {{ request('operacao_id') == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data início</label>
                    <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data fim</label>
                    <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-search"></i> Filtrar</button>
                    <a href="{{ route('vendas.index') }}" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bx bx-cart"></i> Listagem de Vendas</h5>
        </div>
        <div class="card-body">
            @if($vendas->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Operação</th>
                                <th class="text-end">Total</th>
                                <th>Vendedor</th>
                                <th width="90">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vendas as $venda)
                                <tr>
                                    <td>{{ $venda->id }}</td>
                                    <td>{{ $venda->data_venda->format('d/m/Y') }}</td>
                                    <td>
                                        @if($venda->cliente)
                                            <a href="{{ \App\Support\ClienteUrl::show($venda->cliente_id, $venda->operacao_id) }}">{{ $venda->cliente->nome }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $venda->operacao->nome ?? '—' }}</td>
                                    <td class="text-end"><strong>R$ {{ number_format($venda->formasPagamento->sum('valor'), 2, ',', '.') }}</strong></td>
                                    <td>{{ $venda->user->name ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('vendas.show', $venda->id) }}" class="btn btn-sm btn-info" title="Ver"><i class="bx bx-show"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $vendas->links() }}</div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bx bx-cart font-size-48"></i>
                    <p class="mt-2">Nenhuma venda encontrada com os filtros aplicados.</p>
                    <a href="{{ route('vendas.index') }}" class="btn btn-secondary me-2">Limpar filtros</a>
                    <a href="{{ route('vendas.create') }}" class="btn btn-primary">Nova Venda</a>
                </div>
            @endif
        </div>
    </div>
@endsection
