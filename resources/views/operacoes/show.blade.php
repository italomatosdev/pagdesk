@extends('layouts.master')
@section('title')
    Operação #{{ $operacao->id }}
@endsection
@section('page-title')
    Operação: {{ $operacao->nome }}
@endsection
@section('body')

    <body>
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Informações da Operação</h4>
                            <a href="{{ route('operacoes.edit', $operacao->id) }}" class="btn btn-warning">
                                <i class="bx bx-edit"></i> Editar
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Nome:</strong> {{ $operacao->nome }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Código:</strong> {{ $operacao->codigo ?? '-' }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Status:</strong>
                                <span class="badge bg-{{ $operacao->ativo ? 'success' : 'danger' }}">
                                    {{ $operacao->ativo ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Valor de Aprovação Automática:</strong>
                                @if($operacao->valor_aprovacao_automatica)
                                    <span class="h6 text-primary">
                                        R$ {{ number_format($operacao->valor_aprovacao_automatica, 2, ',', '.') }}
                                    </span>
                                    <br><small class="text-muted">
                                        Empréstimos até este valor são aprovados automaticamente
                                    </small>
                                @else
                                    <span class="text-muted">Não configurado</span>
                                    <br><small class="text-muted">
                                        Todos os empréstimos passam pelas validações normais
                                    </small>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Requer Aprovação Manual:</strong>
                                <span class="badge bg-{{ $operacao->requer_aprovacao ? 'warning' : 'success' }}">
                                    {{ $operacao->requer_aprovacao ? 'Sim' : 'Não' }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Requer Liberação do Gestor:</strong>
                                <span class="badge bg-{{ $operacao->requer_liberacao ? 'info' : 'success' }}">
                                    {{ $operacao->requer_liberacao ? 'Sim' : 'Não' }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Consultor pode vender / ver produtos:</strong>
                                <span class="badge bg-{{ ($operacao->consultor_pode_vender ?? false) ? 'primary' : 'secondary' }}">
                                    {{ ($operacao->consultor_pode_vender ?? false) ? 'Sim' : 'Não' }}
                                </span>
                            </div>
                            @if($operacao->descricao)
                                <div class="col-12 mb-3">
                                    <strong>Descrição:</strong><br>
                                    {{ $operacao->descricao }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Usuários da Operação -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Usuários da Operação ({{ $operacao->usuarios->count() }})</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Função</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($operacao->usuarios as $usuario)
                                        <tr>
                                            <td>
                                                <a href="{{ route('usuarios.show', $usuario->id) }}">
                                                    {{ $usuario->name }}
                                                </a>
                                            </td>
                                            <td>{{ $usuario->email }}</td>
                                            <td>
                                                @foreach($usuario->roles as $role)
                                                    <span class="badge bg-{{ $role->name === 'administrador' ? 'danger' : ($role->name === 'gestor' ? 'warning' : 'info') }}">
                                                        {{ ucfirst($role->name) }}
                                                    </span>
                                                @endforeach
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    Ativo
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center">Nenhum usuário vinculado a esta operação.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Totalizadores: Clientes e Empréstimos (estilo dashboard) -->
                <div class="row mt-3">
                    <div class="col-12 col-md-6 mb-3">
                        <a href="{{ route('clientes.index', ['operacao_id' => $operacao->id]) }}" class="text-decoration-none">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-0 font-size-15">Clientes vinculados</h6>
                                            <h4 class="mt-3 mb-0 font-size-22">{{ number_format($operacao->operation_clients_count, 0, ',', '.') }}</h4>
                                        </div>
                                        <div class="">
                                            <div class="avatar">
                                                <div class="avatar-title rounded bg-primary-subtle">
                                                    <i class="bx bx-group font-size-24 mb-0 text-primary"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <a href="{{ route('emprestimos.index', ['operacao_id' => $operacao->id]) }}" class="text-decoration-none">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-0 font-size-15">Empréstimos</h6>
                                            <h4 class="mt-3 mb-0 font-size-22">{{ number_format($operacao->emprestimos_count, 0, ',', '.') }}</h4>
                                        </div>
                                        <div class="">
                                            <div class="avatar">
                                                <div class="avatar-title rounded bg-success-subtle">
                                                    <i class="bx bx-money font-size-24 mb-0 text-success"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js -->
    @endsection