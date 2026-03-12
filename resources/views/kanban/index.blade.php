@extends('layouts.master')
@section('title')
    Painel de Pendências
@endsection
@section('page-title')
    Painel de Pendências
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Filtros -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('kanban.index') }}" class="d-flex gap-2 align-items-end flex-wrap">
                            @if($operacoes->count() > 1)
                            <div class="flex-grow-1" style="min-width: 200px;">
                                <label for="operacao_id" class="form-label">Filtrar por Operação</label>
                                <select name="operacao_id" id="operacao_id" class="form-select">
                                    <option value="">Todas as Operações</option>
                                    @foreach($operacoes as $operacao)
                                        <option value="{{ $operacao->id }}" {{ $operacaoId == $operacao->id ? 'selected' : '' }}>
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
                                @if($operacaoId)
                                    <a href="{{ route('kanban.index') }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contadores -->
        <div class="row mb-3">
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <div class="card border-primary">
                    <div class="card-body text-center p-2">
                        <h4 class="mb-0 text-primary">{{ $contadores['aprovacoes'] }}</h4>
                        <small class="text-muted">Aprovações</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <div class="card border-info">
                    <div class="card-body text-center p-2">
                        <h4 class="mb-0 text-info">{{ $contadores['liberacoes'] }}</h4>
                        <small class="text-muted">Liberações</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <div class="card border-warning">
                    <div class="card-body text-center p-2">
                        <h4 class="mb-0 text-warning">{{ $contadores['em_acao'] }}</h4>
                        <small class="text-muted">Em Ação</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <div class="card border-secondary">
                    <div class="card-body text-center p-2">
                        <h4 class="mb-0 text-secondary">{{ $contadores['aguardando'] }}</h4>
                        <small class="text-muted">Aguardando</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <div class="card border-danger">
                    <div class="card-body text-center p-2">
                        <h4 class="mb-0 text-danger">{{ $contadores['urgentes'] }}</h4>
                        <small class="text-muted">Urgentes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <div class="card border-dark">
                    <div class="card-body text-center p-2">
                        <h4 class="mb-0 text-dark">{{ $contadores['total'] }}</h4>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanban Board -->
        <div class="row">
            <!-- Coluna: Aprovações Pendentes -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0 text-white">
                            <i class="bx bx-check-circle"></i> Aprovações
                            <span class="badge bg-light text-primary ms-2">{{ $pendencias['aprovacoes']->count() }}</span>
                        </h5>
                    </div>
                    <div class="card-body p-2" id="coluna-aprovacoes" style="min-height: 400px; max-height: 80vh; overflow-y: auto;">
                        @forelse($pendencias['aprovacoes'] as $item)
                            @include('kanban.partials.card', ['item' => $item])
                        @empty
                            <div class="text-center text-muted py-3">
                                <i class="bx bx-check-circle font-size-24"></i>
                                <p class="mb-0 small">Nenhuma pendência</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Coluna: Liberações Aguardando -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card h-100 border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0 text-white">
                            <i class="bx bx-transfer"></i> Liberações
                            <span class="badge bg-light text-info ms-2">{{ $pendencias['liberacoes']->count() }}</span>
                        </h5>
                    </div>
                    <div class="card-body p-2" id="coluna-liberacoes" style="min-height: 400px; max-height: 80vh; overflow-y: auto;">
                        @forelse($pendencias['liberacoes'] as $item)
                            @include('kanban.partials.card', ['item' => $item])
                        @empty
                            <div class="text-center text-muted py-3">
                                <i class="bx bx-transfer font-size-24"></i>
                                <p class="mb-0 small">Nenhuma pendência</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Coluna: Em Ação -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0 text-dark">
                            <i class="bx bx-time"></i> Em Ação
                            <span class="badge bg-dark text-warning ms-2">{{ $pendencias['em_acao']->count() }}</span>
                        </h5>
                    </div>
                    <div class="card-body p-2" id="coluna-em-acao" style="min-height: 400px; max-height: 80vh; overflow-y: auto;">
                        @forelse($pendencias['em_acao'] as $item)
                            @include('kanban.partials.card', ['item' => $item])
                        @empty
                            <div class="text-center text-muted py-3">
                                <i class="bx bx-time font-size-24"></i>
                                <p class="mb-0 small">Nenhuma pendência</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Coluna: Aguardando Confirmação -->
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card h-100 border-secondary">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0 text-white">
                            <i class="bx bx-hourglass"></i> Aguardando
                            <span class="badge bg-light text-secondary ms-2">{{ $pendencias['aguardando']->count() }}</span>
                        </h5>
                    </div>
                    <div class="card-body p-2" id="coluna-aguardando" style="min-height: 400px; max-height: 80vh; overflow-y: auto;">
                        @forelse($pendencias['aguardando'] as $item)
                            @include('kanban.partials.card', ['item' => $item])
                        @empty
                            <div class="text-center text-muted py-3">
                                <i class="bx bx-hourglass font-size-24"></i>
                                <p class="mb-0 small">Nenhuma pendência</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Coluna: Urgentes/Atrasados -->
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0 text-white">
                            <i class="bx bx-error-circle"></i> Urgentes/Atrasados
                            <span class="badge bg-light text-danger ms-2">{{ $pendencias['urgentes']->count() }}</span>
                        </h5>
                    </div>
                    <div class="card-body p-2" id="coluna-urgentes" style="min-height: 400px; max-height: 80vh; overflow-y: auto;">
                        @forelse($pendencias['urgentes'] as $item)
                            @include('kanban.partials.card', ['item' => $item])
                        @empty
                            <div class="text-center text-muted py-3">
                                <i class="bx bx-error-circle font-size-24"></i>
                                <p class="mb-0 small">Nenhuma pendência</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('css')
        <style>
            .kanban-card {
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .kanban-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            #coluna-aprovacoes,
            #coluna-liberacoes,
            #coluna-em-acao,
            #coluna-aguardando,
            #coluna-urgentes {
                background-color: #f8f9fa;
            }
        </style>
    @endsection
    @section('scripts')
        <script>
            function confirmarAcao(acao, form) {
                const acoes = {
                    'aprovar': 'Tem certeza que deseja aprovar?',
                    'liberar': 'Tem certeza que deseja liberar o dinheiro?',
                    'confirmar': 'Tem certeza que deseja confirmar?'
                };
                return confirm(acoes[acao] || 'Tem certeza?');
            }

            function mostrarModalRejeitarEmprestimo(emprestimoId, url) {
                const motivo = prompt('Digite o motivo da rejeição:');
                if (motivo && motivo.trim().length >= 5) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    form.innerHTML = `
                        @csrf
                        <input type="hidden" name="motivo_rejeicao" value="${motivo}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                } else if (motivo !== null) {
                    alert('O motivo deve ter pelo menos 5 caracteres.');
                }
            }

            // Auto-refresh a cada 30 segundos
            setInterval(function() {
                if (document.visibilityState === 'visible') {
                    location.reload();
                }
            }, 30000);
        </script>
    @endsection
