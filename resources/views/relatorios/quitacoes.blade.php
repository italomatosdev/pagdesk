@extends('layouts.master')
@section('title')
    Quitações
@endsection
@section('page-title')
    Quitações
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row no-print">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('relatorios.quitacoes') }}">
                        <div class="row g-3 align-items-end mb-0">
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Data inicial</label>
                                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom->format('Y-m-d') }}">
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Data final</label>
                                <input type="date" name="date_to" class="form-control" value="{{ $dateTo->format('Y-m-d') }}">
                            </div>
                            @if($operacoes->isNotEmpty())
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" id="relatorio-operacao-id" class="form-select">
                                    <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Frequência</label>
                                <select name="frequencia" class="form-select">
                                    <option value="">Todas</option>
                                    <option value="diaria" {{ $frequencia === 'diaria' ? 'selected' : '' }}>Diária</option>
                                    <option value="semanal" {{ $frequencia === 'semanal' ? 'selected' : '' }}>Semanal</option>
                                    <option value="quinzenal" {{ $frequencia === 'quinzenal' ? 'selected' : '' }}>Quinzenal (15 dias)</option>
                                    <option value="mensal" {{ $frequencia === 'mensal' ? 'selected' : '' }}>Mensal</option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Tipo de quitação</label>
                                <select name="tipo_quitacao" class="form-select">
                                    <option value="" {{ ($tipoQuitacao === null || $tipoQuitacao === '') ? 'selected' : '' }}>Todos</option>
                                    <option value="total" {{ $tipoQuitacao === 'total' ? 'selected' : '' }}>Quitação total</option>
                                    <option value="renovacao" {{ $tipoQuitacao === 'renovacao' ? 'selected' : '' }}>Quitado por renovação</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 align-items-end mt-2">
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label">Consultores</label>
                                <select name="consultor_id[]" class="form-select" id="consultores-select" multiple title="Vazio = todos" data-select2-placeholder="Todos os consultores">
                                    @foreach($consultores as $c)
                                        <option value="{{ $c->id }}" {{ in_array($c->id, $consultoresIds) ? 'selected' : '' }}>{{ $c->id === auth()->id() ? $c->name . ' (Você)' : $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Gerar
                                </button>
                                <a href="{{ route('relatorios.quitacoes') }}" class="btn btn-secondary">Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="card-title mb-0">Quitações no período ({{ $dateFrom->format('d/m/Y') }} a {{ $dateTo->format('d/m/Y') }})</h5>
                        <span class="badge bg-success">{{ $emprestimos->count() }} empréstimo(s)</span>
                    </div>
                    @include('relatorios.partials.botoes-exportar-imprimir', ['exportRoute' => 'relatorios.quitacoes.export'])
                </div>
                <div class="card-body">
                    @php
                        $qtdTotal = $emprestimos->where('renovacoes_count', 0)->count();
                        $qtdRenovacao = $emprestimos->where('renovacoes_count', '>', 0)->count();
                    @endphp
                    <div class="row mb-3 g-2">
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Valor total quitado (principal)</small>
                                <div class="fw-bold">R$ {{ number_format($totalPrincipalQuitadoRelatorio, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total pago (bruto)</small>
                                <div class="fw-bold">R$ {{ number_format($totalPagoBrutoRelatorio, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Lucro <span class="text-muted fw-normal">(neste relatório)</span></small>
                                <div class="fw-bold">R$ {{ number_format($totalLucroRelatorioQuitacoes, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-6">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Quitação total</small>
                                <div class="fw-bold text-success">{{ $qtdTotal }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-6">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Quitado por renovação</small>
                                <div class="fw-bold text-info">{{ $qtdRenovacao }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">Listagem</h5>
                    @include('relatorios.partials.botoes-exportar-imprimir', ['exportRoute' => 'relatorios.quitacoes.export'])
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th>Consultor</th>
                                    <th class="text-end">Valor total</th>
                                    <th class="text-end">Total pago (bruto)</th>
                                    <th class="text-end">Lucro <span class="text-muted fw-normal small">(relatório)</span></th>
                                    <th class="text-center">Data quitação</th>
                                    <th class="text-center">Frequência</th>
                                    <th class="text-center">Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($emprestimos as $e)
                                    <tr>
                                        <td>
                                            @if($e->cliente)
                                                <a href="{{ \App\Support\ClienteUrl::show($e->cliente_id, $e->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($e, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $e->operacao ? $e->operacao->nome : '-' }}</td>
                                        <td>{{ $e->consultor ? $e->consultor->name : '-' }}</td>
                                        <td class="text-end">R$ {{ number_format($e->valor_total, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($e->valor_total_pago_bruto ?? 0, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($e->lucro_relatorio_quitacao ?? 0, 2, ',', '.') }}</td>
                                        <td class="text-center">{{ $e->data_quitacao ? \Carbon\Carbon::parse($e->data_quitacao)->format('d/m/Y') : '-' }}</td>
                                        <td class="text-center">{{ $e->frequencia ? ucfirst($e->frequencia) : '-' }}</td>
                                        <td class="text-center">
                                            @if($e->renovacoes_count > 0)
                                                <span class="badge bg-info">Quitado por renovação</span>
                                            @else
                                                <span class="badge bg-success">Quitação total</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">Nenhuma quitação no período para os filtros informados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-2 no-print">
        <div class="col-12">
            <a href="{{ route('relatorios.index') }}" class="btn btn-secondary">
                <i class="bx bx-arrow-back"></i> Voltar aos relatórios
            </a>
        </div>
    </div>
@endsection

@section('scripts')
@parent
@include('relatorios.partials.consultores-operacao-ajax')
@endsection
