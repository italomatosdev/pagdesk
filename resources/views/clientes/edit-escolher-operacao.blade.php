@extends('layouts.master')
@section('title')
    Escolher operação — Editar cliente
@endsection
@section('page-title')
    Editar ficha: {{ $cliente->nome }}
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Em qual operação deseja editar a ficha?</h4>
                    <p class="text-muted mb-0 small mt-2">
                        O cadastro é mantido <strong>por operação</strong>. Escolha a operação para carregar e salvar nome, contato, endereço e documentos dessa ficha.
                    </p>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="list-group">
                        @foreach($opcoes as $op)
                            <a href="{{ route('clientes.edit', ['id' => $cliente->id, 'operacao_id' => $op['operacao_id']]) }}"
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
                        <a href="{{ route('clientes.show', ['id' => $cliente->id, 'geral' => 1]) }}" class="btn btn-secondary">
                            <i class="bx bx-arrow-back"></i> Voltar para o cliente
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
