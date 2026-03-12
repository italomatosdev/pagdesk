@extends('layouts.master')
@section('title')
    Sandbox - Ambiente de Testes
@endsection
@section('page-title')
    Sandbox - Ambiente de Testes
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="alert alert-info">
                <i class="bx bx-info-circle"></i>
                Use esta tela para gerar <strong>dados fictícios</strong> e testar relatórios (ex.: parcelas atrasadas), cobranças e fluxos. Tudo criado aqui é marcado como sandbox e pode ser removido na seção "Limpar sandbox".
            </div>

            <div class="row">
                {{-- 1. Clientes fictícios --}}
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bx bx-user-plus"></i> Clientes fictícios</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Cria N clientes com nome e documento fake (faixa 90xxx) para usar em cenários.</p>
                            <form action="{{ route('super-admin.sandbox.store-clientes') }}" method="POST" class="mb-0">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Empresa <span class="text-danger">*</span></label>
                                    <select name="empresa_id" class="form-select" required>
                                        <option value="">Selecione a empresa...</option>
                                        @foreach($empresas as $e)
                                            <option value="{{ $e->id }}">{{ $e->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vincular à operação (opcional)</label>
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Nenhuma — só criar clientes</option>
                                        @foreach($operacoes as $op)
                                            <option value="{{ $op->id }}">{{ $op->nome }} ({{ $op->empresa->nome ?? '-' }})</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Se escolher, os clientes já ficam vinculados a essa operação.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                                    <input type="number" name="quantidade" class="form-control" value="5" min="1" max="50" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Prefixo no nome (opcional)</label>
                                    <input type="text" name="prefixo" class="form-control" value="[SANDBOX]" placeholder="[SANDBOX]">
                                </div>
                                <button type="submit" class="btn btn-primary">Gerar clientes</button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- 2. Cenário: parcelas atrasadas --}}
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bx bx-time-five text-danger"></i> Cenário: parcelas atrasadas</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Cria empréstimo(s) ativo(s) com parcelas vencidas (para testar relatório de parcelas atrasadas). Use clientes sandbox da empresa da operação.</p>
                            <form action="{{ route('super-admin.sandbox.store-cenario') }}" method="POST" class="mb-0">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Operação</label>
                                    <select name="operacao_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        @foreach($operacoes as $op)
                                            <option value="{{ $op->id }}">{{ $op->nome }} ({{ $op->empresa->nome ?? '-' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Qtd. empréstimos</label>
                                        <input type="number" name="quantidade_emprestimos" class="form-control" value="1" min="1" max="20">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Valor por parcela (R$)</label>
                                        <input type="number" name="valor_parcela" class="form-control" value="100" min="1" step="0.01" required>
                                    </div>
                                </div>
                                <div class="row g-2 mt-2">
                                    <div class="col-6">
                                        <label class="form-label">Nº parcelas</label>
                                        <input type="number" name="numero_parcelas" class="form-control" value="3" min="1" max="60" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Dias de atraso</label>
                                        <input type="number" name="dias_atraso" class="form-control" value="15" min="1" max="365" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning mt-3">Criar cenário</button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- 2b. Cenário: empréstimo diária (30% juros) --}}
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bx bx-calendar-check"></i> Cenário: empréstimo diária (30% juros)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Cria um empréstimo ativo com <strong>frequência diária</strong> e <strong>juros 30%</strong> para testar quitação em lote e regras de diária. Use um cliente sandbox da empresa da operação.</p>
                            <form action="{{ route('super-admin.sandbox.store-cenario-diaria') }}" method="POST" class="mb-0">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Operação <span class="text-danger">*</span></label>
                                    <select name="operacao_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        @foreach($operacoes as $op)
                                            <option value="{{ $op->id }}">{{ $op->nome }} ({{ $op->empresa->nome ?? '-' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Valor total (R$)</label>
                                        <input type="number" name="valor_total" class="form-control" value="1000" min="1" step="0.01">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Nº parcelas (dias)</label>
                                        <input type="number" name="numero_parcelas" class="form-control" value="7" min="2" max="60">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Dias de atraso da 1ª parcela</label>
                                    <input type="number" name="dias_atraso_primeira" class="form-control" value="5" min="0" max="365" title="A primeira parcela vencerá há X dias (parcelas ficam atrasadas para testar quitação).">
                                    <small class="text-muted">Define há quantos dias a primeira parcela venceu (parcelas atrasadas para teste).</small>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Criar empréstimo diária</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Limpar sandbox --}}
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0"><i class="bx bx-trash"></i> Limpar sandbox</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Remove todos os empréstimos sandbox (e suas parcelas/liberaciones). Opcionalmente remove também os clientes fictícios.</p>
                    <form action="{{ route('super-admin.sandbox.destroy') }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza? Todos os dados sandbox serão removidos.');">
                        @csrf
                        @method('DELETE')
                        <label class="me-2">
                            <input type="checkbox" name="incluir_clientes" value="1"> Remover também clientes fictícios
                        </label>
                        <button type="submit" class="btn btn-danger">Limpar sandbox</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
