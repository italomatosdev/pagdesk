@extends('layouts.master')
@section('title')
    Venda #{{ $venda->id }}
@endsection
@section('page-title')
    Venda #{{ $venda->id }}
@endsection
@section('body')
    <body>
@endsection
@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bx bx-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('vendas.index') }}" class="btn btn-secondary"><i class="bx bx-arrow-back me-1"></i> Voltar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Dados da venda</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Data</div>
                        <div class="col-md-8">{{ $venda->data_venda->format('d/m/Y') }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Cliente</div>
                        <div class="col-md-8">
                            <a href="{{ \App\Support\ClienteUrl::show($venda->cliente_id, $venda->operacao_id) }}">{{ $nomeClienteExibicao ?? ($venda->cliente->nome ?? '-') }}</a>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Operação</div>
                        <div class="col-md-8">{{ $venda->operacao->nome ?? '-' }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Vendedor</div>
                        <div class="col-md-8">{{ $venda->user->name ?? '-' }}</div>
                    </div>
                    @if($venda->observacoes)
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Observações</div>
                            <div class="col-md-8">{{ $venda->observacoes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Itens</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Produto / Descrição</th>
                                    <th class="text-end">Qtd</th>
                                    <th class="text-end">Preço à vista</th>
                                    <th class="text-end">Preço crediário</th>
                                    <th class="text-end">Subtotal vista</th>
                                    <th class="text-end">Subtotal crediário</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($venda->itens as $item)
                                    <tr>
                                        <td>{{ $item->produto?->nome ?? $item->descricao ?? '-' }}</td>
                                        <td class="text-end">
                                            @if($item->produto)
                                                {{ $item->produto->formatarQuantidade((float) $item->quantidade) }}
                                            @else
                                                @php
                                                    $qtdItem = (float) $item->quantidade;
                                                    $qtdStr = number_format($qtdItem, 3, ',', '.');
                                                    $qtdStr = rtrim(rtrim($qtdStr, '0'), ',');
                                                @endphp
                                                {{ $qtdStr === '' ? '0' : $qtdStr }}
                                            @endif
                                        </td>
                                        <td class="text-end">R$ {{ number_format($item->preco_unitario_vista, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($item->preco_unitario_crediario, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($item->subtotal_vista, 2, ',', '.') }}</td>
                                        <td class="text-end">R$ {{ number_format($item->subtotal_crediario, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Formas de pagamento</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Forma</th>
                                    <th class="text-end">Valor</th>
                                    <th>Descrição</th>
                                    <th>Comprovante</th>
                                    <th>Parcelas</th>
                                    <th>Empréstimo (crediário)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($venda->formasPagamento as $fp)
                                    <tr>
                                        <td>{{ \App\Modules\Core\Models\FormaPagamentoVenda::formasDisponiveis()[$fp->forma] ?? $fp->forma }}</td>
                                        <td class="text-end">R$ {{ number_format($fp->valor, 2, ',', '.') }}</td>
                                        <td>{{ $fp->descricao ?: '—' }}</td>
                                        <td>
                                            @if($fp->comprovante_path)
                                                <a href="{{ route('vendas.formas.comprovante', ['venda' => $venda->id, 'forma' => $fp->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bx bx-file-blank me-1"></i> Ver comprovante</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $fp->numero_parcelas ? $fp->numero_parcelas . 'x' : '—' }}</td>
                                        <td>
                                            @if($fp->emprestimo_id)
                                                <a href="{{ route('emprestimos.show', $fp->emprestimo_id) }}">Empréstimo #{{ $fp->emprestimo_id }}</a>
                                            @else
                                                —
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
        <div class="col-lg-4">
            {{-- Totais: total_bruto = soma dos itens (preço à vista × qtd); total_final = total_bruto - desconto = valor pago; formas de pagamento devem somar o total_final --}}
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0 text-white">Totais da venda</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <a class="btn btn-sm btn-outline-secondary collapsed" data-bs-toggle="collapse" href="#ajuda-totais-venda" aria-expanded="false">
                            <i class="bx bx-help-circle me-1"></i> Como interpretar os totais?
                        </a>
                    </p>
                    <div class="collapse mb-3" id="ajuda-totais-venda">
                        <div class="small text-muted border rounded p-2 bg-light">
                            <strong>Total da venda</strong> = soma das formas de pagamento (Dinheiro + PIX + Cartão + Crediário + …). É o valor que o cliente paga.<br>
                            <strong>Total bruto</strong> = soma dos itens pelo preço à vista (referência). <strong>Desconto</strong> = valor concedido (se houver). Os itens também mostram preço e subtotal crediário para referência das parcelas.
                        </div>
                    </div>
                    @php
                        $totalDaVenda = $venda->formasPagamento->sum('valor');
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold">Total da venda</span>
                        <strong class="fs-4">R$ {{ number_format($totalDaVenda, 2, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span>soma das formas de pagamento (valor que o cliente paga)</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total bruto (ref.)</span>
                        <strong>R$ {{ number_format($venda->valor_total_bruto, 2, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>soma dos itens (preço à vista × quantidade)</span>
                    </div>
                    @if($venda->valor_desconto > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Desconto (ref.)</span>
                            <strong class="text-danger">- R$ {{ number_format($venda->valor_desconto, 2, ',', '.') }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
