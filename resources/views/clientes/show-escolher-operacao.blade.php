@extends('layouts.master')
@section('title')
    Escolher operação — Ver cliente
@endsection
@section('page-title')
    Ver cliente: {{ $cliente->nome }}
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Em qual operação deseja ver a ficha?</h4>
                    <p class="text-muted mb-0 small mt-2">
                        O cadastro é exibido <strong>por operação</strong>. Escolha a operação para ver nome, contato e endereço dessa ficha.
                    </p>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="list-group">
                        @foreach($opcoes as $op)
                            <a href="{{ route('clientes.show', ['id' => $cliente->id, 'operacao_id' => $op['operacao_id']]) }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $op['nome'] }}</strong>
                                    @if(!empty($op['empresa_nome']))
                                        <br><small class="text-muted"><i class="bx bx-building"></i> {{ $op['empresa_nome'] }}</small>
                                    @endif
                                </div>
                                <i class="bx bx-chevron-right font-size-20"></i>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('clientes.show', ['id' => $cliente->id, 'geral' => 1]) }}" class="btn btn-outline-secondary">
                            <i class="bx bx-list-ul"></i> Ver cadastro geral (sem filtro por operação)
                        </a>
                        <a href="{{ route('clientes.index') }}" class="btn btn-secondary ms-2">
                            <i class="bx bx-arrow-back"></i> Voltar à lista
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
