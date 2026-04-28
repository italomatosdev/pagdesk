@extends('layouts.master')
@section('title')
    Editar Produto
@endsection
@section('page-title')
    Editar Produto
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
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bx bx-error-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Corrija os erros abaixo:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Editar Produto #{{ $produto->id }}</h4>
                </div>
                <div class="card-body">
                    <form id="form-produto-edit" action="{{ route('produtos.update', $produto->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Operação <span class="text-danger">*</span></label>
                            @php
                                $operacaoId = old('operacao_id', $produto->operacao_id);
                            @endphp
                            <select name="operacao_id" class="form-select" required>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}" {{ $operacaoId == $op->id || ($operacaoId === null && $loop->first) ? 'selected' : '' }}>{{ $op->nome }}</option>
                                @endforeach
                            </select>
                            @error('operacao_id')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" value="{{ old('nome', $produto->nome) }}" required maxlength="255">
                            @error('nome')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" value="{{ old('codigo', $produto->codigo) }}" maxlength="50" placeholder="Ex: PROD-001">
                            @error('codigo')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço de venda (R$) <span class="text-danger">*</span></label>
                            <input type="text" id="preco_venda" name="preco_venda" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="{{ old('preco_venda', $produto->preco_venda) }}" required>
                            @error('preco_venda')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        @if($podeVerCustoProdutos ?? false)
                            @if(!$produto->temCustoVigenteDefinido())
                                <div class="alert alert-warning">
                                    <i class="bx bx-error-circle me-1"></i> Este produto <strong>não tem preço de custo</strong>. Não será possível registrar vendas até informar o custo abaixo.
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">Preço de custo vigente (R$)</label>
                                <p class="mb-1">{{ $produto->temCustoVigenteDefinido() ? 'R$ '.number_format((float) $produto->custo_unitario_vigente, 2, ',', '.') : '—' }}</p>
                                <label class="form-label">{{ $produto->temCustoVigenteDefinido() ? 'Novo preço de custo (opcional)' : 'Preço de custo' }} @if(!$produto->temCustoVigenteDefinido())<span class="text-danger">*</span>@endif</label>
                                <input type="text" id="novo_custo_unitario" name="novo_custo_unitario" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="{{ old('novo_custo_unitario') }}">
                                <small class="text-muted">Ao informar um valor, é criada uma nova entrada no histórico (vendas futuras usam esse custo).</small>
                                @error('novo_custo_unitario')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observação da alteração de custo</label>
                                <input type="text" name="novo_custo_observacao" class="form-control" value="{{ old('novo_custo_observacao') }}" maxlength="500" placeholder="Ex.: reajuste fornecedor, NF 1234">
                                @error('novo_custo_observacao')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <p class="small mb-3">
                                <a href="{{ route('produtos.custos.historico', $produto->id) }}"><i class="bx bx-history me-1"></i> Ver histórico de custo</a>
                            </p>
                        @endif
                        @php
                            $unidadeValorEdit = old('unidade', $produto->unidade ?: 'un');
                            $estoqueInteiroForm = \App\Modules\Core\Models\Produto::estoqueExigeInteiro($unidadeValorEdit);
                        @endphp
                        @include('produtos.partials.unidade-select', ['valorSelecionado' => $unidadeValorEdit])
                        <div class="mb-3">
                            <label class="form-label">Estoque <span class="text-danger">*</span></label>
                            <input type="number" name="estoque" id="produto_estoque_input" class="form-control" step="{{ $estoqueInteiroForm ? '1' : '0.001' }}" min="0" value="{{ old('estoque', $produto->estoque) }}" required>
                            <small class="text-muted">O passo do campo segue a unidade (inteiro ou até 3 decimais).</small>
                            @error('estoque')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="hidden" name="ativo" value="0">
                                <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" {{ old('ativo', $produto->ativo) ? 'checked' : '' }}>
                                <label class="form-check-label" for="ativo">Ativo (disponível para venda)</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Adicionar fotos ou anexos</label>
                            <input type="file" name="anexos[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                            <small class="text-muted">Imagens (jpg, png, gif, webp) ou documentos (PDF, Word, Excel, txt). Máx. 5 MB cada.</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Salvar</button>
                            <a href="{{ route('produtos.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>

                    <hr class="my-4">
                    <h5 class="mb-2"><i class="bx bx-images"></i> Fotos e anexos já cadastrados</h5>
                    @if($produto->anexos->count() > 0)
                        <div class="mb-3">
                            <div class="row g-2">
                                @foreach($produto->anexos as $anexo)
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="border rounded p-2 position-relative">
                                            @if($anexo->isImagem())
                                                <a href="{{ $anexo->url }}" target="_blank" class="d-block text-center">
                                                    <img src="{{ $anexo->url }}" alt="" class="img-fluid rounded" style="max-height: 120px; object-fit: cover;">
                                                </a>
                                            @else
                                                <a href="{{ $anexo->url }}" target="_blank" class="d-block text-center text-muted py-2">
                                                    <i class="bx bx-file font-size-32"></i><br>
                                                    <small>{{ Str::limit($anexo->nome_arquivo, 20) }}</small>
                                                </a>
                                            @endif
                                            <form action="{{ route('produtos.anexos.destroy', [$produto->id, $anexo->id]) }}" method="POST" class="mt-1 text-center" onsubmit="return confirm('Remover este anexo?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i> Remover</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-muted small mb-2">Nenhuma foto ou anexo. Use o campo acima para adicionar e clique em Salvar.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script>
(function () {
    const u = document.getElementById('produto_unidade_select');
    const e = document.getElementById('produto_estoque_input');
    if (!u || !e) return;
    const inteiro = new Set(@json(\App\Modules\Core\Models\Produto::unidadesCodigosEstoqueInteiro()));
    function sync() {
        e.step = inteiro.has(u.value) ? '1' : '0.001';
    }
    u.addEventListener('change', sync);
    sync();
})();
</script>
@endsection