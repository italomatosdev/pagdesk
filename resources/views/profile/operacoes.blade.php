@extends('layouts.master')
@section('title')
    Minhas Operações
@endsection
@section('page-title')
    Minhas Operações
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Operações que você faz parte</h4>
                        <a href="{{ route('profile.show') }}" class="btn btn-secondary btn-sm">
                            <i class="bx bx-arrow-back"></i> Voltar ao perfil
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($operacoes->isEmpty())
                        <p class="text-muted mb-0">Você não está vinculado a nenhuma operação.</p>
                    @else
                        <p class="text-muted mb-3">Total: {{ $operacoes->count() }} operação(ões).</p>
                        <div class="row g-3">
                            @foreach($operacoes as $operacao)
                                <div class="col-md-6 col-lg-4">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <div class="avatar avatar-md me-3">
                                                <div class="avatar-title rounded bg-primary-subtle text-primary">
                                                    <i class="bx bx-briefcase font-size-20"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $operacao->nome }}</h6>
                                                @if(auth()->user()->roles->isNotEmpty())
                                                    <div class="mt-1">
                                                        @foreach(auth()->user()->roles as $role)
                                                            <span class="badge bg-{{ $role->name === 'administrador' ? 'danger' : ($role->name === 'gestor' ? 'warning' : 'info') }} me-1">{{ ucfirst($role->name) }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if(!empty($operacao->descricao))
                                                    <small class="text-muted d-block mt-1">{{ Str::limit($operacao->descricao, 60) }}</small>
                                                @endif
                                            </div>
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
