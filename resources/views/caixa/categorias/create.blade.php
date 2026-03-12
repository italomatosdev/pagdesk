@extends('layouts.master')
@section('title')
    Nova Categoria
@endsection
@section('page-title')
    Nova Categoria de Movimentação
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Criar Categoria</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('caixa.categorias.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" value="{{ old('nome') }}" maxlength="100" required>
                            @error('nome')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select" required>
                                <option value="entrada" {{ old('tipo') === 'entrada' ? 'selected' : '' }}>Entrada</option>
                                <option value="despesa" {{ old('tipo', 'despesa') === 'despesa' ? 'selected' : '' }}>Despesa</option>
                            </select>
                            @error('tipo')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="{{ old('ordem', 0) }}" min="0">
                            @error('ordem')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="ativo" value="1" class="form-check-input" id="ativo" {{ old('ativo', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('caixa.categorias.index') }}" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
