@extends('layouts.master')
@section('title')
    Valor emprestado (principal) por período
@endsection
@section('page-title')
    Valor emprestado (principal) por período
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
                    <form method="GET" action="{{ route('relatorios.valor-emprestado-principal') }}">
                        <div class="row g-3 align-items-end">
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
                                <select name="operacao_id" class="form-select">
                                    <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-12 col-sm-6 col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Gerar
                                </button>
                                <a href="{{ route('relatorios.valor-emprestado-principal') }}" class="btn btn-secondary">Limpar</a>
                            </div>
                        </div>
                    </form>
                    <p class="text-muted small mb-0 mt-2">
                        Critério: contratos com <strong>data de início</strong> no período (não é data de liberação nem de aprovação).
                        Status: aprovado, ativo ou finalizado. Soma do campo <strong>valor total</strong> (principal) do contrato.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Resumo ({{ $dateFrom->format('d/m/Y') }} a {{ $dateTo->format('d/m/Y') }})</h5>
                    <span class="badge bg-primary">{{ $qtdEmprestimos }} contrato(s)</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted">Total principal (valor total dos contratos)</small>
                                <div class="fw-bold">R$ {{ number_format($totalPrincipal, 2, ',', '.') }}</div>
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
                    <h5 class="card-title mb-0">Listagem</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Contrato</th>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th>Consultor</th>
                                    <th class="text-center">Data início</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($emprestimos as $e)
                                    <tr>
                                        <td class="text-center">
                                            <a href="{{ route('emprestimos.show', $e->id) }}">#{{ $e->id }}</a>
                                        </td>
                                        <td>
                                            @if($e->cliente)
                                                <a href="{{ \App\Support\ClienteUrl::show($e->cliente_id, $e->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($e, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $e->operacao ? $e->operacao->nome : '—' }}</td>
                                        <td>{{ $e->consultor ? $e->consultor->name : '—' }}</td>
                                        <td class="text-center">{{ $e->data_inicio ? \Carbon\Carbon::parse($e->data_inicio)->format('d/m/Y') : '—' }}</td>
                                        <td class="text-end">R$ {{ number_format($e->valor_total, 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @switch($e->status)
                                                @case('aprovado')
                                                    <span class="badge bg-secondary">Aprovado</span>
                                                    @break
                                                @case('ativo')
                                                    <span class="badge bg-success">Ativo</span>
                                                    @break
                                                @case('finalizado')
                                                    <span class="badge bg-dark">Finalizado</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-light text-dark">{{ $e->status }}</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Nenhum contrato no período para os filtros informados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($emprestimos->isNotEmpty())
                                <tfoot>
                                    <tr class="table-light border-top border-2">
                                        <td colspan="5" class="bg-light"></td>
                                        <th class="text-end">Principal</th>
                                        <td class="bg-light"></td>
                                    </tr>
                                    <tr class="fw-semibold table-light">
                                        <td colspan="5" class="text-end">Totais</td>
                                        <td class="text-end">R$ {{ number_format($totalPrincipal, 2, ',', '.') }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-2 mb-5 pb-5">
        <div class="col-12">
            <a href="{{ route('relatorios.index') }}" class="btn btn-secondary">
                <i class="bx bx-arrow-back"></i> Voltar aos relatórios
            </a>
        </div>
    </div>
@endsection
