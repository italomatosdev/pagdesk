@extends('layouts.master')
@section('title')
    Histórico de Renovações - {{ $cliente->nome }}
@endsection
@section('page-title')
    Histórico de Renovações - {{ $cliente->nome }}
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <a href="{{ ($operacaoId ?? null) !== null ? \App\Support\ClienteUrl::show($cliente->id, (int) $operacaoId) : route('clientes.show', $cliente->id) }}">
                                    {{ $cliente->nome }}
                                </a>
                            </h5>
                            <p class="text-muted mb-0">
                                {{ $cliente->isPessoaFisica() ? 'CPF' : 'CNPJ' }}: {{ $cliente->documento_formatado ?? $cliente->documento }}
                            </p>
                        </div>
                        <a href="{{ route('renovacoes.index') }}{{ ($operacaoId ?? null) !== null ? '?operacao_id=' . $operacaoId : '' }}" class="btn btn-secondary">
                            <i class="bx bx-arrow-back"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @forelse($cadeiasRenovacao as $cadeia)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Cadeia de Renovações
                            <span class="badge bg-primary ms-2">
                                {{ $cadeia['total_renovacoes'] }} renovação(ões)
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Empréstimo</th>
                                        <th>Valor Principal</th>
                                        <th>Juros</th>
                                        <th>Valor Total</th>
                                        <th>Data Início</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cadeia['cadeia'] as $index => $emp)
                                        <tr class="{{ $index === 0 ? 'table-primary' : '' }}">
                                            <td>
                                                @if($index === 0)
                                                    <span class="badge bg-primary">Original</span>
                                                @else
                                                    <span class="badge bg-info">Renovação #{{ $index }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emp->id) }}">
                                                    #{{ $emp->id }}
                                                </a>
                                            </td>
                                            <td>R$ {{ number_format($emp->valor_total, 2, ',', '.') }}</td>
                                            <td>
                                                @if($emp->taxa_juros > 0)
                                                    {{ number_format($emp->taxa_juros, 2, ',', '.') }}%
                                                    <br>
                                                    <small class="text-muted">
                                                        R$ {{ number_format($emp->calcularValorJuros(), 2, ',', '.') }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>R$ {{ number_format($emp->calcularValorTotalComJuros(), 2, ',', '.') }}</strong>
                                            </td>
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
                                                <a href="{{ route('emprestimos.show', $emp->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2"><strong>Total:</strong></td>
                                        <td><strong>R$ {{ number_format($cadeia['cadeia']->sum('valor_total'), 2, ',', '.') }}</strong></td>
                                        <td colspan="2">
                                            <strong>Total de Juros Pagos:</strong>
                                            R$ {{ number_format($cadeia['cadeia']->sum(function($emp) { return $emp->calcularValorJuros(); }), 2, ',', '.') }}
                                        </td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    Este cliente não possui renovações de empréstimos.
                </div>
            </div>
        </div>
    @endforelse
@endsection
