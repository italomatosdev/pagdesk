@extends('layouts.master')
@section('title')
    Relatórios
@endsection
@section('page-title')
    Relatórios
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Relatórios disponíveis</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('relatorios.recebimento-juros-dia') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-receipt font-size-20 text-primary me-3"></i>
                            <div>
                                <h6 class="mb-1">Recebimento e juros por dia</h6>
                                <small class="text-muted">Total recebido e total de juros por dia no período, com filtro por consultor e totalizadores.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.parcelas-atrasadas') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-time-five font-size-20 text-danger me-3"></i>
                            <div>
                                <h6 class="mb-1">Parcelas atrasadas</h6>
                                <small class="text-muted">Listagem de parcelas vencidas e não pagas, com filtro por data de referência, operação, consultor e dias de atraso.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.receber-por-cliente') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-user-voice font-size-20 text-warning me-3"></i>
                            <div>
                                <h6 class="mb-1">A receber por cliente (período)</h6>
                                <small class="text-muted">Consolida cliente por cliente no período: total a receber, juros, contrato sem juros e principal com juros.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.quitacoes') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-check-circle font-size-20 text-success me-3"></i>
                            <div>
                                <h6 class="mb-1">Quitações</h6>
                                <small class="text-muted">Empréstimos finalizados no período. Filtros: período, operação, frequência e tipo (quitação total ou quitado por renovação).</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.juros-quitacoes') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-trending-up font-size-20 text-success me-3"></i>
                            <div>
                                <h6 class="mb-1">Juros por quitação</h6>
                                <small class="text-muted">Juros associados a quitações no período, com filtros alinhados ao relatório de quitações.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.valor-emprestado-principal') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-money font-size-20 text-primary me-3"></i>
                            <div>
                                <h6 class="mb-1">Valor emprestado (principal) por período</h6>
                                <small class="text-muted">Soma do principal por data de início do contrato. Filtros: período e operação.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.comissoes') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-calculator font-size-20 text-info me-3"></i>
                            <div>
                                <h6 class="mb-1">Comissões</h6>
                                <small class="text-muted">Comissão por consultor: período e operação. Tipo Diária (em cima do valor quitado) ou Mensal (em cima dos juros) e taxa %.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                        <a href="{{ route('relatorios.entradas-saidas-categoria') }}" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bx bx-transfer font-size-20 text-secondary me-3"></i>
                            <div>
                                <h6 class="mb-1">Entradas e saídas por categoria</h6>
                                <small class="text-muted">Fluxo de caixa por categoria. Filtros: período e operação.</small>
                            </div>
                            <i class="bx bx-chevron-right ms-auto"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
