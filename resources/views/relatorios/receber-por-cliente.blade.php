@extends('layouts.master')
@section('title')
    A receber por cliente
@endsection
@section('page-title')
    A receber por cliente
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('relatorios.receber-por-cliente') }}">
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
                                    <option value="">Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ $operacaoId == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-12 col-sm-6 col-md-4">
                                <label class="form-label">Consultores</label>
                                <select name="consultor_id[]" class="form-select" id="consultores-select" multiple title="Vazio = todos" data-select2-placeholder="Todos os consultores">
                                    @foreach($consultores as $c)
                                        <option value="{{ $c->id }}" {{ in_array($c->id, $consultoresIds) ? 'selected' : '' }}>{{ $c->id === auth()->id() ? $c->name . ' (Você)' : $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <div class="form-check mt-4">
                                    {{-- hidden garante somente_sem_juros=0 no GET quando o checkbox está desmarcado --}}
                                    <input type="hidden" name="somente_sem_juros" value="0">
                                    <input class="form-check-input" type="checkbox" name="somente_sem_juros" id="somente-sem-juros" value="1" {{ $somenteSemJuros ? 'checked' : '' }}>
                                    <label class="form-check-label" for="somente-sem-juros">
                                        Só contrato sem juros
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Gerar
                                </button>
                                <a href="{{ route('relatorios.receber-por-cliente') }}" class="btn btn-secondary">Limpar</a>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Totalizadores ({{ $dateFrom->format('d/m/Y') }} a {{ $dateTo->format('d/m/Y') }})</h5>
                    <span class="badge bg-info">{{ $totais['clientes'] }} cliente(s)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total a receber no período</small>
                                <div class="fw-bold text-primary">R$ {{ number_format($totais['total_a_receber_periodo'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Contrato sem juros</small>
                                <div class="fw-bold text-success">R$ {{ number_format($totais['parcela_sem_juros_contrato_no_periodo'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Principal (com juros)</small>
                                <div class="fw-bold text-info">R$ {{ number_format($totais['principal_com_juros_no_periodo'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Somente juros</small>
                                <div class="fw-bold text-warning">R$ {{ number_format($totais['somente_juros_no_periodo'], 2, ',', '.') }}</div>
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
                <div class="card-header">
                    <h5 class="card-title mb-0">Cliente por cliente</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th class="text-end">Total a receber no período</th>
                                    <th class="text-end">Contrato sem juros</th>
                                    <th class="text-end">Principal (com juros)</th>
                                    <th class="text-end">Somente juros</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $r)
                                    <tr>
                                        <td>{{ $r->cliente_nome }}</td>
                                        <td>{{ $r->cliente_documento }}</td>
                                        <td class="text-end">R$ {{ number_format((float) $r->total_a_receber_periodo, 2, ',', '.') }}</td>
                                        <td class="text-end text-success">R$ {{ number_format((float) $r->parcela_sem_juros_contrato_no_periodo, 2, ',', '.') }}</td>
                                        <td class="text-end text-info">R$ {{ number_format((float) $r->principal_com_juros_no_periodo, 2, ',', '.') }}</td>
                                        <td class="text-end text-warning">R$ {{ number_format((float) $r->somente_juros_no_periodo, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Nenhum cliente encontrado para os filtros informados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($rows->isNotEmpty())
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="2" class="text-end">TOTAIS:</td>
                                    <td class="text-end">R$ {{ number_format($totais['total_a_receber_periodo'], 2, ',', '.') }}</td>
                                    <td class="text-end text-success">R$ {{ number_format($totais['parcela_sem_juros_contrato_no_periodo'], 2, ',', '.') }}</td>
                                    <td class="text-end text-info">R$ {{ number_format($totais['principal_com_juros_no_periodo'], 2, ',', '.') }}</td>
                                    <td class="text-end text-warning">R$ {{ number_format($totais['somente_juros_no_periodo'], 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-2">
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
