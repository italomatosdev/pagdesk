@extends('layouts.master')
@section('title')
    Novo Produto
@endsection
@section('page-title')
    Novo Produto
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Cadastrar Produto</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('produtos.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Operação <span class="text-danger">*</span></label>
                            <select name="operacao_id" class="form-select" required>
                                <option value="">Selecione a operação</option>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}" {{ old('operacao_id') == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                @endforeach
                            </select>
                            @error('operacao_id')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" value="{{ old('nome') }}" required maxlength="255">
                            @error('nome')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" value="{{ old('codigo') }}" maxlength="50" placeholder="Ex: PROD-001">
                            @error('codigo')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço de venda (R$) <span class="text-danger">*</span></label>
                            <input type="text" id="preco_venda" name="preco_venda" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="{{ old('preco_venda', '0') }}" required>
                            <small class="text-muted">Usado como sugestão à vista e crediário na venda.</small>
                            @error('preco_venda')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estoque <span class="text-danger">*</span></label>
                            <input type="number" name="estoque" class="form-control" step="0.001" min="0" value="{{ old('estoque', '0') }}" required>
                            <small class="text-muted">Quantidade disponível para venda. Produto só aparece na venda se estoque &gt; 0.</small>
                            @error('estoque')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unidade</label>
                            <input type="text" name="unidade" class="form-control" value="{{ old('unidade') }}" maxlength="20" placeholder="Ex: un, kg, m">
                            @error('unidade')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" {{ old('ativo', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="ativo">Ativo (disponível para venda)</label>
                            </div>
                        </div>
                        <p class="small text-muted mb-3">Após cadastrar, você poderá adicionar fotos e anexos na edição do produto.</p>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Cadastrar</button>
                            <a href="{{ route('produtos.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
@endsection