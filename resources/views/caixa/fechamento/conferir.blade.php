@extends('layouts.master')
@section('title')
    Conferir fechamento de caixa
@endsection
@section('page-title')
    Conferir antes de fechar o caixa
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="alert alert-info mb-3">
                <i class="bx bx-info-circle me-2"></i>
                Revise o período, as movimentações e os totais. O valor registrado no fechamento será o
                <strong>saldo atual</strong> do caixa no momento da confirmação.
            </div>

            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h4 class="card-title mb-0 text-white">
                        <i class="bx bx-search-alt me-2"></i>Conferência — {{ $usuarioAlvo->name }}
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1 small">Usuário</p>
                                <h5 class="mb-0">{{ $usuarioAlvo->name }}</h5>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1 small">Operação</p>
                                <h5 class="mb-0">{{ $operacao->nome }}</h5>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1 small">Período (extrato)</p>
                                <h5 class="mb-0">
                                    {{ \Carbon\Carbon::parse($dataInicioConf)->format('d/m/Y') }} até
                                    {{ \Carbon\Carbon::parse($dataFimConf)->format('d/m/Y') }}
                                </h5>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1 small">Movimentações no período</p>
                                <h5 class="mb-0">{{ $quantidadeMovimentacoes }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3 mb-3 d-flex">
                            <div class="card border-{{ $saldoInicial >= 0 ? 'success' : 'danger' }} w-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Saldo inicial</p>
                                    <h4 class="mb-0 text-{{ $saldoInicial >= 0 ? 'success' : 'danger' }}">
                                        R$ {{ number_format($saldoInicial, 2, ',', '.') }}
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 d-flex">
                            <div class="card border-success w-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Total entradas</p>
                                    <h4 class="mb-0 text-success">R$ {{ number_format($totalEntradas, 2, ',', '.') }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 d-flex">
                            <div class="card border-danger w-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Total saídas</p>
                                    <h4 class="mb-0 text-danger">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 d-flex">
                            <div class="card border-primary w-100">
                                <div class="card-body">
                                    <p class="text-muted mb-1 small">Saldo (extrato)</p>
                                    <h4 class="mb-0 text-primary">R$ {{ number_format($saldoFinal, 2, ',', '.') }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <strong>Valor do fechamento (saldo atual):</strong>
                        <span class="fs-4 text-success">R$ {{ number_format($saldoAtual, 2, ',', '.') }}</span>
                        @if(abs($saldoAtual - $saldoFinal) > 0.009)
                            <p class="small mb-0 mt-2">
                                O saldo por extrato (R$ {{ number_format($saldoFinal, 2, ',', '.') }}) pode diferir do saldo
                                atual por movimentações ou arredondamentos. O que vale para o fechamento é o saldo atual.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bx bx-list-ul me-2"></i>Movimentações no período</h5>
                </div>
                <div class="card-body">
                    @if($movimentacoes->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Origem</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($movimentacoes as $movimentacao)
                                        <tr>
                                            <td>{{ $movimentacao->data_movimentacao->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $movimentacao->isEntrada() ? 'success' : 'danger' }}">
                                                    {{ $movimentacao->isEntrada() ? 'Entrada' : 'Saída' }}
                                                </span>
                                            </td>
                                            <td>
                                                {{ $movimentacao->descricao }}
                                                @if($movimentacao->pagamento && $movimentacao->pagamento->parcela && $movimentacao->pagamento->parcela->emprestimo && $movimentacao->pagamento->parcela->emprestimo->cliente)
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bx bx-user"></i>
                                                        {{ $movimentacao->pagamento->parcela->emprestimo->cliente->nome }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $movimentacao->isManual() ? 'info' : 'secondary' }}">
                                                    {{ ucfirst($movimentacao->origem) }}
                                                </span>
                                            </td>
                                            <td class="text-end {{ $movimentacao->isEntrada() ? 'text-success' : 'text-danger' }}">
                                                <strong>
                                                    {{ $movimentacao->isEntrada() ? '+' : '-' }}
                                                    R$ {{ number_format($movimentacao->valor, 2, ',', '.') }}
                                                </strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="4" class="text-end">Saldo inicial:</th>
                                        <th class="text-end {{ $saldoInicial >= 0 ? 'text-success' : 'text-danger' }}">
                                            R$ {{ number_format($saldoInicial, 2, ',', '.') }}
                                        </th>
                                    </tr>
                                    <tr class="table-success">
                                        <th colspan="4" class="text-end">Total entradas:</th>
                                        <th class="text-end text-success">+ R$ {{ number_format($totalEntradas, 2, ',', '.') }}</th>
                                    </tr>
                                    <tr class="table-danger">
                                        <th colspan="4" class="text-end">Total saídas:</th>
                                        <th class="text-end text-danger">- R$ {{ number_format($totalSaidas, 2, ',', '.') }}</th>
                                    </tr>
                                    <tr class="table-primary">
                                        <th colspan="4" class="text-end">Saldo final (extrato):</th>
                                        <th class="text-end">R$ {{ number_format($saldoFinal, 2, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            Nenhuma movimentação no período; o saldo atual ainda pode ser positivo por saldo anterior.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="bx bx-lock-alt me-2"></i>Confirmar fechamento</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('fechamento-caixa.fechar') }}">
                        @csrf
                        <input type="hidden" name="usuario_id" value="{{ $usuarioAlvo->id }}">
                        <input type="hidden" name="operacao_id" value="{{ $operacao->id }}">
                        <div class="mb-3">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="3">{{ old('observacoes') }}</textarea>
                            @error('observacoes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-between">
                            <a href="{{ route('fechamento-caixa.index', ['operacao_id' => $operacao->id]) }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="bx bx-lock-alt"></i> Confirmar fechamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
