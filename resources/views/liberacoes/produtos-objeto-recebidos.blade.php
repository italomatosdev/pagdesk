@extends('layouts.master')
@section('title')
    Produtos/Objetos recebidos
@endsection
@section('page-title')
    Produtos/Objetos recebidos
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
                        <h4 class="card-title mb-0">Produtos/Objetos recebidos em pagamento</h4>
                        <a href="{{ route('liberacoes.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bx bx-arrow-back"></i> Voltar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Itens recebidos em pagamentos do tipo produto/objeto (um pagamento pode ter vários itens).
                    </p>

                    <form method="GET" action="{{ route('produtos-recebidos.index') }}" class="mb-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" class="form-select">
                                    <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status do pagamento</label>
                                <select name="status" class="form-select">
                                    <option value="todos" {{ ($status ?? '') === 'todos' ? 'selected' : '' }}>Todos</option>
                                    <option value="aceito" {{ ($status ?? '') === 'aceito' ? 'selected' : '' }}>Aceito</option>
                                    <option value="pendente" {{ ($status ?? '') === 'pendente' ? 'selected' : '' }}>Pendente aceite</option>
                                    <option value="rejeitado" {{ ($status ?? '') === 'rejeitado' ? 'selected' : '' }}>Rejeitado</option>
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
                                    <th>Item</th>
                                    <th>Qtd</th>
                                    <th>Valor est.</th>
                                    <th>Empréstimo</th>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($itens as $item)
                                    @php
                                        $p = $item->pagamento;
                                        $emp = $p->parcela->emprestimo ?? null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $item->nome }}</strong>
                                            @if($item->descricao)
                                                <br><small class="text-muted">{{ Str::limit($item->descricao, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $item->quantidade }}</td>
                                        <td>
                                            @if($item->valor_estimado !== null)
                                                R$ {{ number_format($item->valor_estimado, 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($emp)
                                                <a href="{{ route('emprestimos.show', $emp->id) }}">#{{ $emp->id }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emp, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                        <td>{{ $emp->operacao->nome ?? '—' }}</td>
                                        <td>
                                            @if($p->aceite_gestor_id)
                                                <span class="badge bg-success">Aceito</span>
                                            @elseif($p->rejeitado_por_id)
                                                <span class="badge bg-danger">Rejeitado</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Pendente</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">Nenhum item de produto/objeto encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-3">
                        {{ $itens->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
