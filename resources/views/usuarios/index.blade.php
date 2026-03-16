@extends('layouts.master')
@section('title')
    Usuários
@endsection
@section('page-title')
    Usuários e Permissões
@endsection
@section('body')

    <body>
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h4 class="card-title mb-0">Lista de Usuários</h4>
                            <a href="{{ route('usuarios.create') }}" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Novo Usuário
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Papéis</th>
                                        <th>Operação</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($usuarios as $usuario)
                                        <tr>
                                            <td>{{ $usuario->id }}</td>
                                            <td>{{ $usuario->name }}</td>
                                            <td>{{ $usuario->email }}</td>
                                            <td>
                                                @foreach($usuario->roles as $role)
                                                    <span class="badge bg-primary me-1">{{ $role->display_name }}</span>
                                                @endforeach
                                                @if($usuario->roles->isEmpty())
                                                    <span class="text-muted">Sem papéis</span>
                                                @endif
                                            </td>
                                            <td>
                                                @foreach($usuario->operacoes as $operacao)
                                                    @php $papel = $operacao->pivot->role ?? 'consultor'; @endphp
                                                    <span class="badge bg-info me-1" title="Papel: {{ ucfirst($papel) }}">{{ $operacao->nome }} ({{ ucfirst($papel) }})</span>
                                                @endforeach
                                                @if($usuario->operacoes->isEmpty())
                                                    <span class="text-muted">Sem operações</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('usuarios.show', $usuario->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhum usuário encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-end">
                            {{ $usuarios->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js -->
    @endsection