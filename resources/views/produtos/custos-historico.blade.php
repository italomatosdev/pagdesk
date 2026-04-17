@extends('layouts.master')
@section('title')
    Histórico de custo — {{ $produto->nome }}
@endsection
@section('page-title')
    Histórico de custo
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row mb-3">
        <div class="col">
            <a href="{{ route('produtos.show', $produto->id) }}" class="btn btn-secondary"><i class="bx bx-arrow-back me-1"></i> Voltar ao produto</a>
            <a href="{{ route('produtos.edit', $produto->id) }}" class="btn btn-warning"><i class="bx bx-edit me-1"></i> Editar produto</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ $produto->nome }} <small class="text-muted">#{{ $produto->id }}</small></h5>
        </div>
        <div class="card-body p-0">
            @if($produto->custoHistoricos->isEmpty())
                <p class="p-3 text-muted mb-0">Nenhum registro de custo ainda.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Válido de</th>
                                <th>Válido até</th>
                                <th class="text-end">Custo unit.</th>
                                <th>Usuário</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($produto->custoHistoricos as $h)
                                <tr>
                                    <td>{{ $h->valido_de->format('d/m/Y H:i') }}</td>
                                    <td>{{ $h->valido_ate ? $h->valido_ate->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="text-end">R$ {{ number_format((float) $h->custo_unitario, 2, ',', '.') }}</td>
                                    <td>{{ $h->user->name ?? '—' }}</td>
                                    <td>{{ $h->observacao ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
