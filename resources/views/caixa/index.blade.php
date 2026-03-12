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
                                        @if(auth()->user()->hasAnyRole(['administrador', 'gestor']) && $consultorId === null)
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
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas as operações</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ $operacaoId == $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                @if(auth()->user()->hasAnyRole(['administrador', 'gestor']))
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">
                                        <i class="bx bx-user text-muted me-1"></i> Consultor/Gestor
                                    </label>
                                    <select name="consultor_id" id="consultor-select" class="form-select">
                                        <option value="" {{ $consultorId === null ? 'selected' : '' }}>Todas as movimentações</option>
                                        @if(isset($consultorSelecionado) && $consultorSelecionado)
                                            @php
                                                $roles = $consultorSelecionado->roles->pluck('name')->map(fn($r) => ucfirst($r))->implode(', ');
                                            @endphp
                                            <option value="{{ $consultorSelecionado->id }}" selected>
                                                {{ $consultorSelecionado->name }} - {{ $consultorSelecionado->email }} ({{ $roles }})
                                            </option>
                                        @endif
                                    </select>
                                    <small class="text-muted">Busque um usuário específico ou deixe em branco</small>
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
                            @if(auth()->user()->hasAnyRole(['administrador', 'gestor']))
                                <a href="{{ route('caixa.movimentacao.create') }}" class="btn btn-primary btn-sm">
                                    <i class="bx bx-plus"></i> Nova Movimentação Manual
                                </a>
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
                                                    <a href="{{ asset('storage/' . $movimentacao->comprovante_path) }}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="bx bx-file"></i> Ver
                                                    </a>
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
                // Verificar se já há um consultor selecionado
                const consultorSelect = document.getElementById('consultor-select');
                if (!consultorSelect) return; // Se não existe o campo (consultor não tem acesso), sair
                
                const consultorJaSelecionado = consultorSelect.options.length > 0 && consultorSelect.options[0].value;
                
                // Configuração do Select2
                const select2Config = {
                    theme: 'bootstrap-5',
                    placeholder: 'Selecione "Caixa da Operação" ou busque um usuário...',
                    allowClear: true,
                    minimumInputLength: 2,
                    language: {
                        inputTooShort: function() {
                            return 'Digite pelo menos 2 caracteres para buscar';
                        },
                        noResults: function() {
                            return 'Nenhum usuário encontrado';
                        },
                        searching: function() {
                            return 'Buscando...';
                        }
                    },
                    ajax: {
                        url: '{{ route("usuarios.api.buscar") }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term, // termo de busca
                                page: params.page || 1
                            };
                        },
                        processResults: function (data, params) {
                            params.page = params.page || 1;
                            return {
                                results: data.results,
                                pagination: {
                                    more: (params.page * 20) < data.total_count
                                }
                            };
                        },
                        cache: true
                    }
                };
                
                // Se já há um consultor selecionado, não precisa de minimumInputLength
                if (consultorJaSelecionado) {
                    select2Config.minimumInputLength = 0;
                }
                
                // Inicializar Select2 para busca de consultores/gestores
                $('#consultor-select').select2(select2Config);
                
                // Permitir selecionar a opção "Caixa da Operação" (valor vazio)
                $('#consultor-select').on('select2:select', function(e) {
                    // Se selecionar a opção vazia, garantir que está selecionada
                    if (e.params.data.id === '') {
                        $(this).val('').trigger('change');
                    }
                });
            });
        </script>
    @endsection