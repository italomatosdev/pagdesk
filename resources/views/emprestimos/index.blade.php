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
                                <a href="{{ route('emprestimos.export', request()->only(['operacao_id', 'status', 'tipo', 'cliente_nome', 'proximo_vencimento_de', 'proximo_vencimento_ate', 'apenas_atrasadas'])) }}" class="btn btn-outline-success">
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
                        <form method="GET" action="{{ route('emprestimos.index') }}" class="mb-4">
                            @php
                                $statusesFiltro = (array) request('status', []);
                                $opcoesStatus = [
                                    'draft' => 'Rascunho',
                                    'pendente' => 'Pendente',
                                    'aprovado' => 'Aprovado',
                                    'ativo' => 'Ativo',
                                    'finalizado' => 'Finalizado',
                                    'cancelado' => 'Cancelado',
                                ];
                            @endphp
                            <div class="bg-light rounded p-3 mb-3">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Operação</label>
                                        <select name="operacao_id" class="form-select">
                                            <option value="">Todas</option>
                                            @foreach($operacoes as $operacao)
                                                <option value="{{ $operacao->id }}" {{ request('operacao_id') == $operacao->id ? 'selected' : '' }}>{{ $operacao->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Tipo</label>
                                        <select name="tipo" class="form-select">
                                            <option value="">Todos</option>
                                            <option value="dinheiro" {{ request('tipo') == 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                                            <option value="price" {{ request('tipo') == 'price' ? 'selected' : '' }}>Price</option>
                                            <option value="empenho" {{ request('tipo') == 'empenho' ? 'selected' : '' }}>Empenho</option>
                                            <option value="troca_cheque" {{ request('tipo') == 'troca_cheque' ? 'selected' : '' }}>Troca de Cheque</option>
                                            <option value="crediario" {{ request('tipo') == 'crediario' ? 'selected' : '' }}>Crediário</option>
                                            <option value="outros" {{ request('tipo') == 'outros' ? 'selected' : '' }}>Outros</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-1">Cliente (nome ou CPF)</label>
                                        <input type="text" name="cliente_nome" class="form-control" placeholder="Digite nome ou CPF" value="{{ request('cliente_nome') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Próx. venc. de</label>
                                        <input type="date" name="proximo_vencimento_de" class="form-control" value="{{ request('proximo_vencimento_de') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Próx. venc. até</label>
                                        <input type="date" name="proximo_vencimento_ate" class="form-control" value="{{ request('proximo_vencimento_ate') }}">
                                    </div>
                                    <div class="col-auto d-flex align-items-end">
                                        <div class="form-check">
                                            <input type="checkbox" name="apenas_atrasadas" value="1" class="form-check-input" id="apenas_atrasadas" {{ request('apenas_atrasadas') ? 'checked' : '' }}>
                                            <label class="form-check-label text-danger" for="apenas_atrasadas"><i class="bx bx-error-circle"></i> Atrasadas</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary"><i class="bx bx-search"></i> Buscar</button>
                                    </div>
                                </div>
                                <div class="row mt-3 pt-3 border-top border-secondary border-opacity-25">
                                    <div class="col-12">
                                        <span class="form-label d-inline-block me-2 mb-0">Status (pode marcar vários):</span>
                                        @foreach($opcoesStatus as $valor => $label)
                                            <div class="form-check form-check-inline">
                                                <input type="checkbox" name="status[]" value="{{ $valor }}" id="status_{{ $valor }}" class="form-check-input" {{ in_array($valor, $statusesFiltro) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="status_{{ $valor }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                        <span class="text-muted ms-2">(nenhum marcado = todos)</span>
                                    </div>
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
                                        <th>Valor (emprestado)</th>
                                        <th>Valor total (c/ juros)</th>
                                        <th>Parcelas</th>
                                        <th>Status</th>
                                        <th>Consultor</th>
                                        <th>Próx. venc.</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emprestimos as $emprestimo)
                                        <tr>
                                            <td>#{{ $emprestimo->id }}</td>
                                            <td>
                                                @if($emprestimo->cliente)
                                                    <a href="{{ \App\Support\ClienteUrl::show($emprestimo->cliente_id, $emprestimo->operacao_id) }}">{{ $emprestimo->cliente->nome }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
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
                                            <td>R$ {{ number_format($emprestimo->calcularValorTotalComJuros(), 2, ',', '.') }}</td>
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
                                            <td>{{ $emprestimo->getProximoVencimento()?->format('d/m/Y') ?? '—' }}</td>
                                            <td>{{ $emprestimo->created_at?->format('d/m/Y') ?? '—' }}</td>
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
                                            <td colspan="11" class="text-center">Nenhum empréstimo encontrado.</td>
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