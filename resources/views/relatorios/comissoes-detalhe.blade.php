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
                @if(!empty($quitacaoTotalPorDataQuitacao))
                    — <span class="badge bg-primary">Apenas quitação total por data de quitação</span>
                @endif
            </p>
            @if(!empty($quitacaoTotalPorDataQuitacao))
                <p class="text-muted small mb-0">
                    Filtro ativo: entram apenas contratos <strong>finalizados</strong>, <strong>quitação total</strong> (sem renovação gerada) cuja <strong>data de quitação</strong>
                    (maior <code class="small">data_pagamento</code> das parcelas) está no intervalo acima.
                    Os valores por empréstimo consideram <strong>todos os pagamentos</strong> deste consultor nesses contratos, não só os com data de pagamento no período.
                </p>
            @else
                <p class="text-muted small mb-0">
                    Período aplicado à <strong>data do pagamento</strong> de cada lançamento. <strong>Valor quitado (principal)</strong> e <strong>juros recebidos</strong> usam a mesma repartição por pagamento do relatório principal.
                </p>
            @endif
        </div>
    </div>

    <div class="row mb-3 no-print">
        <div class="col-12">
            <div class="card border">
                <div class="card-body py-2">
                    <form method="get" action="{{ route('relatorios.comissoes-detalhe') }}" class="row g-2 align-items-center flex-wrap">
                        <input type="hidden" name="consultor_id" value="{{ $consultorId }}">
                        <input type="hidden" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}">
                        <input type="hidden" name="date_to" value="{{ $dateTo->format('Y-m-d') }}">
                        @if($operacaoId !== null)
                            <input type="hidden" name="operacao_id" value="{{ $operacaoId }}">
                        @endif
                        @if(!empty($frequencia))
                            <input type="hidden" name="frequencia" value="{{ $frequencia }}">
                        @endif
                        <div class="col-12 col-md-auto">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="quitacao_total_periodo_quitacao" value="1" id="chkQuitacaoTotalPeriodoQuitacao"
                                    {{ !empty($quitacaoTotalPorDataQuitacao) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="chkQuitacaoTotalPeriodoQuitacao">
                                    Apenas quitações nesse período <span class="text-muted">(por data de quitação; só quitação total)</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bx bx-filter-alt"></i> Aplicar
                            </button>
                            @if(!empty($quitacaoTotalPorDataQuitacao))
                                @php
                                    $paramsSemQuitacao = array_filter([
                                        'consultor_id' => $consultorId,
                                        'date_from' => $dateFrom->format('Y-m-d'),
                                        'date_to' => $dateTo->format('Y-m-d'),
                                        'operacao_id' => $operacaoId,
                                        'frequencia' => !empty($frequencia) ? $frequencia : null,
                                    ], fn ($v) => $v !== null && $v !== '');
                                @endphp
                                <a href="{{ route('relatorios.comissoes-detalhe', $paramsSemQuitacao) }}" class="btn btn-sm btn-outline-secondary ms-md-1">Remover filtro</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-4 col-xl-2 mb-3">
            <div class="card h-100 border">
                <div class="card-body py-3">
                    <h6 class="text-muted font-size-13 mb-1">Empréstimos</h6>
                    <h4 class="mb-0 font-size-22">{{ number_format($totais['qtd_emprestimos'], 0, ',', '.') }}</h4>
                    <small class="text-muted">{{ !empty($quitacaoTotalPorDataQuitacao) ? 'quitação total no período (data quitação)' : 'com pagamento no período' }}</small>
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
                    <h6 class="text-muted font-size-13 mb-1">{{ !empty($quitacaoTotalPorDataQuitacao) ? 'Total pago (contratos)' : 'Total pago (período)' }}</h6>
                    <h4 class="mb-0 font-size-22 text-primary">R$ {{ number_format($totais['soma_total_pago_periodo'], 2, ',', '.') }}</h4>
                    <small class="text-muted">{{ !empty($quitacaoTotalPorDataQuitacao) ? 'pagamentos do consultor nos contratos listados' : 'soma dos pagamentos' }}</small>
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
                    <p class="small mb-0 text-muted">
                        @if(!empty($quitacaoTotalPorDataQuitacao))
                            Com o filtro por <strong>data de quitação</strong>, os totais desta tela <strong>não</strong> coincidem com a linha do consultor no relatório principal (aquele usa data de pagamento no período).
                        @else
                            Principal + juros no período = total pago alocado (saldo de arredondamentos pode diferir em centavos).
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0">Empréstimos — {{ $consultorNome }}</h5>
                    @include('relatorios.partials.botoes-exportar-imprimir', ['exportRoute' => 'relatorios.comissoes-detalhe.export'])
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
                                    <th class="text-end">{{ !empty($quitacaoTotalPorDataQuitacao) ? 'Total pago (contrato)' : 'Total pago (período)' }}</th>
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
                                            @if(!empty($quitacaoTotalPorDataQuitacao))
                                                Nenhuma quitação total com data de quitação neste período para este consultor (e filtros atuais).
                                            @else
                                                Nenhum empréstimo com pagamento vinculado a parcela neste período para este consultor.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($linhas) > 0)
                                <tfoot>
                                    <tr class="table-light border-top border-2">
                                        <td colspan="3" class="bg-light"></td>
                                        <th class="text-end">Total empréstimo</th>
                                        <th class="text-end">{{ !empty($quitacaoTotalPorDataQuitacao) ? 'Total pago (contrato)' : 'Total pago (período)' }}</th>
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
