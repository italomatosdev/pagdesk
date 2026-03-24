@extends('layouts.master')
@section('title')
    Relatório de Renovações
@endsection
@section('page-title')
    Relatório de Renovações
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('renovacoes.index') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Operação</label>
                            <select name="operacao_id" class="form-select">
                                <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas as Operações</option>
                                @foreach($operacoes as $operacao)
                                    <option value="{{ $operacao->id }}" {{ (int) ($operacaoId ?? 0) === (int) $operacao->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>
                                        {{ $operacao->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cliente</label>
                            <input type="text" name="cliente_busca" class="form-control"
                                   placeholder="Nome ou CPF/CNPJ"
                                   value="{{ request('cliente_busca') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bx bx-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach($renovacoesPorCliente as $dados)
            <div class="col-lg-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <a href="{{ ($operacaoId ?? null) !== null ? \App\Support\ClienteUrl::show($dados['cliente']->id, (int) $operacaoId) : route('clientes.show', $dados['cliente']->id) }}">
                                {{ $dados['cliente']->nome }}
                            </a>
                            <a href="{{ route('renovacoes.show-cliente', $dados['cliente']->id) }}{{ ($operacaoId ?? null) !== null ? '?operacao_id=' . $operacaoId : '' }}"
                               class="btn btn-sm btn-info float-end">
                                <i class="bx bx-show"></i> Ver Histórico Completo
                            </a>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Empréstimo</th>
                                        <th>Valor</th>
                                        <th>Data Início</th>
                                        <th>Status</th>
                                        <th>Renovações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dados['historico'] as $emp)
                                        <tr>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emp->id) }}">
                                                    #{{ $emp->id }}
                                                </a>
                                                @if($emp->isRenovacao())
                                                    <span class="badge bg-info">Renovação</span>
                                                @endif
                                            </td>
                                            <td>R$ {{ number_format($emp->valor_total, 2, ',', '.') }}</td>
                                            <td>{{ $emp->data_inicio->format('d/m/Y') }}</td>
                                            <td>
                                                @php
                                                    $badgeClass = match($emp->status) {
                                                        'ativo' => 'success',
                                                        'finalizado' => 'info',
                                                        'cancelado' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ ucfirst($emp->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($emp->renovacoes->count() > 0)
                                                    <span class="badge bg-warning">
                                                        {{ $emp->renovacoes->count() }} renovação(ões)
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($emprestimos->isEmpty())
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    Nenhuma renovação encontrada com os filtros aplicados.
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12 d-flex justify-content-end">
            {{ $emprestimos->links() }}
        </div>
    </div>
@endsection
