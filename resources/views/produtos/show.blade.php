@extends('layouts.master')
@section('title')
    Produto #{{ $produto->id }}
@endsection
@section('page-title')
    {{ $produto->nome }}
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
            <a href="{{ route('produtos.index') }}" class="btn btn-secondary"><i class="bx bx-arrow-back me-1"></i> Voltar</a>
            <a href="{{ route('produtos.edit', $produto->id) }}" class="btn btn-warning"><i class="bx bx-edit me-1"></i> Editar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Dados do produto</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Nome</div>
                        <div class="col-md-8"><strong>{{ $produto->nome }}</strong></div>
                    </div>
                    @if($produto->codigo)
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Código</div>
                            <div class="col-md-8">{{ $produto->codigo }}</div>
                        </div>
                    @endif
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Preço de venda</div>
                        <div class="col-md-8">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Estoque</div>
                        <div class="col-md-8">{{ number_format((float)$produto->estoque, 3, ',', '.') }} {{ $produto->unidade ?: 'un' }}</div>
                    </div>
                    @if($produto->unidade)
                        <div class="row mb-2">
                            <div class="col-md-4 text-muted">Unidade</div>
                            <div class="col-md-8">{{ $produto->unidade }}</div>
                        </div>
                    @endif
                    <div class="row mb-2">
                        <div class="col-md-4 text-muted">Status</div>
                        <div class="col-md-8">
                            <span class="badge bg-{{ $produto->ativo ? 'success' : 'secondary' }}">{{ $produto->ativo ? 'Ativo' : 'Inativo' }}</span>
                            @if((float)$produto->estoque <= 0)
                                <span class="badge bg-warning text-dark ms-1">Sem estoque</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            @php $fotos = $produto->anexos->where('tipo', 'imagem'); $documentos = $produto->anexos->where('tipo', 'documento'); @endphp
            @if($fotos->count() > 0)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bx bx-images"></i> Fotos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach($fotos as $foto)
                                <div class="col-6">
                                    <a href="{{ $foto->url }}" target="_blank">
                                        <img src="{{ $foto->url }}" alt="" class="img-fluid rounded border" style="max-height: 200px; object-fit: cover;">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            @if($documentos->count() > 0)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bx bx-file"></i> Anexos</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            @foreach($documentos as $doc)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <a href="{{ $doc->url }}" target="_blank"><i class="bx bx-file me-2"></i>{{ $doc->nome_arquivo }}</a>
                                    <span class="badge bg-secondary">{{ $doc->tamanho_formatado }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @if($produto->anexos->count() === 0)
                <div class="card mb-3">
                    <div class="card-body text-center text-muted py-4">
                        <i class="bx bx-images font-size-48"></i>
                        <p class="mt-2 mb-0">Nenhuma foto ou anexo.</p>
                        <a href="{{ route('produtos.edit', $produto->id) }}" class="btn btn-sm btn-outline-primary mt-2">Editar produto para adicionar</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
