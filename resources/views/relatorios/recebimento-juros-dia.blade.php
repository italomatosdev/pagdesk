@extends('layouts.master')
@section('title')
    Recebimento e juros por dia
@endsection
@section('page-title')
    Recebimento e juros por dia
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
                    <form method="GET" action="{{ route('relatorios.recebimento-juros-dia') }}">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Data inicial</label>
                                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data final</label>
                                <input type="date" name="date_to" class="form-control" value="{{ $dateTo->format('Y-m-d') }}">
                            </div>
                            @if($operacoes->isNotEmpty())
                            <div class="col-md-2">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" id="relatorio-operacao-id" class="form-select">
                                    <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-4">
                                <label class="form-label">Consultores (selecione um ou mais)</label>
                                <select name="consultor_id[]" class="form-select" id="consultores-select" multiple data-select2-placeholder="Selecione um ou mais consultores">
                                    @foreach($consultores as $c)
                                        <option value="{{ $c->id }}" {{ in_array($c->id, $consultoresIds) ? 'selected' : '' }}>{{ $c->id === auth()->id() ? $c->name . ' (Você)' : $c->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Segure Ctrl (Windows) ou Cmd (Mac) para selecionar vários.</small>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Gerar
                                </button>
                                <a href="{{ route('relatorios.recebimento-juros-dia') }}" class="btn btn-secondary">Limpar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(count($consultoresIds) > 0)
    {{-- Totalizadores --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">Totalizadores do período</h5>
                    @include('relatorios.partials.botoes-exportar-imprimir', ['exportRoute' => 'relatorios.recebimento-juros-dia.export'])
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-muted mb-1">Total recebido</h6>
                                <h4 class="mb-0 text-success">R$ {{ number_format($totalizadores['recebido'], 2, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-muted mb-1">Total investido (valor emprestado)</h6>
                                <h4 class="mb-0 text-info">R$ {{ number_format($totalizadores['investido'], 2, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="text-muted mb-1">Total de juros</h6>
                                <h4 class="mb-0 text-primary">R$ {{ number_format($totalizadores['juros'], 2, ',', '.') }}</h4>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-2">Por usuário</h6>
                    <div class="row g-2">
                        @foreach($totalizadoresPorUsuario as $userId => $tot)
                            <div class="col-md-4 col-lg-3">
                                <div class="border rounded p-2">
                                    <strong>{{ $tot['nome'] }}</strong><br>
                                    <span class="text-success">R$ {{ number_format($tot['recebido'], 2, ',', '.') }}</span> recebido |
                                    <span class="text-info">R$ {{ number_format($tot['investido'], 2, ',', '.') }}</span> investido |
                                    <span class="text-primary">R$ {{ number_format($tot['juros'], 2, ',', '.') }}</span> juros
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabela por dia, dividida por usuário --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">Recebimento e juros por dia (por usuário)</h5>
                    @include('relatorios.partials.botoes-exportar-imprimir', ['exportRoute' => 'relatorios.recebimento-juros-dia.export'])
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    @foreach($totalizadoresPorUsuario as $userId => $tot)
                                        <th colspan="3" class="text-center">{{ $tot['nome'] }}</th>
                                    @endforeach
                                    <th colspan="3" class="text-center">Total dia</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    @foreach($totalizadoresPorUsuario as $userId => $tot)
                                        <th class="text-end">Recebido</th>
                                        <th class="text-end">Investido</th>
                                        <th class="text-end">Juros</th>
                                    @endforeach
                                    <th class="text-end">Recebido</th>
                                    <th class="text-end">Investido</th>
                                    <th class="text-end">Juros</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($porDiaPorUsuario as $dia => $porUsuario)
                                    @php
                                        $totalDiaRecebido = 0;
                                        $totalDiaInvestido = 0;
                                        $totalDiaJuros = 0;
                                        foreach ($porUsuario as $uid => $v) {
                                            $totalDiaRecebido += $v['recebido'];
                                            $totalDiaInvestido += $v['investido'] ?? 0;
                                            $totalDiaJuros += $v['juros'];
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($dia)->format('d/m/Y') }}</td>
                                        @foreach($totalizadoresPorUsuario as $userId => $tot)
                                            <td class="text-end">{{ isset($porUsuario[$userId]) ? 'R$ ' . number_format($porUsuario[$userId]['recebido'], 2, ',', '.') : '-' }}</td>
                                            <td class="text-end">{{ isset($porUsuario[$userId]) ? 'R$ ' . number_format($porUsuario[$userId]['investido'] ?? 0, 2, ',', '.') : '-' }}</td>
                                            <td class="text-end">{{ isset($porUsuario[$userId]) ? 'R$ ' . number_format($porUsuario[$userId]['juros'], 2, ',', '.') : '-' }}</td>
                                        @endforeach
                                        <td class="text-end fw-bold">R$ {{ number_format($totalDiaRecebido, 2, ',', '.') }}</td>
                                        <td class="text-end fw-bold">R$ {{ number_format($totalDiaInvestido, 2, ',', '.') }}</td>
                                        <td class="text-end fw-bold">R$ {{ number_format($totalDiaJuros, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($totalizadoresPorUsuario) * 3 + 4 }}" class="text-center text-muted py-4">Nenhum pagamento no período para os consultores selecionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <i class="bx bx-info-circle"></i> Selecione ao menos um consultor e clique em <strong>Gerar</strong> para ver o relatório.
            </div>
        </div>
    </div>
    @endif

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
