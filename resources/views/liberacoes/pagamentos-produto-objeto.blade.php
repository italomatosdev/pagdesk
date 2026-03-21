@extends('layouts.master')
@section('title')
    Pagamentos em Produto/Objeto
@endsection
@section('page-title')
    Pagamentos em Produto/Objeto - Aguardando Aceite
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
                            <h4 class="card-title mb-0">Pagamentos em produto/objeto (não geram caixa)</h4>
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
                            Estes pagamentos foram registrados como <strong>produto/objeto</strong>. Veja os dados e imagens abaixo. 
                            Ao <strong>aceitar</strong>, a parcela é creditada (sem movimentação de caixa). Ao <strong>rejeitar</strong>, o pagamento permanece pendente.
                        </p>

                        <form method="GET" action="{{ route('liberacoes.pagamentos-produto-objeto') }}" class="mb-3">
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
                                        <th>Valor parcela</th>
                                        <th>Produto/Objeto</th>
                                        <th>Consultor</th>
                                        <th>Data reg.</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pagamentos as $p)
                                        @php
                                            $emp = $p->parcela->emprestimo;
                                            $parcela = $p->parcela;
                                            $principalParcela = $parcela->valor_amortizacao ?? ($parcela->valor - ($parcela->valor_juros ?? 0));
                                            $jurosParcela = $parcela->valor_juros ?? 0;
                                        @endphp
                                        <tr>
                                            <td>{{ $p->id }}</td>
                                            <td><a href="{{ route('emprestimos.show', $emp->id) }}">#{{ $emp->id }}</a></td>
                                            <td>{{ \App\Support\ClienteNomeExibicao::fromParcelaMap($p->parcela, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                            <td>{{ $emp->operacao->nome ?? '-' }}</td>
                                            <td>#{{ $parcela->numero ?? $p->parcela_id }}</td>
                                            <td>R$ {{ number_format($principalParcela, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($jurosParcela, 2, ',', '.') }}</td>
                                            <td class="fw-semibold">R$ {{ number_format($p->valor, 2, ',', '.') }}</td>
                                            <td>
                                                @if($p->hasProdutoObjetoItens() || $p->produto_nome || $p->produto_imagens)
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProduto{{ $p->id }}" title="Ver dados do produto/objeto">
                                                        <i class="bx bx-show"></i> Ver
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $p->consultor->name ?? '-' }}</td>
                                            <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <form action="{{ route('liberacoes.aceitar-pagamento-produto-objeto', $p->id) }}" method="post" class="d-inline" onsubmit="return confirm('Aceitar este pagamento em produto/objeto? A parcela será creditada (sem movimentação de caixa).');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Aceitar">
                                                            <i class="bx bx-check"></i> Aceitar
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('liberacoes.rejeitar-pagamento-produto-objeto', $p->id) }}" method="post" class="d-inline" onsubmit="return confirm('Rejeitar este pagamento? A parcela permanecerá pendente.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Rejeitar">
                                                            <i class="bx bx-x"></i> Rejeitar
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- Modal com dados do produto/objeto --}}
                                        @if($p->hasProdutoObjetoItens() || $p->produto_nome || $p->produto_descricao || $p->produto_valor !== null || !empty($p->produto_imagens))
                                        <div class="modal fade" id="modalProduto{{ $p->id }}" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Produto/Objeto - Pagamento #{{ $p->id }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        @if($p->hasProdutoObjetoItens())
                                                            @foreach($p->produtoObjetoItens as $item)
                                                                <div class="border rounded p-3 mb-3 bg-light">
                                                                    <strong>{{ $item->nome }}</strong>
                                                                    @if($item->quantidade > 1) <span class="badge bg-secondary">{{ $item->quantidade }} un.</span> @endif
                                                                    @if($item->valor_estimado !== null) — R$ {{ number_format($item->valor_estimado, 2, ',', '.') }} @endif
                                                                    @if($item->descricao)<p class="mb-2 small text-muted">{{ $item->descricao }}</p>@endif
                                                                    @if(!empty($item->imagens))
                                                                        <div class="d-flex flex-wrap gap-2">
                                                                            @foreach($item->imagens_urls as $url)
                                                                                <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="" class="img-thumbnail" style="max-height: 120px; max-width: 160px; object-fit: contain;"></a>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        @else
                                                            <dl class="row mb-0">
                                                                @if($p->produto_nome)
                                                                    <dt class="col-sm-3">Nome</dt>
                                                                    <dd class="col-sm-9">{{ $p->produto_nome }}</dd>
                                                                @endif
                                                                @if($p->produto_descricao)
                                                                    <dt class="col-sm-3">Descrição</dt>
                                                                    <dd class="col-sm-9">{{ $p->produto_descricao }}</dd>
                                                                @endif
                                                                @if($p->produto_valor !== null)
                                                                    <dt class="col-sm-3">Valor do produto</dt>
                                                                    <dd class="col-sm-9">R$ {{ number_format($p->produto_valor, 2, ',', '.') }}</dd>
                                                                @endif
                                                            </dl>
                                                            @if(!empty($p->produto_imagens))
                                                                <p class="mb-2 mt-3"><strong>Imagens</strong></p>
                                                                <div class="d-flex flex-wrap gap-2">
                                                                    @foreach($p->produto_imagens_urls as $url)
                                                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="d-inline-block">
                                                                            <img src="{{ $url }}" alt="Produto" class="img-thumbnail" style="max-height: 120px; max-width: 160px; object-fit: contain;">
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center">Nenhum pagamento em produto/objeto aguardando aceite.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $pagamentos->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection
