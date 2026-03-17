@extends('layouts.master')
@section('title')
    Solicitações de Pagamento – Juros Abaixo do Devido
@endsection
@section('page-title')
    Solicitações de Pagamento – Juros Abaixo do Devido
@endsection
@section('body')
    <body>
@endsection
@section('content')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h4 class="card-title mb-0">Pagamentos com juros abaixo do devido – aguardando aprovação</h4>
                            <a href="{{ route('liberacoes.index') }}" class="btn btn-secondary btn-sm">
                                <i class="bx bx-arrow-back"></i> Voltar às Liberações
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <p class="text-muted mb-3">
                            O consultor registrou um pagamento com valor de <strong>juros menor</strong> que o devido (multa e juros da operação).
                            Revise os dados e <strong>aprove</strong> para registrar o pagamento na parcela ou <strong>rejeite</strong> para que o consultor possa refazer com o valor correto.
                        </p>

                        <form method="GET" action="{{ route('liberacoes.juros-parcial') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas as Operações</option>
                                        @foreach($operacoes as $op)
                                            <option value="{{ $op->id }}" {{ ($operacaoId ?? '') == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary"><i class="bx bx-search"></i> Filtrar</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Empréstimo</th>
                                        <th>Cliente</th>
                                        <th>Operação</th>
                                        <th>Parcela</th>
                                        <th>Principal</th>
                                        <th>Juros devido</th>
                                        <th>Juros solicitado</th>
                                        <th>Valor pagamento</th>
                                        <th>Consultor</th>
                                        <th>Data solicitação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($solicitacoes as $s)
                                        @php
                                            $parcela = $s->parcela;
                                            $emp = $parcela?->emprestimo;
                                            $principalPagamento = $s->valor - $s->valor_juros_solicitado;
                                        @endphp
                                        <tr>
                                            <td>{{ $s->id }}</td>
                                            <td>@if($emp)<a href="{{ route('emprestimos.show', $emp->id) }}">#{{ $emp->id }}</a>@else-@endif</td>
                                            <td>{{ $emp?->cliente?->nome ?? '-' }}</td>
                                            <td>{{ $emp?->operacao?->nome ?? '-' }}</td>
                                            <td>#{{ $parcela->numero ?? $parcela->id }}</td>
                                            <td>R$ {{ number_format($principalPagamento, 2, ',', '.') }}</td>
                                            <td class="text-danger">R$ {{ number_format($s->valor_juros_devido, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($s->valor_juros_solicitado, 2, ',', '.') }}</td>
                                            <td class="fw-semibold">R$ {{ number_format($s->valor, 2, ',', '.') }}</td>
                                            <td>{{ $s->consultor?->name ?? '-' }}</td>
                                            <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <form action="{{ route('liberacoes.juros-parcial.aprovar', $s->id) }}" method="post" class="d-inline" onsubmit="return confirm('Aprovar esta solicitação? O pagamento será registrado na parcela com o valor de juros informado.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Aprovar">
                                                            <i class="bx bx-check"></i> Aprovar
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('liberacoes.juros-parcial.rejeitar', $s->id) }}" method="post" class="d-inline" onsubmit="return confirm('Rejeitar esta solicitação? O consultor poderá registrar um novo pagamento com o valor de juros devido.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Rejeitar">
                                                            <i class="bx bx-x"></i> Rejeitar
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center">Nenhuma solicitação de pagamento com juros parcial aguardando aprovação.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $solicitacoes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection
