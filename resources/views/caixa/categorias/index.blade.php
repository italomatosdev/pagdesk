@extends('layouts.master')
@section('title')
    Categorias de Movimentação
@endsection
@section('page-title')
    Categorias de Movimentação
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
                        <h4 class="card-title mb-0">Categorias (entrada e despesa)</h4>
                        <a href="{{ route('caixa.categorias.create') }}" class="btn btn-primary">
                            <i class="bx bx-plus"></i> Nova Categoria
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <form method="get" class="mb-3 row g-2 align-items-end">
                        @if(($operacoes ?? collect())->isNotEmpty())
                        <div class="col-auto">
                            <label class="form-label mb-0">Operação</label>
                            <select name="operacao_id" class="form-select form-select-sm" style="width: auto;">
                                <option value="">Todas</option>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}" {{ (isset($operacaoId) && $operacaoId == $op->id) ? 'selected' : '' }}>{{ $op->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-auto">
                            <label class="form-label mb-0">Tipo</label>
                            <select name="tipo" class="form-select form-select-sm" style="width: auto;">
                                <option value="">Todos</option>
                                <option value="entrada" {{ ($tipo ?? '') === 'entrada' ? 'selected' : '' }}>Entrada</option>
                                <option value="despesa" {{ ($tipo ?? '') === 'despesa' ? 'selected' : '' }}>Despesa</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-secondary">Filtrar</button>
                            <a href="{{ route('caixa.categorias.index') }}" class="btn btn-sm btn-light">Limpar</a>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>Operação</th>
                                    <th>Tipo</th>
                                    <th>Ordem</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categorias as $c)
                                    <tr>
                                        <td>{{ $c->id }}</td>
                                        <td>{{ $c->nome }}</td>
                                        <td>{{ $c->operacao ? $c->operacao->nome : '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $c->tipo === 'entrada' ? 'success' : 'warning' }}">
                                                {{ $c->tipo === 'entrada' ? 'Entrada' : 'Despesa' }}
                                            </span>
                                        </td>
                                        <td>{{ $c->ordem }}</td>
                                        <td>
                                            <span class="badge bg-{{ $c->ativo ? 'success' : 'secondary' }}">
                                                {{ $c->ativo ? 'Ativo' : 'Inativo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('caixa.categorias.edit', $c->id) }}" class="btn btn-sm btn-warning" title="Editar">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            <form action="{{ route('caixa.categorias.destroy', $c->id) }}" method="post" class="d-inline" onsubmit="return confirm('Excluir esta categoria?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">Nenhuma categoria cadastrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $categorias->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
@endsection
