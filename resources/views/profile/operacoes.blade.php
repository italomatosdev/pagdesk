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
@section('css')
    <style>
        .operacao-card-preferida {
            border-color: rgba(var(--bs-warning-rgb), 0.55) !important;
            box-shadow: 0 0.125rem 0.5rem rgba(var(--bs-warning-rgb), 0.12);
        }
        .btn-operacao-estrela {
            width: 2.5rem;
            height: 2.5rem;
            padding: 0;
            margin: 0;
            line-height: 1;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
        }
        .btn-operacao-estrela .bx {
            display: block;
            width: 1.25rem;
            height: 1.25rem;
            font-size: 1.25rem;
            line-height: 1;
        }
        .btn-operacao-estrela:hover:not(:disabled) {
            transform: scale(1.06);
        }
        .btn-operacao-estrela:focus-visible {
            outline: 2px solid var(--bs-warning);
            outline-offset: 2px;
        }
        .btn-operacao-estrela.is-ativa {
            background-color: var(--bs-warning);
            border: 1px solid var(--bs-warning);
            color: var(--bs-dark);
        }
        .btn-operacao-estrela.is-ativa:hover {
            background-color: var(--bs-warning);
            filter: brightness(0.95);
        }
        .btn-operacao-estrela.is-inativa {
            color: var(--bs-secondary);
            background-color: var(--bs-tertiary-bg);
            border: 1px dashed var(--bs-border-color);
        }
        .btn-operacao-estrela.is-inativa:hover {
            color: var(--bs-warning);
            border-color: rgba(var(--bs-warning-rgb), 0.5);
            background-color: rgba(var(--bs-warning-rgb), 0.08);
        }
    </style>
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
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($operacoes->isEmpty())
                        <p class="text-muted mb-0">Você não está vinculado a nenhuma operação.</p>
                    @else
                        <p class="text-muted mb-3">Total: {{ $operacoes->count() }} operação(ões).</p>

                        @if($operacoes->count() > 1)
                            <div class="rounded-3 border bg-light p-3 p-md-4 mb-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                                    <div>
                                        <h6 class="mb-2 d-flex align-items-center gap-2">
                                            <i class="bx bxs-star text-warning"></i>
                                            Operação padrão nos filtros
                                        </h6>
                                        <p class="text-muted small mb-0">
                                            Clique na <strong>estrela</strong> no card da operação que você usa no dia a dia — o sistema vai pré-selecioná-la em caixa, aprovações e outras telas. Opcional.
                                        </p>
                                    </div>
                                    <form action="{{ route('profile.operacoes-preferida') }}" method="POST" class="flex-shrink-0">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="operacao_id" value="">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1"
                                            @if($preferidaId === null) disabled @endif
                                            title="Remover pré-seleção automática">
                                            <i class="bx bx-star"></i>
                                            Sem preferência
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <p class="text-muted small mb-4">Com apenas uma operação vinculada, ela já é o contexto natural — não é necessário marcar preferência.</p>
                        @endif

                        <div class="row g-3">
                            @foreach($operacoes as $operacao)
                                @php
                                    $isPreferida = (int) ($preferidaId ?? 0) === (int) $operacao->id;
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <div class="card border shadow-none h-100 position-relative {{ $isPreferida ? 'operacao-card-preferida' : '' }}">
                                        @if($operacoes->count() > 1)
                                            <div class="position-absolute top-0 end-0 p-2" style="z-index: 1;">
                                                <form action="{{ route('profile.operacoes-preferida') }}" method="POST" class="mb-0">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="operacao_id" value="{{ $operacao->id }}">
                                                    <button type="submit"
                                                        class="btn-operacao-estrela {{ $isPreferida ? 'is-ativa' : 'is-inativa' }}"
                                                        title="{{ $isPreferida ? 'Operação padrão (clique para manter)' : 'Definir como operação padrão nos filtros' }}"
                                                        aria-label="{{ $isPreferida ? 'Operação padrão selecionada' : 'Definir como operação padrão' }}"
                                                        aria-pressed="{{ $isPreferida ? 'true' : 'false' }}">
                                                        <i class="bx {{ $isPreferida ? 'bxs-star' : 'bx-star' }}" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                        <div class="card-body d-flex align-items-center pt-4 {{ $operacoes->count() > 1 ? 'pe-5' : '' }}">
                                            <div class="avatar avatar-md me-3 flex-shrink-0">
                                                <div class="avatar-title rounded bg-primary-subtle text-primary">
                                                    <i class="bx bx-briefcase font-size-20"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 min-w-0">
                                                <h6 class="mb-1">{{ $operacao->nome }}</h6>
                                                @if(!empty($operacao->pivot?->role))
                                                    <div class="mt-1">
                                                        @php
                                                            $pr = $operacao->pivot->role;
                                                        @endphp
                                                        <span class="badge bg-{{ $pr === 'administrador' ? 'danger' : ($pr === 'gestor' ? 'warning' : 'info') }}">{{ ucfirst($pr) }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($operacao->descricao))
                                                    <small class="text-muted d-block mt-1">{{ Str::limit($operacao->descricao, 60) }}</small>
                                                @endif
                                                @if($operacoes->count() > 1 && $isPreferida)
                                                    <div class="mt-2">
                                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25">
                                                            <i class="bx bxs-star align-middle"></i> Padrão nos filtros
                                                        </span>
                                                    </div>
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
