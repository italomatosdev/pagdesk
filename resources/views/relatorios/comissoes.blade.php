@extends('layouts.master')
@section('title')
    Cálculo de comissões
@endsection
@section('page-title')
    Cálculo de comissões
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
                    <p class="text-muted small mb-2">Selecione o período e a operação. Em cada linha, escolha o tipo de comissão (Diária = em cima do valor quitado / Mensal = em cima dos juros) e informe a taxa % para calcular.</p>
                    <form method="GET" action="{{ route('relatorios.comissoes') }}" class="row g-3 align-items-end">
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
                        <div class="col-12 col-sm-6 col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Gerar
                            </button>
                            <a href="{{ route('relatorios.comissoes') }}" class="btn btn-secondary">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Comissões por consultor ({{ $dateFrom->format('d/m/Y') }} a {{ $dateTo->format('d/m/Y') }})</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover mb-0" id="tabela-comissoes">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 52px;" title="Abrir tela de detalhe por empréstimo"></th>
                                    <th>Consultor</th>
                                    <th class="text-end">Valor quitado (principal)</th>
                                    <th class="text-end">Juros recebidos</th>
                                    <th class="text-center">Tipo de comissão</th>
                                    <th class="text-center">Taxa %</th>
                                    <th class="text-end">Comissão</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($consultoresComTotais as $row)
                                    @php
                                        $temMovimento = ($row['valor_quitado'] + $row['juros_recebidos']) > 0;
                                        $paramsDetalhe = array_filter([
                                            'consultor_id' => $row['id'],
                                            'date_from' => $dateFrom->format('Y-m-d'),
                                            'date_to' => $dateTo->format('Y-m-d'),
                                            'operacao_id' => $operacaoId,
                                        ], fn ($v) => $v !== null && $v !== '');
                                    @endphp
                                    <tr class="row-comissao"
                                        data-valor-quitado="{{ number_format($row['valor_quitado'], 2, '.', '') }}"
                                        data-juros-recebidos="{{ number_format($row['juros_recebidos'], 2, '.', '') }}">
                                        <td class="text-center align-middle">
                                            @if($temMovimento)
                                                <a href="{{ route('relatorios.comissoes-detalhe', $paramsDetalhe) }}"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Ver empréstimos e totalizadores">
                                                    <i class="bx bx-list-ul"></i>
                                                </a>
                                            @else
                                                <span class="btn btn-sm btn-outline-secondary disabled opacity-50" title="Sem movimentação no período">
                                                    <i class="bx bx-list-ul"></i>
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $row['nome'] }}</td>
                                        <td class="text-end">R$ {{ number_format($row['valor_quitado'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($row['juros_recebidos'], 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            <select class="form-select form-select-sm tipo-comissao" style="min-width: 140px;">
                                                <option value="">—</option>
                                                <option value="diaria">Diária (valor quitado)</option>
                                                <option value="mensal">Mensal (juros recebidos)</option>
                                            </select>
                                        </td>
                                        <td class="text-center" style="width: 100px;">
                                            <input type="number" class="form-control form-control-sm taxa-pct" min="0" step="0.01" placeholder="0" style="width: 80px;">
                                        </td>
                                        <td class="text-end comissao-valor fw-bold">—</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Nenhum consultor com movimentação no período. Ajuste os filtros ou verifique se há pagamentos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
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

@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function() {
    function formatarMoeda(valor) {
        if (valor == null || isNaN(valor)) return '—';
        return 'R$ ' + Number(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function calcularComissao(tr) {
        var valorQuitado = parseFloat(tr.getAttribute('data-valor-quitado')) || 0;
        var jurosRecebidos = parseFloat(tr.getAttribute('data-juros-recebidos')) || 0;
        var tipo = tr.querySelector('.tipo-comissao').value;
        var taxa = parseFloat(tr.querySelector('.taxa-pct').value) || 0;
        var base = 0;
        if (tipo === 'diaria') base = valorQuitado;
        else if (tipo === 'mensal') base = jurosRecebidos;
        var comissao = base * (taxa / 100);
        tr.querySelector('.comissao-valor').textContent = formatarMoeda(comissao);
    }

    document.querySelectorAll('#tabela-comissoes .row-comissao').forEach(function(tr) {
        tr.querySelector('.tipo-comissao').addEventListener('change', function() { calcularComissao(tr); });
        tr.querySelector('.taxa-pct').addEventListener('input', function() { calcularComissao(tr); });
    });
});
</script>
@endsection
