@extends('layouts.master')
@section('title')
    Parcelas atrasadas
@endsection
@section('page-title')
    Parcelas atrasadas
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
                    <form method="GET" action="{{ route('relatorios.parcelas-atrasadas') }}">
                        <div class="row g-3 align-items-end mb-0">
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Situação em</label>
                                <input type="date" name="data_ref" class="form-control" value="{{ $dataRef->format('Y-m-d') }}" title="Parcelas vencidas antes desta data e ainda não pagas.">
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Vencimento de</label>
                                <input type="date" name="vencimento_de" class="form-control" value="{{ $vencimentoDe ? $vencimentoDe->format('Y-m-d') : '' }}">
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <label class="form-label">Vencimento até</label>
                                <input type="date" name="vencimento_ate" class="form-control" value="{{ $vencimentoAte ? $vencimentoAte->format('Y-m-d') : '' }}">
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
                            <div class="col-6 col-sm-4 col-md-1">
                                <label class="form-label">Mín. dias</label>
                                <input type="number" name="dias_atraso_min" class="form-control" min="0" placeholder="0" value="{{ $diasAtrasoMin !== null ? $diasAtrasoMin : '' }}">
                            </div>
                            <div class="col-12 col-sm-6 col-md-2">
                                <label class="form-label">Consultores</label>
                                <select name="consultor_id[]" class="form-select" id="consultores-select" multiple>
                                    @foreach($consultores as $c)
                                        <option value="{{ $c->id }}" {{ in_array($c->id, $consultoresIds) ? 'selected' : '' }}>{{ $c->id === auth()->id() ? $c->name . ' (Você)' : $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-search"></i> Gerar
                                </button>
                                <a href="{{ route('relatorios.parcelas-atrasadas') }}" class="btn btn-secondary">Limpar</a>
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
                    <h5 class="card-title mb-0">Parcelas atrasadas em {{ $dataRef->format('d/m/Y') }}</h5>
                    <span class="badge bg-danger">{{ $parcelas->count() }} parcela(s)</span>
                </div>
                <div class="card-body">
                    @php
                        $totalValor = $parcelas->sum('valor');
                        $totalPago = $parcelas->sum(fn($p) => (float)($p->valor_pago ?? 0));
                        $totalSaldo = $parcelas->sum('saldo_receber');
                    @endphp
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted">Valor total das parcelas</small>
                                <div class="fw-bold">R$ {{ number_format($totalValor, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted">Já recebido (parcial)</small>
                                <div class="fw-bold">R$ {{ number_format($totalPago, 2, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-light">
                                <small class="text-muted">Saldo a receber</small>
                                <div class="fw-bold text-danger">R$ {{ number_format($totalSaldo, 2, ',', '.') }}</div>
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
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-relatorio" data-bs-toggle="tab" data-bs-target="#painel-relatorio" type="button" role="tab">Relatório</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-rota" data-bs-toggle="tab" data-bs-target="#painel-rota" type="button" role="tab">Rota de cobrança</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="painel-relatorio" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Operação</th>
                                            <th>Consultor</th>
                                            <th class="text-center">Parcela</th>
                                            <th class="text-end">Vencimento</th>
                                            <th class="text-center">Dias atraso</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-end">Valor pago</th>
                                            <th class="text-end">Saldo</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($parcelas as $p)
                                            <tr>
                                                <td>
                                                    @if($p->emprestimo && $p->emprestimo->cliente)
                                                        <a href="{{ \App\Support\ClienteUrl::show($p->emprestimo->cliente_id, $p->emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($p, $fichasPorClienteOperacao ?? collect()) }}</a>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $p->emprestimo && $p->emprestimo->operacao ? $p->emprestimo->operacao->nome : '-' }}</td>
                                                <td>{{ $p->emprestimo && $p->emprestimo->consultor ? $p->emprestimo->consultor->name : '-' }}</td>
                                                <td class="text-center">{{ $p->numero }}/{{ $p->emprestimo ? $p->emprestimo->numero_parcelas : '-' }}</td>
                                                <td class="text-end">{{ $p->data_vencimento ? $p->data_vencimento->format('d/m/Y') : '-' }}</td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning text-dark">{{ $p->dias_na_data_ref }} dias</span>
                                                </td>
                                                <td class="text-end">R$ {{ number_format($p->valor, 2, ',', '.') }}</td>
                                                <td class="text-end">R$ {{ number_format($p->valor_pago ?? 0, 2, ',', '.') }}</td>
                                                <td class="text-end fw-bold">R$ {{ number_format($p->saldo_receber, 2, ',', '.') }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $p->status_cor }}">{{ $p->status_nome }}</span>
                                                    @if($p->hasSolicitacaoJurosContratoReduzidoPendente())
                                                        <span class="badge bg-info ms-1" title="Pagamento com valor inferior aguardando aprovação">Aguardando aprovação (valor inferior)</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">Nenhuma parcela atrasada para os filtros informados.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="painel-rota" role="tabpanel">
                            @php
                                $fichasRota = $fichasPorClienteOperacao ?? collect();
                                $porGrupoRota = $parcelas->groupBy(function ($p) {
                                    if (!$p->emprestimo || !$p->emprestimo->cliente) {
                                        return 'sem-cliente';
                                    }

                                    return $p->emprestimo->cliente_id.'_'.($p->emprestimo->operacao_id ?? 0);
                                });
                                $porGrupoRota = $porGrupoRota->sortBy(function ($grupo) use ($fichasRota) {
                                    $primeira = $grupo->first();
                                    $e = $primeira->emprestimo ?? null;
                                    if (!$e || !$e->cliente) {
                                        return 'zzz';
                                    }
                                    $k = $e->cliente_id.'_'.$e->operacao_id;
                                    $ficha = $fichasRota->get($k);
                                    $c = $e->cliente;
                                    $cidade = $ficha?->cidade ?? $c->cidade;
                                    $end = $ficha?->endereco ?? $c->endereco;
                                    $num = $ficha?->numero ?? $c->numero;

                                    return ($cidade ?? '').' '.($end ?? '').' '.($num ?? '');
                                });
                            @endphp
                            @if($porGrupoRota->isEmpty())
                                <p class="text-muted mb-0">Nenhuma parcela atrasada para os filtros informados.</p>
                            @else
                                <p class="text-muted small mb-3">Agrupado por cliente e operação; endereço usa a <strong>ficha da operação</strong> quando existir, senão o cadastro geral. Ordenado por cidade/endereço. Use &quot;Copiar&quot; ou &quot;Mapa&quot; para navegação.</p>
                                <div class="list-group list-group-flush">
                                    @foreach($porGrupoRota as $grupoKey => $parcelasCliente)
                                        @php
                                            $primeira = $parcelasCliente->first();
                                            $emp = $primeira->emprestimo ?? null;
                                            $cliente = $emp->cliente ?? null;
                                            $saldoCliente = $parcelasCliente->sum('saldo_receber');
                                            $kFicha = $cliente && $emp && $emp->operacao_id
                                                ? $cliente->id.'_'.$emp->operacao_id
                                                : null;
                                            $fichaRota = $kFicha ? $fichasRota->get($kFicha) : null;
                                            $enderecoCompleto = '';
                                            if ($cliente) {
                                                $estadoLinha = $fichaRota?->estado ?? $cliente->estado;
                                                $cepLinha = $fichaRota?->cep ?? $cliente->cep;
                                                $partes = array_filter([
                                                    $fichaRota?->endereco ?? $cliente->endereco ?? null,
                                                    $fichaRota?->numero ?? $cliente->numero ?? null,
                                                    $fichaRota?->cidade ?? $cliente->cidade ?? null,
                                                    $estadoLinha ? ($estadoLinha.($cepLinha ? ' - '.$cepLinha : '')) : ($cepLinha ?? null),
                                                ]);
                                                $enderecoCompleto = implode(', ', $partes);
                                            }
                                            $enderecoMapa = rawurlencode($enderecoCompleto);
                                        @endphp
                                        <div class="list-group-item d-flex flex-wrap align-items-start gap-2 py-3">
                                            <div class="flex-grow-1">
                                                <strong>
                                                    @if($cliente && $emp)
                                                        <a href="{{ \App\Support\ClienteUrl::show($cliente->id, $emp->operacao_id) }}">{{ $fichaRota?->nome ?? $cliente->nome }}</a>
                                                    @else
                                                        {{ $cliente ? $cliente->nome : 'Cliente não informado' }}
                                                    @endif
                                                </strong>
                                                @if($emp?->operacao)
                                                    <div class="text-muted small">{{ $emp->operacao->nome }}</div>
                                                @endif
                                                @if($enderecoCompleto)
                                                    <div class="text-muted small mt-1">{{ $enderecoCompleto }}</div>
                                                @else
                                                    <div class="text-muted small mt-1 fst-italic">Sem endereço cadastrado</div>
                                                @endif
                                                <div class="mt-2">
                                                    <span class="badge bg-danger">{{ $parcelasCliente->count() }} parcela(s)</span>
                                                    <span class="ms-2 fw-semibold">Saldo: R$ {{ number_format($saldoCliente, 2, ',', '.') }}</span>
                                                </div>
                                            </div>
                                            @if($enderecoCompleto)
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm copiar-endereco" data-endereco="{{ e($enderecoCompleto) }}" title="Copiar endereço">
                                                        <i class="bx bx-copy"></i> Copiar
                                                    </button>
                                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $enderecoMapa }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm" title="Abrir no Google Maps">
                                                        <i class="bx bx-map"></i> Mapa
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
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
    document.querySelectorAll('.copiar-endereco').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var endereco = this.getAttribute('data-endereco');
            if (endereco && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(endereco).then(function() {
                    var lbl = btn.querySelector('i');
                    var txt = btn.innerHTML;
                    btn.innerHTML = '<i class="bx bx-check"></i> Copiado!';
                    btn.classList.add('btn-success');
                    btn.classList.remove('btn-outline-secondary');
                    setTimeout(function() { btn.innerHTML = txt; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-secondary'); }, 1500);
                });
            }
        });
    });
});
</script>
@endsection
