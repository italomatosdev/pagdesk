@extends('layouts.master')
@section('title')
    Juros e Valores por Quitação
@endsection
@section('page-title')
    Juros e Valores por Quitação
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
                    <form method="GET" action="{{ route('relatorios.juros-quitacoes') }}">
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
                                <select name="operacao_id" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ $operacaoId == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Tipo de Empréstimo</label>
                                <select name="tipo_emprestimo" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($tipoLabels as $valor => $label)
                                        <option value="{{ $valor }}" {{ $tipoEmprestimo === $valor ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Frequência</label>
                                <select name="frequencia" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach($freqLabels as $valor => $label)
                                        <option value="{{ $valor }}" {{ $frequencia === $valor ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
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
                                <select name="consultor_id[]" class="form-select" id="consultores-select" multiple title="Vazio = todos">
                                    @foreach($consultores as $c)
                                        <option value="{{ $c->id }}" {{ in_array($c->id, $consultoresIds) ? 'selected' : '' }}>{{ $c->id === auth()->id() ? $c->name . ' (Você)' : $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Gerar
                                </button>
                                <a href="{{ route('relatorios.juros-quitacoes') }}" class="btn btn-secondary">Limpar</a>
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
                    <span class="badge bg-success">{{ $totais['quantidade'] }} quitação(ões)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Valor Emprestado</small>
                                <div class="fw-bold text-primary">R$ {{ number_format($totais['valor_emprestado'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Valor Recebido</small>
                                <div class="fw-bold text-success">R$ {{ number_format($totais['valor_recebido'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Juros Contrato</small>
                                <div class="fw-bold text-info">R$ {{ number_format($totais['juros_contrato'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Juros Atraso</small>
                                <div class="fw-bold text-warning">R$ {{ number_format($totais['juros_atraso'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Total de Juros</small>
                                <div class="fw-bold text-danger">R$ {{ number_format($totais['total_juros'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="border rounded p-2 bg-light h-100">
                                <small class="text-muted">Lucro Bruto</small>
                                <div class="fw-bold" style="color: #28a745;">R$ {{ number_format($totais['valor_recebido'] - $totais['valor_emprestado'], 2, ',', '.') }}</div>
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
                    <h5 class="card-title mb-0">Listagem de Quitações</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th class="text-center">Tipo</th>
                                    <th class="text-center">Frequência</th>
                                    <th class="text-end">Emprestado</th>
                                    <th class="text-end">Recebido</th>
                                    <th class="text-end">Juros Contrato</th>
                                    <th class="text-end">Juros Atraso</th>
                                    <th class="text-end">Total Juros</th>
                                    <th class="text-center">Data Quitação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($emprestimos as $e)
                                    <tr>
                                        <td>
                                            <a href="{{ route('emprestimos.show', $e->id) }}" class="text-decoration-none">
                                                {{ $e->cliente?->nome ?? '-' }}
                                            </a>
                                        </td>
                                        <td>{{ $e->operacao?->nome ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ $e->tipo_label }}</span>
                                        </td>
                                        <td class="text-center">{{ $e->frequencia_label }}</td>
                                        <td class="text-end">R$ {{ number_format($e->valor_emprestado, 2, ',', '.') }}</td>
                                        <td class="text-end text-success">R$ {{ number_format($e->valor_recebido, 2, ',', '.') }}</td>
                                        <td class="text-end text-info">R$ {{ number_format($e->juros_contrato, 2, ',', '.') }}</td>
                                        <td class="text-end text-warning">R$ {{ number_format($e->juros_atraso, 2, ',', '.') }}</td>
                                        <td class="text-end text-danger fw-bold">R$ {{ number_format($e->total_juros, 2, ',', '.') }}</td>
                                        <td class="text-center">{{ $e->data_quitacao ? \Carbon\Carbon::parse($e->data_quitacao)->format('d/m/Y') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">Nenhuma quitação encontrada para os filtros informados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($emprestimos->isNotEmpty())
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="4" class="text-end">TOTAIS:</td>
                                    <td class="text-end">R$ {{ number_format($totais['valor_emprestado'], 2, ',', '.') }}</td>
                                    <td class="text-end text-success">R$ {{ number_format($totais['valor_recebido'], 2, ',', '.') }}</td>
                                    <td class="text-end text-info">R$ {{ number_format($totais['juros_contrato'], 2, ',', '.') }}</td>
                                    <td class="text-end text-warning">R$ {{ number_format($totais['juros_atraso'], 2, ',', '.') }}</td>
                                    <td class="text-end text-danger">R$ {{ number_format($totais['total_juros'], 2, ',', '.') }}</td>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#consultores-select').select2({ theme: 'bootstrap-5', placeholder: 'Todos os consultores', allowClear: true });
    }
});
</script>
@endsection
