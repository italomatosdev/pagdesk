@extends('layouts.master')
@section('title')
    Solicitações – Renovação com abate (valor inferior ao principal)
@endsection
@section('page-title')
    Solicitações – Renovação com abate (valor inferior ao principal)
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
                            <h4 class="card-title mb-0">Renovação com abate – valor inferior ao principal – aguardando aprovação</h4>
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
                            O consultor solicitou <strong>renovar com abate</strong> pagando um valor <strong>inferior ao principal</strong>. Ao aprovar, o pagamento será registrado e um novo empréstimo será criado com o saldo devedor restante.
                        </p>

                        <form method="GET" action="{{ route('liberacoes.renovacao-abate') }}" class="mb-3">
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
                                        <th>Juros</th>
                                        <th>Valor da parcela</th>
                                        <th>Valor a pagar</th>
                                        <th>Saldo restante</th>
                                        <th>Consultor</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($solicitacoes as $s)
                                        @php
                                            $emp = $s->parcela->emprestimo;
                                            $parcela = $s->parcela;
                                            $jurosParcela = $s->valor_parcela_total - $s->valor_principal;
                                            $saldoRestante = $s->valor_parcela_total - $s->valor;
                                        @endphp
                                        <tr>
                                            <td>{{ $s->id }}</td>
                                            <td><a href="{{ route('emprestimos.show', $emp->id) }}">#{{ $emp->id }}</a></td>
                                            <td>{{ $emp->cliente->nome ?? '-' }}</td>
                                            <td>{{ $emp->operacao->nome ?? '-' }}</td>
                                            <td>#{{ $parcela->numero ?? $parcela->id }}</td>
                                            <td>R$ {{ number_format($s->valor_principal, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($jurosParcela, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($s->valor_parcela_total, 2, ',', '.') }}</td>
                                            <td class="fw-semibold">R$ {{ number_format($s->valor, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($saldoRestante, 2, ',', '.') }}</td>
                                            <td>{{ $s->consultor->name ?? '-' }}</td>
                                            <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <form action="{{ route('liberacoes.renovacao-abate.aprovar', $s->id) }}" method="post" class="d-inline" onsubmit="return confirm('Aprovar? A renovação será realizada e um novo empréstimo será criado com o saldo restante.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Aprovar"><i class="bx bx-check"></i> Aprovar</button>
                                                    </form>
                                                    <form action="{{ route('liberacoes.renovacao-abate.rejeitar', $s->id) }}" method="post" class="d-inline" onsubmit="return confirm('Rejeitar? O consultor poderá enviar uma nova solicitação.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Rejeitar"><i class="bx bx-x"></i> Rejeitar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center">Nenhuma solicitação aguardando aprovação.</td>
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
