@extends('layouts.master')
@section('title')
    Clientes
@endsection
@section('page-title')
    Clientes
@endsection
@section('body')

    <body>
    <body>
    @endsection
    @section('content')
        <!-- Cards de contadores (respeitam os filtros da listagem) -->
        <div class="row mb-3">
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-user font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-check-circle font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['com_emprestimo_ativo'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Com empréstimo ativo</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-body text-center">
                        <i class="bx bx-error-circle font-size-24 text-danger"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['com_parcela_atrasada'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Com parcela atrasada</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-secondary">
                    <div class="card-body text-center">
                        <i class="bx bx-user-x font-size-24 text-secondary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['sem_emprestimo_ativo'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Sem empréstimo ativo</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body text-center">
                        <i class="bx bx-calendar-plus font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['novos_no_mes'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Novos este mês</small>
                    </div>
                </div>
            </div>
            @if($isSuperAdmin && isset($stats['pessoa_fisica']))
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body text-center">
                        <i class="bx bx-user-circle font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['pessoa_fisica'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Pessoa Física</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center">
                        <i class="bx bx-building font-size-24 text-warning"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['pessoa_juridica'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Pessoa Jurídica</small>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Lista de Clientes</h4>
                            <div class="d-flex gap-2">
                                <a href="{{ route('clientes.link-cadastro') }}" class="btn btn-outline-info">
                                    <i class="bx bx-link-alt"></i> Link de cadastro
                                </a>
                                <a href="{{ route('clientes.export', request()->only(['documento', 'cpf', 'nome', 'operacao_id'])) }}" class="btn btn-outline-success">
                                    <i class="bx bx-download"></i> Exportar CSV
                                </a>
                                <a href="{{ route('clientes.create') }}" class="btn btn-primary">
                                    <i class="bx bx-plus"></i> Novo Cliente
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('clientes.index') }}" class="mb-3" id="form-filtro-clientes">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-0">Operação</label>
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas</option>
                                        @foreach($operacoes ?? [] as $op)
                                            <option value="{{ $op->id }}" @selected((int) ($operacaoIdFiltro ?? 0) === (int) $op->id)>{{ $op->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-0">CPF/CNPJ</label>
                                    <input type="text" name="documento" class="form-control" placeholder="CPF/CNPJ"
                                           value="{{ request('documento') ?? request('cpf') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-0">
                                        Nome
                                        @if(!empty($operacaoIdFiltro))
                                            <span class="text-primary" title="Busca também na ficha desta operação">(ficha)</span>
                                        @endif
                                    </label>
                                    <input type="text" name="nome" class="form-control" placeholder="Nome"
                                           value="{{ request('nome') }}">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary me-2" id="btn-buscar-clientes">
                                        <i class="bx bx-search"></i> Buscar
                                    </button>
                                    <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>CPF/CNPJ</th>
                                        <th>Nome</th>
                                        @if($isSuperAdmin ?? false)
                                            <th>Empresa</th>
                                        @endif
                                        <th>Telefone</th>
                                        <th>Email</th>
                                        <th>Operações</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($clientes as $cliente)
                                        @php
                                            $fichaLista = (!empty($operacaoIdFiltro)) ? $cliente->operacaoDadosClientes->first() : null;
                                        @endphp
                                        <tr>
                                            <td>{{ $cliente->id }}</td>
                                            <td>{{ $cliente->documento_formatado }}</td>
                                            <td>
                                                {{ $fichaLista?->nome ?? $cliente->nome }}
                                                @if($fichaLista && trim((string) ($fichaLista->nome ?? '')) !== trim((string) $cliente->nome))
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1" title="Nome na ficha da operação filtrada">ficha</span>
                                                @endif
                                            </td>
                                            @if($isSuperAdmin ?? false)
                                                <td>
                                                    @if($cliente->empresa)
                                                        <span class="badge bg-primary">
                                                            {{ $cliente->empresa->nome }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary">Sem empresa</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>{{ $fichaLista?->telefone ? $fichaLista->telefone : ($cliente->telefone_formatado ?? '-') }}</td>
                                            <td>{{ $fichaLista?->email ?? $cliente->email ?? '-' }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <span class="badge bg-info">
                                                        {{ $cliente->operationClients->count() }} operação(ões)
                                                    </span>
                                                    @if(!($isSuperAdmin ?? false) && $cliente->empresa_id != auth()->user()->empresa_id)
                                                        <span class="badge bg-secondary" title="Cliente vinculado de outra empresa">
                                                            <i class="bx bx-link"></i> Vinculado
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="{{ !empty($operacaoIdFiltro) ? route('clientes.show', ['id' => $cliente->id, 'operacao_id' => $operacaoIdFiltro]) : route('clientes.show', $cliente->id) }}"
                                                       class="btn btn-sm btn-info" title="Ver Detalhes">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    <a href="{{ route('clientes.edit', $cliente->id) }}" 
                                                       class="btn btn-sm btn-warning" title="Editar">
                                                        <i class="bx bx-edit"></i>
                                                    </a>
                                                    @if($cliente->temWhatsapp())
                                                        <a href="{{ $cliente->whatsapp_link }}" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-success" 
                                                           title="Falar no WhatsApp">
                                                            <i class="bx bxl-whatsapp"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ ($isSuperAdmin ?? false) ? 8 : 7 }}" class="text-center">Nenhum cliente encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div class="mt-3 d-flex justify-content-end">
                            {{ $clientes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <!-- App js -->
    @endsection