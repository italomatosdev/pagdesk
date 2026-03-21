@extends('layouts.master')
@section('title')
    Cobranças do Dia
@endsection
@section('page-title')
    Cobranças do Dia
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <!-- Filtros -->
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('cobrancas.index') }}">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Operação</label>
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ $operacaoId == $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                    <a href="{{ route('cobrancas.index') }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Totalizadores (estilo dashboard) -->
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-md-6 col-xl-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-0 font-size-15">Valor vencendo hoje</h6>
                                        <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($valorVencendoHoje ?? 0, 2, ',', '.') }}</h4>
                                        <small class="text-muted">{{ $vencendoHoje->count() }} parcela(s)</small>
                                    </div>
                                    <div class="">
                                        <div class="avatar">
                                            <div class="avatar-title rounded bg-warning-subtle">
                                                <i class="bx bx-calendar-check font-size-24 mb-0 text-warning"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-0 font-size-15">Cobranças hoje</h6>
                                        <h4 class="mt-3 mb-0 font-size-22">{{ number_format($vencendoHoje->count(), 0, ',', '.') }}</h4>
                                        <small class="text-muted">parcela(s) vencendo hoje</small>
                                    </div>
                                    <div class="">
                                        <div class="avatar">
                                            <div class="avatar-title rounded bg-info-subtle">
                                                <i class="bx bx-list-check font-size-24 mb-0 text-info"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-0 font-size-15">Valor em atraso</h6>
                                        <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($valorAtrasado ?? 0, 2, ',', '.') }}</h4>
                                        <small class="text-muted">{{ $atrasadas->count() }} parcela(s)</small>
                                    </div>
                                    <div class="">
                                        <div class="avatar">
                                            <div class="avatar-title rounded bg-danger-subtle">
                                                <i class="bx bx-error-circle font-size-24 mb-0 text-danger"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-0 font-size-15">Cobranças atrasadas</h6>
                                        <h4 class="mt-3 mb-0 font-size-22">{{ number_format($atrasadas->count(), 0, ',', '.') }}</h4>
                                        <small class="text-muted">parcela(s) em atraso</small>
                                    </div>
                                    <div class="">
                                        <div class="avatar">
                                            <div class="avatar-title rounded bg-secondary-subtle">
                                                <i class="bx bx-time-five font-size-24 mb-0 text-secondary"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vencendo Hoje -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="bx bx-calendar-check text-warning"></i> 
                            Vencendo Hoje ({{ $vencendoHoje->count() }})
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Empréstimo</th>
                                        <th>Parcela</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($vencendoHoje as $parcela)
                                        <tr>
                                            <td>{{ $parcela->emprestimo->cliente->nome }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $parcela->emprestimo->id) }}">#{{ $parcela->emprestimo->id }}</a>
                                            </td>
                                            <td>{{ $parcela->numero }}/{{ $parcela->emprestimo->numero_parcelas }}</td>
                                            <td>R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo->id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver empréstimo">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @php
                                                        $fichaWaCob = ($fichasContatoPorClienteOperacao ?? collect())->get($parcela->emprestimo->cliente_id.'_'.$parcela->emprestimo->operacao_id);
                                                    @endphp
                                                    @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaWaCob, $parcela->emprestimo->cliente))
                                                        <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaWaCob, $parcela->emprestimo->cliente) }}"
                                                           target="_blank"
                                                           class="btn btn-sm btn-success"
                                                           title="Falar no WhatsApp">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhuma parcela vencendo hoje.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Atrasadas -->
            <div class="col-12 mt-3">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="card-title mb-0">
                            <i class="bx bx-error-circle"></i> 
                            Atrasadas ({{ $atrasadas->count() }})
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Empréstimo</th>
                                        <th>Parcela</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Dias Atraso</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($atrasadas as $parcela)
                                        <tr class="{{ $parcela->dias_atraso > 30 ? 'table-danger' : '' }}">
                                            <td>{{ $parcela->emprestimo->cliente->nome }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $parcela->emprestimo->id) }}">#{{ $parcela->emprestimo->id }}</a>
                                            </td>
                                            <td>{{ $parcela->numero }}/{{ $parcela->emprestimo->numero_parcelas }}</td>
                                            <td>R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-danger">{{ $parcela->dias_atraso }} dias</span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo->id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver empréstimo">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @php
                                                        $fichaWaCob = ($fichasContatoPorClienteOperacao ?? collect())->get($parcela->emprestimo->cliente_id.'_'.$parcela->emprestimo->operacao_id);
                                                    @endphp
                                                    @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaWaCob, $parcela->emprestimo->cliente))
                                                        <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaWaCob, $parcela->emprestimo->cliente) }}"
                                                           target="_blank"
                                                           class="btn btn-sm btn-success"
                                                           title="Falar no WhatsApp">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">Nenhuma parcela atrasada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js já carregado no layout master -->
    @endsection