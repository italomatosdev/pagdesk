@extends('layouts.master')
@section('title')
    Movimentação #{{ $movimentacao->id }}
@endsection
@section('page-title')
    Detalhes da Movimentação
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h4 class="card-title mb-0">Movimentação #{{ $movimentacao->id }}</h4>
                        <a href="{{ route('caixa.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bx bx-arrow-back"></i> Voltar ao Caixa
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Data:</strong><br>
                            {{ $movimentacao->data_movimentacao->format('d/m/Y') }}
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Tipo:</strong><br>
                            <span class="badge bg-{{ $movimentacao->isEntrada() ? 'success' : 'danger' }}">
                                {{ ucfirst($movimentacao->tipo) }}
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Valor:</strong><br>
                            <span class="{{ $movimentacao->isEntrada() ? 'text-success' : 'text-danger' }}">
                                {{ $movimentacao->isEntrada() ? '+' : '-' }}
                                R$ {{ number_format($movimentacao->valor, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Operação:</strong><br>
                            {{ $movimentacao->operacao->nome ?? '-' }}
                        </div>
                        @if($movimentacao->categoria)
                            <div class="col-md-6 mb-3">
                                <strong>Categoria:</strong><br>
                                <span class="badge bg-{{ $movimentacao->categoria->tipo === 'entrada' ? 'success' : 'warning' }}">
                                    {{ $movimentacao->categoria->nome }}
                                </span>
                            </div>
                        @endif
                        <div class="col-12 mb-3">
                            <strong>Descrição:</strong><br>
                            {{ $movimentacao->descricao ?? '-' }}
                        </div>
                        @if($movimentacao->observacoes)
                            <div class="col-12 mb-3">
                                <strong>Observações:</strong><br>
                                {{ $movimentacao->observacoes }}
                            </div>
                        @endif
                        <div class="col-md-6 mb-3">
                            <strong>Responsável:</strong><br>
                            @if($movimentacao->consultor_id)
                                <a href="{{ route('usuarios.show', $movimentacao->consultor_id) }}">
                                    {{ $movimentacao->consultor->name ?? 'N/A' }}
                                </a>
                            @else
                                <span class="badge bg-secondary"><i class="bx bx-building"></i> Caixa da Operação</span>
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Origem:</strong><br>
                            <span class="badge bg-{{ $movimentacao->isManual() ? 'warning' : 'info' }}">
                                {{ $movimentacao->isManual() ? 'Manual' : 'Automática' }}
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Tipo de referência:</strong><br>
                            @if($movimentacao->referencia_tipo)
                                @switch($movimentacao->referencia_tipo)
                                    @case('settlement')
                                        <i class="bx bx-receipt"></i> Prestação de Contas
                                        @break
                                    @case('liberacao_emprestimo')
                                        <i class="bx bx-money"></i> Liberação de Empréstimo
                                        @break
                                    @case('pagamento_cliente')
                                        <i class="bx bx-user"></i> Pagamento Cliente
                                        @break
                                    @case('venda')
                                    @case('App\Modules\Core\Models\Venda')
                                        <i class="bx bx-cart"></i> Venda
                                        @break
                                    @default
                                        {{ ucfirst(str_replace('_', ' ', $movimentacao->referencia_tipo)) }}
                                @endswitch
                            @else
                                <span class="text-muted">Manual</span>
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Comprovante:</strong><br>
                            @if($movimentacao->comprovante_path)
                                <a href="{{ asset('storage/' . $movimentacao->comprovante_path) }}" target="_blank" class="btn btn-sm btn-info">
                                    <i class="bx bx-file"></i> Ver comprovante
                                </a>
                            @elseif(!empty($comprovanteReferenciaUrl))
                                <a href="{{ $comprovanteReferenciaUrl }}" target="_blank" class="btn btn-sm btn-outline-info" title="{{ $comprovanteReferenciaLabel ?? 'Comprovante da referência' }}">
                                    <i class="bx bx-file"></i> {{ $comprovanteReferenciaLabel ?? 'Ver comprovante' }}
                                </a>
                            @else
                                <span class="text-muted">Não anexado</span>
                            @endif
                        </div>
                    </div>

                    @php
                        $emprestimo = $movimentacao->pagamento && $movimentacao->pagamento->parcela && $movimentacao->pagamento->parcela->emprestimo
                            ? $movimentacao->pagamento->parcela->emprestimo
                            : null;
                    @endphp
                    @if($emprestimo)
                        <hr>
                        <div class="mb-0">
                            <strong><i class="bx bx-link-alt"></i> Vinculado ao empréstimo</strong>
                            <p class="mb-2 text-muted small">
                                Esta movimentação refere-se a um pagamento de parcela do empréstimo abaixo.
                            </p>
                            <a href="{{ route('emprestimos.show', $emprestimo->id) }}" class="btn btn-primary btn-sm">
                                <i class="bx bx-show"></i> Ver Empréstimo #{{ $emprestimo->id }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
