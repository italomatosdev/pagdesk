@extends('layouts.master')
@section('title')
    Radar — Consulta cadastral
@endsection
@section('page-title')
    Radar
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-radar text-primary me-2"></i>
                        Consulta por CPF ou CNPJ
                    </h4>
                    <p class="text-muted small mb-0 mt-1">Consulte pendências e empréstimos ativos do cliente no sistema (consulta interna).</p>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('radar.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">CPF ou CNPJ</label>
                            <input type="text"
                                   name="documento"
                                   class="form-control"
                                   placeholder="Apenas números ou formatado"
                                   value="{{ old('documento', $documento ?? '') }}"
                                   required>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search me-1"></i> Consultar
                            </button>
                            <a href="{{ route('radar.index') }}" class="btn btn-outline-secondary">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if($error)
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="bx bx-error-circle me-2"></i>{{ $error }}
                </div>
            </div>
        </div>
    @endif

    @if($documento !== null && $documento !== '' && !$error)
        @if(!$existe)
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Nenhum cliente encontrado com o documento informado.
                    </div>
                </div>
            </div>
        @else
            @php
                $totalAtivos = (int) ($ficha['emprestimos_ativos_total'] ?? 0);
                $valorTotalAtivos = (float) ($ficha['emprestimos_ativos_valor_total'] ?? 0);
                if ($valorTotalAtivos == 0 && !empty($ficha['ativos_por_operacao'])) {
                    $valorTotalAtivos = collect($ficha['ativos_por_operacao'])->sum('total_ativo');
                }
                $totalPendencias = (float) ($ficha['pendencias_total_em_aberto'] ?? 0);
                $temAtivoOutraOp = (bool) ($ficha['tem_ativo_em_outra_operacao'] ?? false);
                $temAtivoOutraEmp = (bool) ($ficha['tem_ativo_em_outra_empresa'] ?? false);
                $ativosPorOperacao = $ficha['ativos_por_operacao'] ?? [];
                $pendenciasPorOperacao = $ficha['pendencias_por_operacao'] ?? [];
            @endphp

            <div class="row mt-3">
                <div class="col-12">
                    @if($consultaCruzada)
                        <div class="alert alert-warning">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Este CPF/CNPJ está cadastrado em outra empresa do sistema.</strong>
                        </div>
                    @endif

                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <h6 class="font-size-15 mb-1 text-muted">Cliente</h6>
                            <h4 class="mt-2 mb-1 font-size-22">{{ $cliente['nome'] ?? '—' }}</h4>
                            <small class="text-muted">
                                <i class="bx bx-id-card me-1"></i>
                                {{ isset($cliente['documento_formatado']) ? $cliente['documento_formatado'] : ($cliente['documento'] ?? $documento) }}
                                @if(!empty($cliente['id']))
                                    <span class="badge bg-primary ms-2">ID: {{ $cliente['id'] }}</span>
                                @endif
                            </small>
                            @if(!empty($cliente['operation_clients']) && is_array($cliente['operation_clients']))
                                <div class="mt-2">
                                    <small class="text-muted">Operações vinculadas:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1 justify-content-center">
                                        @foreach($cliente['operation_clients'] as $oc)
                                            @if(!empty($oc['operacao']['nome']))
                                                <span class="badge bg-secondary">{{ $oc['operacao']['nome'] }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if(!empty($fichasPorOperacao) && is_array($fichasPorOperacao))
                                <div class="mt-3 text-start px-2">
                                    <small class="text-muted d-block mb-1">Contato por operação (ficha)</small>
                                    <div class="list-group list-group-flush border rounded small">
                                        @foreach($fichasPorOperacao as $fo)
                                            <div class="list-group-item py-2">
                                                <div class="fw-semibold">{{ $fo['operacao_nome'] ?? ('Operação #'.($fo['operacao_id'] ?? '')) }}</div>
                                                @if(!empty($fo['nome']))
                                                    <div class="text-muted">{{ $fo['nome'] }}</div>
                                                @endif
                                                @if(!empty($fo['telefone']))
                                                    <div><i class="bx bx-phone me-1"></i>{{ $fo['telefone'] }}</div>
                                                @endif
                                                @if(!empty($fo['email']))
                                                    <div><i class="bx bx-envelope me-1"></i>{{ $fo['email'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if(!empty($cliente['id']))
                                @php
                                    $oidsRadar = collect($cliente['operation_clients'] ?? [])->pluck('operacao_id')->filter()->unique()->values();
                                    if (!auth()->user()->isSuperAdmin()) {
                                        $allowedRadar = auth()->user()->getOperacoesIds();
                                        if (!empty($allowedRadar)) {
                                            $oidsRadar = $oidsRadar->filter(fn ($id) => in_array((int) $id, $allowedRadar, true))->values();
                                        }
                                    }
                                    $operacaoIdRadar = $oidsRadar->count() === 1 ? (int) $oidsRadar->first() : null;
                                @endphp
                                <div class="mt-3">
                                    <a href="{{ \App\Support\ClienteUrl::show($cliente['id'], $operacaoIdRadar) }}" class="btn btn-sm btn-primary me-1"><i class="bx bx-show"></i> Ver ficha</a>
                                    <a href="{{ route('clientes.edit', $cliente['id']) }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-edit"></i> Editar</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between">
                                        <div class="text-start">
                                            <h6 class="font-size-15 mb-0">Empréstimos ativos</h6>
                                            <h4 class="mt-3 mb-0 font-size-22">{{ $totalAtivos }}</h4>
                                            @if($valorTotalAtivos > 0)
                                                <small class="text-muted">Total: R$ {{ number_format($valorTotalAtivos, 2, ',', '.') }}</small>
                                            @else
                                                <small class="text-muted">Nenhum empréstimo</small>
                                            @endif
                                        </div>
                                        <div class="avatar">
                                            <div class="avatar-title rounded bg-primary-subtle">
                                                <i class="bx bx-wallet font-size-24 mb-0 text-primary"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between">
                                        <div class="text-start">
                                            <h6 class="font-size-15 mb-0">Pendências atrasadas</h6>
                                            <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($totalPendencias, 2, ',', '.') }}</h4>
                                            <small class="text-muted">{{ $totalPendencias > 0 ? 'Parcelas vencidas' : 'Nenhuma pendência' }}</small>
                                        </div>
                                        <div class="avatar">
                                            <div class="avatar-title rounded bg-{{ $totalPendencias > 0 ? 'danger' : 'success' }}-subtle">
                                                <i class="bx bx-{{ $totalPendencias > 0 ? 'error-circle' : 'check-circle' }} font-size-24 mb-0 text-{{ $totalPendencias > 0 ? 'danger' : 'success' }}"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($temAtivoOutraEmp)
                        <div class="alert alert-danger mb-3">
                            <h6 class="alert-heading"><i class="bx bx-error-circle me-2"></i>Atenção</h6>
                            Cliente possui <strong>{{ $totalAtivos }} empréstimo(s) ativo(s)</strong> em <strong>outra(s) empresa(s)</strong> no valor total de <strong>R$ {{ number_format($valorTotalAtivos, 2, ',', '.') }}</strong>.
                        </div>
                    @elseif($temAtivoOutraOp)
                        <div class="alert alert-warning mb-3">
                            <h6 class="alert-heading"><i class="bx bx-error-circle me-2"></i>Atenção</h6>
                            Cliente possui <strong>{{ $totalAtivos }} empréstimo(s) ativo(s)</strong> em mais de uma operação no valor total de <strong>R$ {{ number_format($valorTotalAtivos, 2, ',', '.') }}</strong>.
                        </div>
                    @elseif($totalAtivos > 0)
                        <div class="alert alert-info mb-3">
                            <h6 class="alert-heading"><i class="bx bx-info-circle me-2"></i>Informação</h6>
                            Cliente possui <strong>{{ $totalAtivos }} empréstimo(s) ativo(s)</strong> no valor total de <strong>R$ {{ number_format($valorTotalAtivos, 2, ',', '.') }}</strong>.
                        </div>
                    @else
                        <div class="alert alert-success mb-3">
                            <h6 class="alert-heading"><i class="bx bx-check-circle me-2"></i>OK</h6>
                            Nenhum empréstimo ativo encontrado.
                        </div>
                    @endif

                    @if($totalPendencias > 0)
                        <div class="alert alert-danger mb-3">
                            <h6 class="alert-heading"><i class="bx bx-error-circle me-2"></i>Pendências em aberto</h6>
                            Total de <strong>R$ {{ number_format($totalPendencias, 2, ',', '.') }}</strong> em parcelas atrasadas.
                        </div>
                    @else
                        <div class="alert alert-success mb-3">
                            <h6 class="alert-heading"><i class="bx bx-check-circle me-2"></i>OK</h6>
                            Nenhuma pendência atrasada encontrada.
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">Empréstimos por operação</h4>
                                </div>
                                <div class="card-body" style="max-height: 280px; overflow-y: auto;">
                                    @forelse($ativosPorOperacao as $item)
                                        @if((float)($item['total_ativo'] ?? 0) > 0)
                                            <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                                <div class="flex-grow-1 text-start">
                                                    <div class="fw-semibold small mb-1">{{ $item['operacao'] ?? '—' }}</div>
                                                    @if(!empty($item['empresa']) && ($item['empresa'] ?? '') !== ($item['operacao'] ?? ''))
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size: 10px;">{{ $item['empresa'] }}</span>
                                                    @endif
                                                    <div class="text-muted small">{{ $item['qtd'] ?? 0 }} empréstimo(s) ativo(s)</div>
                                                </div>
                                                <div class="fw-bold text-primary">R$ {{ number_format($item['total_ativo'] ?? 0, 2, ',', '.') }}</div>
                                            </div>
                                        @endif
                                    @empty
                                        <div class="text-center text-muted py-3 small">Nenhum empréstimo ativo</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title mb-0">Pendências por operação</h4>
                                </div>
                                <div class="card-body" style="max-height: 280px; overflow-y: auto;">
                                    @forelse($pendenciasPorOperacao as $item)
                                        @if((float)($item['total_em_aberto'] ?? 0) > 0)
                                            <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                                <div class="flex-grow-1 text-start">
                                                    <div class="fw-semibold small mb-1">{{ $item['operacao'] ?? '—' }}</div>
                                                    @if(!empty($item['empresa']) && ($item['empresa'] ?? '') !== ($item['operacao'] ?? ''))
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size: 10px;">{{ $item['empresa'] }}</span>
                                                    @endif
                                                    @if(($item['atrasadas_qtd'] ?? 0) > 0)
                                                        <div class="text-danger small">{{ $item['atrasadas_qtd'] }} atrasada(s): R$ {{ number_format($item['atrasadas_total'] ?? 0, 2, ',', '.') }}</div>
                                                    @endif
                                                </div>
                                                <div class="fw-bold text-danger">R$ {{ number_format($item['total_em_aberto'] ?? 0, 2, ',', '.') }}</div>
                                            </div>
                                        @endif
                                    @empty
                                        <div class="text-center text-muted py-3 small">Nenhuma pendência atrasada</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection
