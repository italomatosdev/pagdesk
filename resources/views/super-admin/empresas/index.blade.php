@extends('layouts.master')
@section('title')
    Empresas
@endsection
@section('page-title')
    Empresas
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
                        <i class="bx bx-building font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Total de Empresas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-check-circle font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['ativas'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Empresas Ativas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center">
                        <i class="bx bx-pause-circle font-size-24 text-warning"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['suspensas'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Empresas Suspensas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-body text-center">
                        <i class="bx bx-x-circle font-size-24 text-danger"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['canceladas'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Empresas Canceladas</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Lista de Empresas</h4>
                            <a href="{{ route('super-admin.empresas.create') }}" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Nova Empresa
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('super-admin.empresas.index') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="ativa" {{ request('status') == 'ativa' ? 'selected' : '' }}>Ativa</option>
                                        <option value="suspensa" {{ request('status') == 'suspensa' ? 'selected' : '' }}>Suspensa</option>
                                        <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Plano</label>
                                    <select name="plano" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="basico" {{ request('plano') == 'basico' ? 'selected' : '' }}>Básico</option>
                                        <option value="profissional" {{ request('plano') == 'profissional' ? 'selected' : '' }}>Profissional</option>
                                        <option value="enterprise" {{ request('plano') == 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Buscar</label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Nome, CNPJ..." value="{{ request('search') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>CNPJ</th>
                                        <th>Status</th>
                                        <th>Plano</th>
                                        <th>Operações</th>
                                        <th>Usuários</th>
                                        <th>Clientes</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($empresas as $empresa)
                                        <tr>
                                            <td>{{ $empresa->id }}</td>
                                            <td>
                                                <strong>{{ $empresa->nome }}</strong>
                                                @if($empresa->razao_social && $empresa->razao_social != $empresa->nome)
                                                    <br><small class="text-muted">{{ $empresa->razao_social }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $empresa->cnpj ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $empresa->cnpj) : '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $empresa->status == 'ativa' ? 'success' : ($empresa->status == 'suspensa' ? 'warning' : 'danger') }}">
                                                    {{ ucfirst($empresa->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ ucfirst($empresa->plano) }}</span>
                                            </td>
                                            <td>{{ $empresa->operacoes_count }}</td>
                                            <td>{{ $empresa->usuarios_count }}</td>
                                            <td>{{ $empresa->clientes_count }}</td>
                                            <td>
                                                <a href="{{ route('super-admin.empresas.show', $empresa->id) }}" 
                                                   class="btn btn-sm btn-info" title="Ver Detalhes">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <a href="{{ route('super-admin.empresas.edit', $empresa->id) }}" 
                                                   class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                @if($empresa->status == 'ativa')
                                                    <form action="{{ route('super-admin.empresas.suspender', $empresa->id) }}" 
                                                          method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Tem certeza que deseja suspender esta empresa?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-warning" title="Suspender">
                                                            <i class="bx bx-pause"></i>
                                                        </button>
                                                    </form>
                                                @elseif($empresa->status == 'suspensa')
                                                    <form action="{{ route('super-admin.empresas.ativar', $empresa->id) }}" 
                                                          method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Ativar">
                                                            <i class="bx bx-play"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">Nenhuma empresa encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-end">
                            {{ $empresas->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
    @endsection
