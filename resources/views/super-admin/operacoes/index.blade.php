@extends('layouts.master')
@section('title')
    Operações - Super Admin
@endsection
@section('page-title')
    Operações - Super Admin
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Cards de Estatísticas -->
        <div class="row mb-3">
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-briefcase font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['total'] }}</h4>
                        <small class="text-muted">Total de Operações</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-check-circle font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['ativas'] }}</h4>
                        <small class="text-muted">Operações Ativas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-body text-center">
                        <i class="bx bx-x-circle font-size-24 text-danger"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['inativas'] }}</h4>
                        <small class="text-muted">Operações Inativas</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bx bx-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('super-admin.operacoes.index') }}">
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="busca" class="form-control" 
                                   placeholder="Nome, código, descrição ou empresa..." 
                                   value="{{ request('busca') }}">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Empresa</label>
                            <select name="empresa_id" class="form-select">
                                <option value="">Todas</option>
                                @foreach($empresas as $empresa)
                                    <option value="{{ $empresa->id }}" {{ request('empresa_id') == $empresa->id ? 'selected' : '' }}>
                                        {{ $empresa->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Status</label>
                            <select name="ativo" class="form-select">
                                <option value="">Todos</option>
                                <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>Ativas</option>
                                <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>Inativas</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Buscar
                            </button>
                            <a href="{{ route('super-admin.operacoes.index') }}" class="btn btn-secondary">
                                <i class="bx bx-x"></i> Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listagem -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bx bx-briefcase"></i> Todas as Operações
                </h5>
                <span class="badge bg-primary">{{ $operacoes->total() }} operações</span>
            </div>
            <div class="card-body">
                @if($operacoes->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th>Nome</th>
                                    <th>Código</th>
                                    <th>Empresa</th>
                                    <th>Valor Aprovação Auto</th>
                                    <th>Requer Aprovação</th>
                                    <th>Requer Liberação</th>
                                    <th>Status</th>
                                    <th width="100">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($operacoes as $operacao)
                                    <tr>
                                        <td>{{ $operacao->id }}</td>
                                        <td>
                                            <strong>{{ $operacao->nome }}</strong>
                                            @if($operacao->descricao)
                                                <br><small class="text-muted">{{ Str::limit($operacao->descricao, 40) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($operacao->codigo)
                                                <span class="badge bg-secondary">{{ $operacao->codigo }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($operacao->empresa)
                                                <a href="{{ route('super-admin.empresas.show', $operacao->empresa_id) }}">
                                                    {{ $operacao->empresa->nome }}
                                                </a>
                                            @else
                                                <span class="text-muted">Sem empresa</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($operacao->valor_aprovacao_automatica)
                                                <span class="text-success">R$ {{ number_format($operacao->valor_aprovacao_automatica, 2, ',', '.') }}</span>
                                            @else
                                                <span class="text-muted">Não configurado</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $operacao->requer_aprovacao ? 'warning' : 'success' }}">
                                                {{ $operacao->requer_aprovacao ? 'Sim' : 'Não' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-{{ $operacao->requer_liberacao ? 'info' : 'success' }}">
                                                {{ $operacao->requer_liberacao ? 'Sim' : 'Não' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $operacao->ativo ? 'success' : 'danger' }}">
                                                {{ $operacao->ativo ? 'Ativo' : 'Inativo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('super-admin.operacoes.show', $operacao->id) }}" 
                                                   class="btn btn-sm btn-info" title="Ver Detalhes">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <a href="{{ route('super-admin.operacoes.edit', $operacao->id) }}" 
                                                   class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="mt-3">
                        {{ $operacoes->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-briefcase font-size-48 text-muted"></i>
                        <p class="text-muted mt-3">Nenhuma operação encontrada.</p>
                    </div>
                @endif
            </div>
        </div>
    @endsection
