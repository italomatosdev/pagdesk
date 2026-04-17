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
                    <form method="GET" action="{{ route('relatorios.valor-emprestado-principal') }}" class="relatorio-vep-filtros">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="row g-3 align-items-end">
                                    <div class="col-6 col-sm-4 col-md-2">
                                        <label for="vep_date_from" class="form-label">Data inicial</label>
                                        <input type="date" name="date_from" id="vep_date_from" class="form-control" value="{{ $dateFrom->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-6 col-sm-4 col-md-2">
                                        <label for="vep_date_to" class="form-label">Data final</label>
                                        <input type="date" name="date_to" id="vep_date_to" class="form-control" value="{{ $dateTo->format('Y-m-d') }}">
                                    </div>
                                    @if($operacoes->isNotEmpty())
                                        <div class="col-6 col-sm-4 col-md-2">
                                            <label for="vep_operacao_id" class="form-label">Operação</label>
                                            <select name="operacao_id" id="vep_operacao_id" class="form-select">
                                                <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                                @foreach($operacoes as $op)
                                                    <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase small fw-semibold text-muted mb-2 d-block">Status do contrato</label>
                                <div class="p-3 rounded border bg-light">
                                    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
                                        @foreach($statusPermitidosRelatorio as $st)
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="status[]" value="{{ $st }}" id="status_{{ $st }}"
                                                    {{ in_array($st, $statusesFiltro ?? [], true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="status_{{ $st }}">
                                                    @switch($st)
                                                        @case('aprovado') Aprovado @break
                                                        @case('ativo') Ativo @break
                                                        @case('finalizado') Finalizado @break
                                                        @default {{ $st }}
                                                    @endswitch
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="small text-muted mb-0 mt-2 pt-2 border-top border-light-subtle">
                                        Nenhuma caixa marcada ao enviar o formulário equivale aos três status. Marque só os que deseja incluir na listagem.
                                    </p>
                                </div>
                            </div>
                            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3 pt-1 border-top">
                                <span class="small text-muted mb-0 d-none d-md-inline">Use <strong>Gerar</strong> para aplicar os filtros; <strong>Limpar</strong> remove datas e volta ao padrão do mês e dos três status.</span>
                                <div class="d-flex flex-wrap gap-2 ms-md-auto">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bx bx-search"></i> Gerar relatório
                                    </button>
                                    <a href="{{ route('relatorios.valor-emprestado-principal') }}" class="btn btn-outline-secondary">Limpar filtros</a>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="mt-4 pt-3 border-top">
                        <p class="text-muted small mb-0">
                            <strong>Colunas:</strong> <strong>Principal</strong> = valor total do contrato.
                            <strong>Juros (contrato)</strong> = soma das parcelas menos o principal (quando há parcelas).
                            <strong>Total a pagar</strong> = principal + juros (contrato); sem parcelas de juros, coincide com o principal.
                            <strong>Total pago</strong> = soma de <code>valor_pago</code> nas parcelas.
                            <strong>Saldo devedor</strong> = parcelas em aberto (mesma regra da quitação).
                        </p>
                    </div>
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
                    <div class="row g-2 row-cols-2 row-cols-md-3 row-cols-xl-5">
                        <div class="col">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total principal</small>
                                <div class="fw-bold">R$ {{ number_format($totalPrincipal, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total juros (contrato)</small>
                                <div class="fw-bold">R$ {{ number_format($totalJurosContrato ?? 0, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total a pagar (principal + juros)</small>
                                <div class="fw-bold">R$ {{ number_format($totalAPagarCronograma ?? 0, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total pago (parcelas)</small>
                                <div class="fw-bold">R$ {{ number_format($totalPagoParcelas ?? 0, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total saldo devedor</small>
                                <div class="fw-bold">R$ {{ number_format($totalSaldoDevedor ?? 0, 2, ',', '.') }}</div>
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
                                    <th class="text-center" style="width:3rem;">#</th>
                                    <th class="text-center">Contrato</th>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th>Consultor</th>
                                    <th class="text-center">Data início</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Juros (contrato)</th>
                                    <th class="text-end">Total a pagar</th>
                                    <th class="text-end">Total pago</th>
                                    <th class="text-end">Saldo devedor</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($emprestimos as $e)
                                    @php
                                        $m = $metricasPorEmprestimoId[$e->id] ?? ['juros_contrato' => 0, 'total_a_pagar' => 0, 'total_pago' => 0, 'saldo_devedor' => 0];
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted">{{ $loop->iteration }}</td>
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
                                        <td class="text-end">R$ {{ number_format($m['juros_contrato'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($m['total_a_pagar'] ?? 0, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($m['total_pago'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($m['saldo_devedor'], 2, ',', '.') }}</td>
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
                                        <td colspan="12" class="text-center text-muted py-4">Nenhum contrato no período para os filtros informados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($emprestimos->isNotEmpty())
                                <tfoot class="table-light">
                                    <tr class="border-top border-secondary border-opacity-25">
                                        <td colspan="6"></td>
                                        <th scope="col" class="text-end small fw-semibold text-muted py-2">Principal</th>
                                        <th scope="col" class="text-end small fw-semibold text-muted py-2">Juros (contrato)</th>
                                        <th scope="col" class="text-end small fw-semibold text-muted py-2">Total a pagar</th>
                                        <th scope="col" class="text-end small fw-semibold text-muted py-2">Total pago</th>
                                        <th scope="col" class="text-end small fw-semibold text-muted py-2">Saldo devedor</th>
                                        <td></td>
                                    </tr>
                                    <tr class="fw-semibold">
                                        <td colspan="6" class="text-end align-middle">Totais</td>
                                        <td class="text-end">R$ {{ number_format($totalPrincipal, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totalJurosContrato ?? 0, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totalAPagarCronograma ?? 0, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totalPagoParcelas ?? 0, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totalSaldoDevedor ?? 0, 2, ',', '.') }}</td>
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
