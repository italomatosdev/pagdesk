@extends('layouts.master')
@section('title')
    Operações
@endsection
@section('page-title')
    Operações
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
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Lista de Operações</h4>
                            <a href="{{ route('operacoes.create') }}" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Nova Operação
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
                                        <th>Código</th>
                                        <th>Descrição</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($operacoes as $operacao)
                                        <tr>
                                            <td>{{ $operacao->id }}</td>
                                            <td>{{ $operacao->nome }}</td>
                                            <td>{{ $operacao->codigo ?? '-' }}</td>
                                            <td>{{ Str::limit($operacao->descricao, 50) ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $operacao->ativo ? 'success' : 'danger' }}">
                                                    {{ $operacao->ativo ? 'Ativo' : 'Inativo' }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('operacoes.show', $operacao->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <a href="{{ route('operacoes.edit', $operacao->id) }}" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhuma operação encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-end">
                            {{ $operacoes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js -->
    @endsection