@extends('layouts.master')
@section('title')
    Produtos
@endsection
@section('page-title')
    Produtos
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

    @if(isset($produtosSemOperacaoCount) && $produtosSemOperacaoCount > 0 && ($podeGerenciarProdutos ?? true))
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center">
            <i class="bx bx-error-circle font-size-24 me-2"></i>
            <div class="flex-grow-1">
                <strong>Produtos sem operação:</strong>
                @if(!empty($semOperacao))
                    Abaixo estão listados apenas produtos sem operação. Edite cada um e selecione uma operação para que possam ser usados nas vendas.
                    <a href="{{ route('produtos.index') }}" class="alert-link ms-1">Ver todos os produtos</a>
                @else
                    {{ $produtosSemOperacaoCount }} {{ $produtosSemOperacaoCount === 1 ? 'produto não está' : 'produtos não estão' }} vinculado(s) a uma operação e {{ $produtosSemOperacaoCount === 1 ? 'não aparecerá' : 'não aparecerão' }} nas vendas até que uma operação seja atribuída.
                    <a href="{{ route('produtos.index', ['sem_operacao' => 1]) }}" class="alert-link ms-1">Ver produtos sem operação</a>
                @endif
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($podeGerenciarProdutos ?? true)
    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('produtos.create') }}" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Novo Produto
            </a>
        </div>
    </div>
    @endif

    <!-- Totalizadores (respeitam os filtros da listagem) -->
    <div class="row mb-3">
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <i class="bx bx-package font-size-24 text-primary"></i>
                    <h5 class="mt-1 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h5>
                    <small class="text-muted">Total de produtos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <div class="card h-100 border-success">
                <div class="card-body text-center py-3">
                    <i class="bx bx-check-circle font-size-24 text-success"></i>
                    <h5 class="mt-1 mb-0">{{ number_format($stats['ativos'], 0, ',', '.') }}</h5>
                    <small class="text-muted">Ativos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <div class="card h-100 border-secondary">
                <div class="card-body text-center py-3">
                    <i class="bx bx-pause-circle font-size-24 text-secondary"></i>
                    <h5 class="mt-1 mb-0">{{ number_format($stats['inativos'], 0, ',', '.') }}</h5>
                    <small class="text-muted">Inativos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <div class="card h-100 border-warning">
                <div class="card-body text-center py-3">
                    <i class="bx bx-error-circle font-size-24 text-warning"></i>
                    <h5 class="mt-1 mb-0">{{ number_format($stats['sem_estoque'], 0, ',', '.') }}</h5>
                    <small class="text-muted">Sem estoque</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <div class="card h-100 border-info">
                <div class="card-body text-center py-3">
                    <i class="bx bx-cube font-size-24 text-info"></i>
                    <h5 class="mt-1 mb-0">{{ number_format((float)$stats['total_unidades'], 3, ',', '.') }}</h5>
                    <small class="text-muted">Unidades em estoque</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-2">
            <div class="card h-100 border-primary">
                <div class="card-body text-center py-3">
                    <i class="bx bx-money font-size-24 text-primary"></i>
                    <h5 class="mt-1 mb-0">R$ {{ number_format((float)$stats['valor_estoque'], 2, ',', '.') }}</h5>
                    <small class="text-muted">Valor do estoque</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bx bx-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('produtos.index') }}" class="row g-3">
                @if(!empty($semOperacao))
                    <input type="hidden" name="sem_operacao" value="1">
                @endif
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
                    <label class="form-label">Buscar</label>
                    <input type="text" name="search" class="form-control" placeholder="Nome ou código" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="ativo" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>Ativos</option>
                        <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>Inativos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estoque</label>
                    <select name="estoque" class="form-select">
                        <option value="">Todos</option>
                        <option value="com" {{ request('estoque') === 'com' ? 'selected' : '' }}>Com estoque</option>
                        <option value="sem" {{ request('estoque') === 'sem' ? 'selected' : '' }}>Sem estoque</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-search"></i> Filtrar</button>
                    <a href="{{ route('produtos.index') }}" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bx bx-package"></i> Lista de Produtos</h5>
        </div>
        <div class="card-body">
            @if($produtos->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Código</th>
                                <th>Operação</th>
                                <th class="text-end">Preço venda</th>
                                <th class="text-end">Estoque</th>
                                <th>Status estoque</th>
                                <th>Unidade</th>
                                <th>Status</th>
                                <th width="100">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($produtos as $p)
                                @php
                                    $qtdEstoque = (float) $p->estoque;
                                    $statusEstoque = $qtdEstoque <= 0 ? 'sem' : ($qtdEstoque < 5 ? 'baixo' : 'ok');
                                @endphp
                                <tr class="{{ $statusEstoque === 'sem' ? 'table-warning' : '' }}">
                                    <td>{{ $p->id }}</td>
                                    <td>{{ $p->nome }}</td>
                                    <td>{{ $p->codigo ?? '—' }}</td>
                                    <td>{{ $p->operacao->nome ?? '—' }}</td>
                                    <td class="text-end">R$ {{ number_format($p->preco_venda, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($qtdEstoque, 3, ',', '.') }}</td>
                                    <td>
                                        @if($statusEstoque === 'sem')
                                            <span class="badge bg-warning text-dark"><i class="bx bx-error"></i> Sem estoque</span>
                                        @elseif($statusEstoque === 'baixo')
                                            <span class="badge bg-info"><i class="bx bx-down-arrow-alt"></i> Baixo</span>
                                        @else
                                            <span class="badge bg-success"><i class="bx bx-check"></i> Em estoque</span>
                                        @endif
                                    </td>
                                    <td>{{ $p->unidade ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $p->ativo ? 'success' : 'secondary' }}">
                                            {{ $p->ativo ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('produtos.show', $p->id) }}" class="btn btn-sm btn-info" title="Ver"><i class="bx bx-show"></i></a>
                                        @if($podeGerenciarProdutos ?? true)
                                            <a href="{{ route('produtos.edit', $p->id) }}" class="btn btn-sm btn-warning" title="Editar"><i class="bx bx-edit"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $produtos->links() }}</div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bx bx-package font-size-48"></i>
                    <p class="mt-2">Nenhum produto encontrado com os filtros aplicados.</p>
                    <a href="{{ route('produtos.index') }}" class="btn btn-secondary me-2">Limpar filtros</a>
                    @if($podeGerenciarProdutos ?? true)
                        <a href="{{ route('produtos.create') }}" class="btn btn-primary">Cadastrar produto</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
