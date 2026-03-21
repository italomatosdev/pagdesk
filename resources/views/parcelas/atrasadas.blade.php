@extends('layouts.master')
@section('title')
    Parcelas Atrasadas
@endsection
@section('page-title')
    Parcelas Atrasadas
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <!-- Filtros -->
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('parcelas.atrasadas') }}">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Operação</label>
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ request('operacao_id') == $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])))
                                <div class="col-md-3">
                                    <label class="form-label">Consultor</label>
                                    <select name="consultor_id" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach($consultores as $consultor)
                                            <option value="{{ $consultor->id }}" 
                                                    {{ request('consultor_id') == $consultor->id ? 'selected' : '' }}>
                                                {{ $consultor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif

                                <div class="col-md-2">
                                    <label class="form-label">Dias Atraso (mín.)</label>
                                    <input type="number" name="dias_atraso_min" class="form-control" 
                                           value="{{ request('dias_atraso_min') }}" 
                                           placeholder="Ex: 30" min="0">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Valor Mínimo</label>
                                    <input type="text" id="valor_min" name="valor_min" class="form-control" inputmode="decimal"
                                           data-mask-money="brl" placeholder="Ex: 100" value="{{ request('valor_min') }}">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Ordenar por</label>
                                    <select name="ordenacao" class="form-select">
                                        <option value="dias_atraso" {{ request('ordenacao') == 'dias_atraso' ? 'selected' : '' }}>Dias Atraso</option>
                                        <option value="data_vencimento" {{ request('ordenacao') == 'data_vencimento' ? 'selected' : '' }}>Data Vencimento</option>
                                        <option value="valor" {{ request('ordenacao') == 'valor' ? 'selected' : '' }}>Valor</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Direção</label>
                                    <select name="direcao" class="form-select">
                                        <option value="desc" {{ request('direcao') == 'desc' ? 'selected' : '' }}>Decrescente</option>
                                        <option value="asc" {{ request('direcao') == 'asc' ? 'selected' : '' }}>Crescente</option>
                                    </select>
                                </div>

                                <div class="col-md-12 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                    <a href="{{ route('parcelas.atrasadas') }}" class="btn btn-secondary">
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
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h6 class="text-muted mb-1">Total de Parcelas</h6>
                                <h4 class="mb-0">{{ number_format($parcelas->total(), 0, ',', '.') }}</h4>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-1">Valor Total em Atraso</h6>
                                <h4 class="mb-0 text-danger">
                                    R$ {{ number_format($parcelas->sum(function($p) { return $p->valor - $p->valor_pago; }), 2, ',', '.') }}
                                </h4>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-1">Média de Dias Atraso</h6>
                                <h4 class="mb-0">
                                    {{ number_format($parcelas->avg('dias_atraso'), 1, ',', '.') }} dias
                                </h4>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-1">Parcela Mais Atrasada</h6>
                                <h4 class="mb-0 text-danger">
                                    {{ $parcelas->max('dias_atraso') ?? 0 }} dias
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Parcelas Atrasadas -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Parcelas Atrasadas</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Empréstimo</th>
                                        <th>Parcela</th>
                                        <th>Consultor</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Dias Atraso</th>
                                        <th>Operação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($parcelas as $parcela)
                                        <tr class="{{ $parcela->dias_atraso > 30 ? 'table-danger' : ($parcela->dias_atraso > 15 ? 'table-warning' : '') }}">
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($parcela->emprestimo->cliente_id, $parcela->emprestimo->operacao_id) }}"
                                                   class="text-primary">
                                                    {{ $parcela->emprestimo->cliente->nome }}
                                                </a>
                                            </td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" 
                                                   class="text-primary">
                                                    #{{ $parcela->emprestimo_id }}
                                                </a>
                                            </td>
                                            <td>{{ $parcela->numero }}/{{ $parcela->emprestimo->numero_parcelas }}</td>
                                            <td>{{ $parcela->emprestimo->consultor->name ?? '-' }}</td>
                                            <td>
                                                <strong>R$ {{ number_format($parcela->valor - $parcela->valor_pago, 2, ',', '.') }}</strong>
                                                @if($parcela->valor_pago > 0)
                                                    <br><small class="text-muted">
                                                        Pago: R$ {{ number_format($parcela->valor_pago, 2, ',', '.') }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                @php
                                                    $diasAtraso = $parcela->dias_atraso ?? $parcela->calcularDiasAtraso();
                                                    $badgeClass = $diasAtraso > 30 ? 'danger' : ($diasAtraso > 15 ? 'warning' : 'info');
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ $diasAtraso }} dias
                                                </span>
                                            </td>
                                            <td>{{ $parcela->emprestimo->operacao->nome }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('pagamentos.create', ['parcela_id' => $parcela->id, 'return_to' => 'parcelas_atrasadas']) }}" 
                                                       class="btn btn-sm btn-success" title="Registrar Pagamento">
                                                        <i class="bx bx-money"></i> Registrar
                                                    </a>
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Empréstimo">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @if($parcela->emprestimo->cliente->temWhatsapp())
                                                        <a href="{{ $parcela->emprestimo->cliente->whatsapp_link }}" 
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
                                            <td colspan="9" class="text-center">
                                                <p class="mb-0 py-3">Nenhuma parcela atrasada encontrada.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div class="mt-3 d-flex justify-content-end">
                            {{ $parcelas->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
    @endsection
