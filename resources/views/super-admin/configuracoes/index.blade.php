@extends('layouts.master')
@section('title')
    Configurações do sistema
@endsection
@section('page-title')
    Configurações do sistema
@endsection
@section('body')
    <body>
@endsection
@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            {{-- Seção: Manutenção --}}
            <div class="card border-{{ $manutencaoAtiva ? 'warning' : 'secondary' }}">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-lock-open-alt me-2"></i>
                        Modo manutenção
                    </h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <p class="text-muted mb-0">
                            {{ $manutencaoAtiva ? 'Sistema em manutenção. Apenas Super Admin pode acessar o sistema.' : 'Sistema disponível para todos os usuários.' }}
                        </p>
                    </div>
                    <form method="post" action="{{ route('super-admin.manutencao.toggle') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-{{ $manutencaoAtiva ? 'success' : 'warning' }}">
                            {{ $manutencaoAtiva ? 'Desativar manutenção' : 'Ativar manutenção' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Futuras seções podem ser adicionadas aqui --}}
        {{-- <div class="col-12 mt-3">
            <div class="card">
                <div class="card-header">...</div>
                <div class="card-body">...</div>
            </div>
        </div> --}}
    </div>
@endsection
