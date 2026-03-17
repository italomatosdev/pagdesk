@extends('layouts.master')
@section('title')
    Devedores
@endsection
@section('page-title')
    Devedores
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filtro</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Clientes com pelo menos uma parcela em atraso (empréstimos ativos). Lista compartilhada no sistema, independente de operação ou consultor.</p>
                    <form method="GET" action="{{ route('consultas.devedores') }}" class="row g-3 align-items-end">
                        <div class="col-auto">
                            <label class="form-label">Mínimo de dias em atraso</label>
                            <input type="number" name="dias_min" class="form-control" min="0" value="{{ $diasMin }}" style="width: 120px;">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Atualizar
                            </button>
                            <a href="{{ route('consultas.devedores') }}" class="btn btn-secondary">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Mural de devedores (acima de {{ $diasMin }} dia(s) de atraso)</h5>
                    <span class="badge bg-danger">{{ $clientes->count() }} cliente(s)</span>
                </div>
                <div class="card-body">
                    @if($clientes->isEmpty())
                        <p class="text-muted text-center py-5 mb-0">
                            <i class="bx bx-check-circle font-size-48 d-block mb-2"></i>
                            Nenhum devedor encontrado para o filtro informado.
                        </p>
                    @else
                        <div class="row g-3">
                            @foreach($clientes as $cliente)
                                @php
                                    $selfie = $cliente->getDocumentoPorCategoria('selfie');
                                    $selfieUrl = $selfie && $selfie->arquivo_path ? asset('storage/' . $selfie->arquivo_path) : null;
                                @endphp
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <div class="card border h-100 mb-0">
                                        <div class="card-body p-2 text-center">
                                            <div class="devedor-foto rounded overflow-hidden bg-light d-inline-block mb-2" style="width: 120px; height: 120px;">
                                                @if($selfieUrl)
                                                    <img src="{{ $selfieUrl }}" alt="" class="w-100 h-100" style="object-fit: cover;">
                                                @else
                                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-secondary">
                                                        <i class="bx bx-user font-size-40"></i>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="small fw-bold text-truncate" title="{{ $cliente->nome }}">{{ $cliente->nome }}</div>
                                            <div class="small text-muted">{{ $cliente->documento_mascarado }}</div>
                                            @if(isset($diasAtrasoPorCliente[$cliente->id]))
                                                <span class="badge bg-warning text-dark mt-1">{{ $diasAtrasoPorCliente[$cliente->id] }} {{ $diasAtrasoPorCliente[$cliente->id] === 1 ? 'dia' : 'dias' }} em atraso</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
