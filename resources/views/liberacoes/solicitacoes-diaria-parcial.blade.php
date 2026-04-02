@extends('layouts.master')
@section('title')
    Solicitações – Pagamento parcial (diária)
@endsection
@section('page-title')
    Solicitações – Pagamento parcial (diária)
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
                            <h4 class="card-title mb-0">Diária – pagamento parcial – aguardando aprovação</h4>
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
                            O consultor registrou um valor <strong>inferior ao devido</strong> na parcela (diária com várias parcelas). A parcela permaneceu <strong>em atraso</strong> até esta aprovação.
                            Ao <strong>aprovar</strong>, a parcela passa a <strong>paga (parcial)</strong> e o faltante é <strong>acrescido à última parcela</strong> do contrato. Ao <strong>rejeitar</strong>, a entrada em caixa é estornada.
                        </p>

                        <form method="GET" action="{{ route('liberacoes.diaria-parcial') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="operacao_id" class="form-select">
                                        <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas as Operações</option>
                                        @foreach($operacoes as $op)
                                            <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
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
                                        <th>Recebido</th>
                                        <th>Devido</th>
                                        <th>Faltante → última</th>
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
                                        @endphp
                                        <tr>
                                            <td>{{ $s->id }}</td>
                                            <td><a href="{{ route('emprestimos.show', $emp->id) }}">#{{ $emp->id }}</a></td>
                                            <td>{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($s->parcela, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                            <td>{{ $emp->operacao->nome ?? '-' }}</td>
                                            <td>#{{ $parcela->numero ?? $parcela->id }}</td>
                                            <td class="fw-semibold">R$ {{ number_format($s->valor_recebido, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($s->valor_esperado, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($s->faltante, 2, ',', '.') }}</td>
                                            <td>{{ $s->consultor->name ?? '-' }}</td>
                                            <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <form action="{{ route('liberacoes.diaria-parcial.aprovar', $s->id) }}" method="post" class="d-inline" onsubmit="return confirm('Aprovar? A parcela ficará paga (parcial) e o faltante será acrescido à última parcela.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Aprovar"><i class="bx bx-check"></i> Aprovar</button>
                                                    </form>
                                                    <form action="{{ route('liberacoes.diaria-parcial.rejeitar', $s->id) }}" method="post" class="d-inline" onsubmit="return confirm('Rejeitar? O valor será estornado no caixa.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Rejeitar"><i class="bx bx-x"></i> Rejeitar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center">Nenhuma solicitação aguardando aprovação.</td>
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
