@extends('layouts.master')
@section('title')
    Entradas e saídas por categoria
@endsection
@section('page-title')
    Entradas e saídas por categoria
@endsection
@section('body')
    <body>
@endsection
@section('content')
    {{-- Filtros --}}
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('relatorios.entradas-saidas-categoria') }}" class="row g-3 align-items-end">
                        <div class="col-auto">
                            <label class="form-label mb-0">Período</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom->format('Y-m-d') }}" style="width: 140px;">
                                <span class="text-muted">até</span>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo->format('Y-m-d') }}" style="width: 140px;">
                            </div>
                        </div>
                        @if($operacoes->isNotEmpty())
                        <div class="col-auto">
                            <label class="form-label mb-0">Escopo</label>
                            <select name="operacao_id" class="form-select form-select-sm" style="width: 220px;">
                                <option value="">Caixa único (todas)</option>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}" {{ $operacaoId == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bx bx-search"></i> Gerar
                            </button>
                            <a href="{{ route('relatorios.entradas-saidas-categoria') }}" class="btn btn-secondary btn-sm">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Totalizadores --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Resumo do período</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-muted mb-1">Total de entradas</h6>
                                <h4 class="mb-0 text-success">R$ {{ number_format($totalEntradas, 2, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-muted mb-1">Total de saídas</h6>
                                <h4 class="mb-0 text-danger">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-muted mb-1">Saldo (entradas − saídas)</h6>
                                <h4 class="mb-0 {{ ($totalEntradas - $totalSaidas) >= 0 ? 'text-primary' : 'text-warning' }}">
                                    R$ {{ number_format($totalEntradas - $totalSaidas, 2, ',', '.') }}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráficos --}}
    @if(!empty($porCategoria) && ($totalEntradas > 0 || $totalSaidas > 0))
    <div class="row mb-3">
        <div class="col-12">
            <h5 class="mb-3">Gráficos</h5>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">Entradas vs Saídas</h6>
                </div>
                <div class="card-body">
                    <div id="chart-entradas-saidas" style="min-height: 260px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">Entradas por categoria</h6>
                </div>
                <div class="card-body">
                    <div id="chart-entradas-categoria" style="min-height: 260px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">Saídas por categoria</h6>
                </div>
                <div class="card-body">
                    <div id="chart-saidas-categoria" style="min-height: 260px;"></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Tabela por categoria --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Por categoria</h5>
                </div>
                <div class="card-body">
                    @if(empty($porCategoria))
                        <p class="text-muted mb-0">Nenhuma movimentação no período com os filtros aplicados.</p>
                        <small class="text-muted">Execute o comando <code>php artisan caixa:preencher-categorias-retroativo</code> para classificar movimentações antigas.</small>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="text-end">Entradas</th>
                                        <th class="text-end">Saídas</th>
                                        <th class="text-end">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($porCategoria as $cat)
                                        <tr>
                                            <td>{{ $cat['nome'] }}</td>
                                            <td class="text-end text-success">R$ {{ number_format($cat['entradas'], 2, ',', '.') }}</td>
                                            <td class="text-end text-danger">R$ {{ number_format($cat['saidas'], 2, ',', '.') }}</td>
                                            @php $saldo = $cat['entradas'] - $cat['saidas']; @endphp
                                            <td class="text-end {{ $saldo >= 0 ? 'text-primary' : 'text-warning' }}">
                                                R$ {{ number_format($saldo, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@if(!empty($porCategoria) && ($totalEntradas > 0 || $totalSaidas > 0))
@section('scripts')
<script src="{{ URL::asset('build/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var entradasTotal = {{ $totalEntradas }};
    var saidasTotal = {{ $totalSaidas }};
    var porCategoria = @json(array_values($porCategoria));

    // Entradas vs Saídas (donut)
    var chartEntradasSaidas = new ApexCharts(document.querySelector("#chart-entradas-saidas"), {
        series: [entradasTotal, saidasTotal],
        chart: { type: 'donut', height: 260 },
        labels: ['Entradas', 'Saídas'],
        colors: ['#198754', '#dc3545'],
        legend: { position: 'bottom', horizontalAlign: 'center' },
        tooltip: {
            y: {
                formatter: function(val) { return 'R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits: 2 }); }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val, opts) {
                var total = opts.w.config.series.reduce((a,b) => a+b, 0);
                return total > 0 ? ((opts.w.config.series[opts.seriesIndex] / total) * 100).toFixed(1) + '%' : '0%';
            }
        }
    });
    chartEntradasSaidas.render();

    // Entradas por categoria (donut)
    var entradasLabels = porCategoria.filter(c => c.entradas > 0).map(c => c.nome);
    var entradasValues = porCategoria.filter(c => c.entradas > 0).map(c => c.entradas);
    if (entradasLabels.length > 0) {
        var chartEntradasCat = new ApexCharts(document.querySelector("#chart-entradas-categoria"), {
            series: entradasValues,
            chart: { type: 'donut', height: 260 },
            labels: entradasLabels,
            colors: ['#198754', '#20c997', '#0dcaf0', '#0d6efd', '#0a58ca'],
            legend: { position: 'bottom', horizontalAlign: 'center' },
            tooltip: {
                y: { formatter: function(v) { return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }); } }
            },
            dataLabels: { enabled: true }
        });
        chartEntradasCat.render();
    } else {
        document.querySelector("#chart-entradas-categoria").innerHTML = '<p class="text-muted text-center py-4 mb-0">Sem entradas no período</p>';
    }

    // Saídas por categoria (donut)
    var saidasLabels = porCategoria.filter(c => c.saidas > 0).map(c => c.nome);
    var saidasValues = porCategoria.filter(c => c.saidas > 0).map(c => c.saidas);
    if (saidasLabels.length > 0) {
        var chartSaidasCat = new ApexCharts(document.querySelector("#chart-saidas-categoria"), {
            series: saidasValues,
            chart: { type: 'donut', height: 260 },
            labels: saidasLabels,
            colors: ['#dc3545', '#fd7e14', '#ffc107', '#6c757d', '#495057'],
            legend: { position: 'bottom', horizontalAlign: 'center' },
            tooltip: {
                y: { formatter: function(v) { return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }); } }
            },
            dataLabels: { enabled: true }
        });
        chartSaidasCat.render();
    } else {
        document.querySelector("#chart-saidas-categoria").innerHTML = '<p class="text-muted text-center py-4 mb-0">Sem saídas no período</p>';
    }
});
</script>
@endsection
@endif
