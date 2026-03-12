@extends('layouts.master')
@section('title')
    Notificações
@endsection
@section('page-title')
    Notificações
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <!-- Filtros e Ações -->
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Filtros</h5>
                            @if($naoLidas > 0)
                                <form method="POST" action="{{ route('notificacoes.marcar-todas-lidas') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bx bx-check-double"></i> Marcar Todas como Lidas
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('notificacoes.index') }}">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por</label>
                                    <select name="filtro" class="form-select">
                                        <option value="todas" {{ $filtro == 'todas' ? 'selected' : '' }}>Todas</option>
                                        <option value="nao_lidas" {{ $filtro == 'nao_lidas' ? 'selected' : '' }}>Não Lidas</option>
                                        <option value="lidas" {{ $filtro == 'lidas' ? 'selected' : '' }}>Lidas</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Por página</label>
                                    <select name="per_page" class="form-select">
                                        <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ request('per_page') == 20 || !request('per_page') ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                </div>
                                <div class="col-md-12 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                    <a href="{{ route('notificacoes.index') }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Resumo -->
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary-subtle">
                                            <span class="avatar-title rounded-circle bg-primary font-size-20">
                                                <i class="bx bx-bell text-white"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Total</p>
                                        <h5 class="mb-0">{{ $notificacoes->total() }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-warning-subtle">
                                            <span class="avatar-title rounded-circle bg-warning font-size-20">
                                                <i class="bx bx-error-circle text-white"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Não Lidas</p>
                                        <h5 class="mb-0">{{ $naoLidas }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-success-subtle">
                                            <span class="avatar-title rounded-circle bg-success font-size-20">
                                                <i class="bx bx-check-circle text-white"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-muted mb-1">Lidas</p>
                                        <h5 class="mb-0">{{ $totalLidas }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Notificações -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notificações</h5>
                    </div>
                    <div class="card-body">
                        @if($notificacoes->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">Tipo</th>
                                            <th>Notificação</th>
                                            <th style="width: 150px;">Data</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 120px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($notificacoes as $notificacao)
                                            <tr class="{{ !$notificacao->lida ? 'table-light' : '' }}">
                                                <td>
                                                    <div class="avatar-sm">
                                                        <span class="avatar-title bg-{{ $notificacao->cor }}-subtle rounded-circle font-size-18">
                                                            <i class="bx {{ $notificacao->icone }} text-{{ $notificacao->cor }}"></i>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-1 {{ !$notificacao->lida ? 'fw-semibold' : '' }}">
                                                            {{ $notificacao->titulo }}
                                                        </h6>
                                                        <p class="text-muted mb-0">{{ $notificacao->mensagem }}</p>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-muted">{{ $notificacao->tempo_relativo }}</span>
                                                    <br>
                                                    <small class="text-muted">{{ $notificacao->created_at->format('d/m/Y H:i') }}</small>
                                                </td>
                                                <td>
                                                    @if($notificacao->lida)
                                                        <span class="badge bg-success">Lida</span>
                                                    @else
                                                        <span class="badge bg-warning">Não Lida</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        @if($notificacao->url)
                                                            <a href="{{ $notificacao->url }}" 
                                                               class="btn btn-sm btn-primary" 
                                                               title="Ver Detalhes">
                                                                <i class="bx bx-show"></i>
                                                            </a>
                                                        @endif
                                                        @if(!$notificacao->lida)
                                                            <form method="POST" 
                                                                  action="{{ route('notificacoes.marcar-lida', $notificacao->id) }}" 
                                                                  class="d-inline">
                                                                @csrf
                                                                <button type="submit" 
                                                                        class="btn btn-sm btn-success" 
                                                                        title="Marcar como Lida">
                                                                    <i class="bx bx-check"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginação -->
                            <div class="mt-3 d-flex justify-content-end">
                                {{ $notificacoes->links() }}
                            </div>
                        @else
                            <div class="text-center py-5">
                                <div class="mb-3">
                                    <i class="bx bx-bell-off font-size-48 text-muted"></i>
                                </div>
                                <h5 class="text-muted">Nenhuma notificação encontrada</h5>
                                <p class="text-muted">Você está em dia!</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
    @endsection
