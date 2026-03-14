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

                <!-- Clientes Vinculados -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Clientes Vinculados ({{ $operacao->operationClients->count() }})</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>CPF</th>
                                        <th>Limite de Crédito</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($operacao->operationClients as $vinculo)
                                        <tr>
                                            <td>
                                                @if($vinculo->cliente)
                                                    <a href="{{ route('clientes.show', $vinculo->cliente_id) }}">
                                                        {{ $vinculo->cliente->nome }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">Cliente #{{ $vinculo->cliente_id }} (removido ou inacessível)</span>
                                                @endif
                                            </td>
                                            <td>{{ $vinculo->cliente?->documento_formatado ?? '-' }}</td>
                                            <td>R$ {{ number_format($vinculo->limite_credito, 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $vinculo->status === 'ativo' ? 'success' : 'danger' }}">
                                                    {{ ucfirst($vinculo->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    @if($vinculo->cliente)
                                                        <a href="{{ route('clientes.show', $vinculo->cliente_id) }}" 
                                                           class="btn btn-sm btn-info" title="Ver Detalhes">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if($vinculo->cliente->temWhatsapp())
                                                            <a href="{{ $vinculo->cliente->whatsapp_link }}" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-success" 
                                                               title="Falar no WhatsApp">
                                                                <i class="bx bxl-whatsapp"></i>
                                                            </a>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Nenhum cliente vinculado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Empréstimos -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Empréstimos ({{ $operacao->emprestimos->count() }})</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($operacao->emprestimos->take(10) as $emprestimo)
                                        <tr>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emprestimo->id) }}">
                                                    #{{ $emprestimo->id }}
                                                </a>
                                            </td>
                                            <td>{{ $emprestimo->cliente->nome }}</td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $emprestimo->status === 'ativo' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($emprestimo->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $emprestimo->created_at->format('d/m/Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center">Nenhum empréstimo encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js -->
    @endsection