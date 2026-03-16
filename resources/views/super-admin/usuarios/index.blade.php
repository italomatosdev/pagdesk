@extends('layouts.master')
@section('title')
    Usuários - Super Admin
@endsection
@section('page-title')
    Todos os Usuários
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
                        <i class="bx bx-group font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Total de Usuários</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-body text-center">
                        <i class="bx bx-shield font-size-24 text-danger"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['administradores'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Administradores</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body text-center">
                        <i class="bx bx-user-check font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['gestores'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Gestores</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-user font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['consultores'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Consultores</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">
                                <i class="bx bx-user"></i> Lista de Usuários
                            </h4>
                            <span class="badge bg-primary">{{ $usuarios->total() }} usuários</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('super-admin.usuarios.index') }}" class="mb-4">
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-building text-muted me-1"></i> Empresa
                                    </label>
                                    <select name="empresa_id" class="form-select">
                                        <option value="">Todas as empresas</option>
                                        @foreach($empresas as $empresa)
                                            <option value="{{ $empresa->id }}" {{ request('empresa_id') == $empresa->id ? 'selected' : '' }}>
                                                {{ $empresa->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-badge text-muted me-1"></i> Papel
                                    </label>
                                    <select name="role" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-search text-muted me-1"></i> Buscar
                                    </label>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Nome ou email..." value="{{ request('search') }}">
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <a href="{{ route('super-admin.usuarios.index') }}" class="btn btn-outline-secondary w-100">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Empresa</th>
                                        <th>Papéis</th>
                                        <th>Operações</th>
                                        <th>Criado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($usuarios as $usuario)
                                        <tr>
                                            <td>{{ $usuario->id }}</td>
                                            <td>
                                                <strong>{{ $usuario->name }}</strong>
                                            </td>
                                            <td>
                                                <a href="mailto:{{ $usuario->email }}">{{ $usuario->email }}</a>
                                            </td>
                                            <td>
                                                @if($usuario->empresa)
                                                    <a href="{{ route('super-admin.empresas.show', $usuario->empresa_id) }}" 
                                                       class="text-decoration-none">
                                                        <span class="badge bg-info">
                                                            {{ $usuario->empresa->nome }}
                                                        </span>
                                                    </a>
                                                @else
                                                    <span class="badge bg-secondary">Sem empresa</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php $papeisUnicos = $usuario->operacoes->pluck('pivot.role')->map(fn ($r) => $r ?? 'consultor')->unique()->values(); @endphp
                                                @forelse($papeisUnicos as $p)
                                                    <span class="badge bg-{{ $p === 'administrador' ? 'danger' : ($p === 'gestor' ? 'warning' : 'primary') }} me-1">
                                                        {{ ucfirst($p) }}
                                                    </span>
                                                @empty
                                                    <span class="badge bg-secondary">Nenhum</span>
                                                @endforelse
                                            </td>
                                            <td>
                                                @if($usuario->operacoes->count() > 0)
                                                    @foreach($usuario->operacoes->take(3) as $operacao)
                                                        @php $papel = $operacao->pivot->role ?? 'consultor'; @endphp
                                                        <span class="badge bg-light text-dark me-1" title="{{ ucfirst($papel) }}">{{ $operacao->nome }} ({{ ucfirst($papel) }})</span>
                                                    @endforeach
                                                    @if($usuario->operacoes->count() > 3)
                                                        <span class="badge bg-secondary">+{{ $usuario->operacoes->count() - 3 }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $usuario->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td>
                                                <a href="{{ route('super-admin.usuarios.show', $usuario->id) }}" 
                                                   class="btn btn-sm btn-primary" title="Ver/Editar Usuário">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                @if($usuario->empresa)
                                                    <a href="{{ route('super-admin.empresas.show', $usuario->empresa_id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Empresa">
                                                        <i class="bx bx-building"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="bx bx-user-x font-size-24 text-muted"></i>
                                                <p class="text-muted mb-0 mt-2">Nenhum usuário encontrado.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($usuarios->hasPages())
                            <div class="mt-3 d-flex justify-content-end">
                                {{ $usuarios->appends(request()->query())->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endsection
