@extends('layouts.master')

@section('title')
    Cheques
@endsection

@section('page-title')
    {{ $titulo ?? 'Cheques' }}
@endsection

@section('content')
    @php
        $totais = $totais ?? null;
        $totalQtd = $totais ? (int) $totais->total : 0;
        $totalValorBruto = $totais ? (float) $totais->valor_bruto : 0;
        $totalValorLiquido = $totais ? (float) $totais->valor_liquido : 0;
    @endphp
    <!-- Cards de totais -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-receipt display-4 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Total de Cheques</h6>
                            <h4 class="mb-0">{{ number_format($totalQtd, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-money display-4 text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Valor Total (Bruto)</h6>
                            <h4 class="mb-0">R$ {{ number_format($totalValorBruto, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-info bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bx bx-wallet display-4 text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Valor Líquido</h6>
                            <h4 class="mb-0">R$ {{ number_format($totalValorLiquido, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-money"></i>
                        {{ $titulo ?? 'Cheques' }}
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <form method="GET" class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="aguardando" {{ ($filtros['status'] ?? '') === 'aguardando' ? 'selected' : '' }}>Aguardando</option>
                                    <option value="depositado" {{ ($filtros['status'] ?? '') === 'depositado' ? 'selected' : '' }}>Depositado</option>
                                    <option value="compensado" {{ ($filtros['status'] ?? '') === 'compensado' ? 'selected' : '' }}>Compensado</option>
                                    <option value="devolvido" {{ ($filtros['status'] ?? '') === 'devolvido' ? 'selected' : '' }}>Devolvido</option>
                                    <option value="cancelado" {{ ($filtros['status'] ?? '') === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Vencimento (de)</label>
                                <input type="date"
                                       name="data_vencimento_de"
                                       class="form-control"
                                       value="{{ $filtros['data_vencimento_de'] ?? '' }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Vencimento (até)</label>
                                <input type="date"
                                       name="data_vencimento_ate"
                                       class="form-control"
                                       value="{{ $filtros['data_vencimento_ate'] ?? '' }}">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="d-flex w-100 gap-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-search-alt"></i> Filtrar
                                    </button>
                                    <a href="{{ route(request()->route()->getName()) }}" class="btn btn-light">
                                        <i class="bx bx-reset"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if($cheques->count() === 0)
                        <div class="alert alert-warning mb-0">
                            <i class="bx bx-info-circle"></i>
                            Nenhum cheque encontrado para os filtros informados.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Operação</th>
                                        <th>Banco</th>
                                        <th>Agência</th>
                                        <th>Conta</th>
                                        <th>Nº Cheque</th>
                                        <th>Vencimento</th>
                                        <th>Dias</th>
                                        <th>Valor</th>
                                        <th>Juros</th>
                                        <th>Valor Líquido</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cheques as $cheque)
                                        <tr>
                                            <td>{{ $cheques->firstItem() + $loop->index }}</td>
                                            <td>
                                                @if($cheque->emprestimo && $cheque->emprestimo->cliente)
                                                    <a href="{{ route('clientes.show', $cheque->emprestimo->cliente->id) }}">
                                                        {{ $cheque->emprestimo->cliente->nome }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($cheque->emprestimo && $cheque->emprestimo->operacao)
                                                    {{ $cheque->emprestimo->operacao->nome }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $cheque->banco }}</td>
                                            <td>{{ $cheque->agencia }}</td>
                                            <td>{{ $cheque->conta }}</td>
                                            <td><strong>{{ $cheque->numero_cheque }}</strong></td>
                                            <td>{{ $cheque->data_vencimento?->format('d/m/Y') }}</td>
                                            <td>
                                                @php
                                                    $diasAte = $cheque->calcularDiasAteVencimento();
                                                    $diasAtraso = $cheque->calcularDiasEmAtraso();
                                                @endphp
                                                @if($cheque->isVencido())
                                                    <span class="text-danger">{{ $diasAtraso }} dia(s) em atraso</span>
                                                @else
                                                    {{ $diasAte }} dia(s) até vencer
                                                @endif
                                            </td>
                                            <td>R$ {{ number_format($cheque->valor_cheque, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($cheque->valor_juros, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($cheque->valor_liquido, 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $cheque->status_cor }}">
                                                    {{ $cheque->status_nome }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($cheque->emprestimo)
                                                    <a href="{{ route('emprestimos.show', $cheque->emprestimo->id) }}"
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="Ver Empréstimo">
                                                        <i class="bx bx-link-external"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <small class="text-muted">
                                    Mostrando {{ $cheques->firstItem() }} até {{ $cheques->lastItem() }}
                                    de {{ $cheques->total() }} registros
                                </small>
                            </div>
                            <div>
                                {{ $cheques->withQueryString()->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

