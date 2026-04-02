@extends('layouts.master')
@section('title')
    Detalhe — Comissões
@endsection
@section('page-title')
    Detalhe por empréstimo — {{ $consultorNome }}
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row mb-3">
        <div class="col-12">
            <p class="text-muted small mb-0">
                Período: <strong>{{ $dateFrom->format('d/m/Y') }}</strong> a <strong>{{ $dateTo->format('d/m/Y') }}</strong>
                @if($operacaoId !== null && $operacoes->firstWhere('id', $operacaoId))
                    — Operação: <strong>{{ $operacoes->firstWhere('id', $operacaoId)->nome }}</strong>
                @elseif($operacaoId === null)
                    — <strong>Todas as operações</strong> (no seu escopo)
                @endif
                @if(!empty($frequencia))
                    @php
                        $freqDetalhe = ['diaria' => 'Diária', 'semanal' => 'Semanal', 'mensal' => 'Mensal'][$frequencia] ?? ucfirst($frequencia);
                    @endphp
                    — Frequência: <strong>{{ $freqDetalhe }}</strong>
                @else
                    — Frequência: <strong>Todas</strong>
                @endif
            </p>
            <p class="text-muted small mb-0">
                Valores do período; <strong>valor quitado (principal)</strong> e <strong>juros recebidos</strong> usam a mesma repartição por pagamento do relatório principal.
            </p>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 border">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Empréstimos</h6>
                    <h4 class="mb-0 font-size-22">{{ number_format($totais['qtd_emprestimos'], 0, ',', '.') }}</h4>
                    <small class="text-muted">com pagamento no período</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 border">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Soma total contratos</h6>
                    <h4 class="mb-0 font-size-22">R$ {{ number_format($totais['soma_valor_total_contratos'], 2, ',', '.') }}</h4>
                    <small class="text-muted">principal de cada contrato</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 border border-primary border-opacity-25">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Total pago (período)</h6>
                    <h4 class="mb-0 font-size-22 text-primary">R$ {{ number_format($totais['soma_total_pago_periodo'], 2, ',', '.') }}</h4>
                    <small class="text-muted">soma dos pagamentos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 border border-success border-opacity-25">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Valor quitado (principal)</h6>
                    <h4 class="mb-0 font-size-22 text-success">R$ {{ number_format($totais['soma_valor_quitado'], 2, ',', '.') }}</h4>
                    <small class="text-muted">base comissão diária</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 border border-info border-opacity-25">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Juros recebidos</h6>
                    <h4 class="mb-0 font-size-22 text-info">R$ {{ number_format($totais['soma_juros_recebidos'], 2, ',', '.') }}</h4>
                    <small class="text-muted">base comissão mensal</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 bg-light">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Confere com o relatório</h6>
                    <p class="small mb-0 text-muted">Principal + juros no período = total pago alocado (saldo de arredondamentos pode diferir em centavos).</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0">Empréstimos — {{ $consultorNome }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Empréstimo</th>
                                    <th>Cliente</th>
                                    <th>Consultor (contrato)</th>
                                    <th class="text-end">Total empréstimo</th>
                                    <th class="text-end">Total pago (período)</th>
                                    <th class="text-end">Valor quitado (principal)</th>
                                    <th class="text-end">Juros recebidos</th>
                                    <th class="text-center">Data quitação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($linhas as $l)
                                    <tr>
                                        <td>
                                            <a href="{{ route('emprestimos.show', $l['emprestimo_id']) }}">#{{ $l['emprestimo_id'] }}</a>
                                        </td>
                                        <td>{{ $l['cliente_nome'] }}</td>
                                        <td>{{ $l['consultor_nome'] }}</td>
                                        <td class="text-end">R$ {{ number_format($l['valor_total'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($l['total_pago_periodo'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($l['valor_quitado'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($l['juros_recebidos'], 2, ',', '.') }}</td>
                                        <td class="text-center">{{ $l['data_quitacao'] ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Nenhum empréstimo com pagamento vinculado a parcela neste período para este consultor.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($linhas) > 0)
                                <tfoot>
                                    <tr class="table-light border-top border-2">
                                        <td colspan="3" class="bg-light"></td>
                                        <th class="text-end">Total empréstimo</th>
                                        <th class="text-end">Total pago (período)</th>
                                        <th class="text-end">Valor quitado (principal)</th>
                                        <th class="text-end">Juros recebidos</th>
                                        <td class="bg-light"></td>
                                    </tr>
                                    <tr class="fw-semibold table-light">
                                        <td colspan="3" class="text-end">Totais</td>
                                        <td class="text-end">R$ {{ number_format($totais['soma_valor_total_contratos'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totais['soma_total_pago_periodo'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totais['soma_valor_quitado'], 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($totais['soma_juros_recebidos'], 2, ',', '.') }}</td>
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

    <div class="row mt-3 mb-5 pb-5">
        <div class="col-12">
            <a href="{{ $urlVoltarComissoes }}" class="btn btn-primary">
                <i class="bx bx-arrow-back"></i> Voltar ao relatório de comissões
            </a>
            <a href="{{ route('relatorios.index') }}" class="btn btn-secondary ms-1">
                Relatórios
            </a>
        </div>
    </div>
@endsection
