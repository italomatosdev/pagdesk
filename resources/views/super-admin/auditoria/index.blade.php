@extends('layouts.master')
@section('title')
    Logs de Auditoria - Super Admin
@endsection
@section('page-title')
    Logs de Auditoria
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Cards de Estatísticas -->
        <div class="row mb-3">
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-list-check font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Total de Logs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-calendar-check font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['hoje'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Hoje</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body text-center">
                        <i class="bx bx-calendar-week font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['semana'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Esta Semana</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center">
                        <i class="bx bx-calendar font-size-24 text-warning"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['mes'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Este Mês</small>
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
                <form method="GET" action="{{ route('super-admin.auditoria.index') }}">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Usuário</label>
                            <select name="user_id" class="form-select">
                                <option value="">Todos</option>
                                @foreach($usuarios as $usuario)
                                    <option value="{{ $usuario->id }}" {{ request('user_id') == $usuario->id ? 'selected' : '' }}>
                                        {{ $usuario->name }} ({{ $usuario->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Ação</label>
                            <input type="text" name="action" class="form-control" 
                                   placeholder="Ex: criar_emprestimo" 
                                   value="{{ request('action') }}">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Tipo de Modelo</label>
                            <select name="model_type" class="form-select">
                                <option value="">Todos</option>
                                @foreach($modelos as $modelo)
                                    <option value="{{ $modelo }}" {{ request('model_type') == $modelo ? 'selected' : '' }}>
                                        {{ $modelo }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">ID do Modelo</label>
                            <input type="number" name="model_id" class="form-control" 
                                   placeholder="Ex: 123" 
                                   value="{{ request('model_id') }}">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">IP Address</label>
                            <input type="text" name="ip_address" class="form-control" 
                                   placeholder="Ex: 192.168.1.1" 
                                   value="{{ request('ip_address') }}">
                        </div>
                        <div class="col-lg-1 col-md-12 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i>
                            </button>
                            <a href="{{ route('super-admin.auditoria.index') }}" class="btn btn-secondary">
                                <i class="bx bx-x"></i>
                            </a>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" 
                                   value="{{ request('data_inicio') }}">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" 
                                   value="{{ request('data_fim') }}">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listagem -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bx bx-list-check"></i> Logs de Auditoria
                </h5>
                <span class="badge bg-primary">{{ $logs->total() }} registros</span>
            </div>
            <div class="card-body">
                @if($logs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">ID</th>
                                    <th width="150">Data/Hora</th>
                                    <th width="120">Usuário</th>
                                    <th width="180">Ação</th>
                                    <th>Modelo</th>
                                    <th width="100">IP</th>
                                    <th width="80">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr>
                                        <td>#{{ $log->id }}</td>
                                        <td>
                                            <small>
                                                {{ $log->created_at->format('d/m/Y') }}<br>
                                                {{ $log->created_at->format('H:i:s') }}
                                            </small>
                                        </td>
                                        <td>
                                            @if($log->user)
                                                <strong>{{ $log->user->name }}</strong><br>
                                                <small class="text-muted">{{ $log->user->email }}</small>
                                            @else
                                                <span class="text-muted">Sistema</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $log->action }}</span>
                                        </td>
                                        <td>
                                            @if($log->model_type && $log->model_id)
                                                <strong>{{ class_basename($log->model_type) }}</strong> #{{ $log->model_id }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $log->ip_address ?? '-' }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('super-admin.auditoria.show', $log->id) }}" 
                                               class="btn btn-sm btn-info" title="Ver Detalhes">
                                                <i class="bx bx-show"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="mt-3">
                        {{ $logs->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-list-check font-size-48 text-muted"></i>
                        <p class="text-muted mt-3">Nenhum log encontrado.</p>
                    </div>
                @endif
            </div>
        </div>
    @endsection
