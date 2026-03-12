@extends('layouts.master')
@section('title')
    Empresa #{{ $empresa->id }}
@endsection
@section('page-title')
    Empresa: {{ $empresa->nome }}
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Informações da Empresa</h4>
                            <div class="d-flex gap-2">
                                <a href="{{ route('super-admin.empresas.usuarios.create', $empresa->id) }}" class="btn btn-primary">
                                    <i class="bx bx-user-plus"></i> Criar Usuário
                                </a>
                                <a href="{{ route('super-admin.empresas.operacoes.create', $empresa->id) }}" class="btn btn-info">
                                    <i class="bx bx-plus-circle"></i> Criar Operação
                                </a>
                                <a href="{{ route('super-admin.empresas.edit', $empresa->id) }}" class="btn btn-warning">
                                    <i class="bx bx-edit"></i> Editar
                                </a>
                                @if($empresa->status == 'ativa')
                                    <form action="{{ route('super-admin.empresas.suspender', $empresa->id) }}" 
                                          method="POST" class="d-inline" 
                                          onsubmit="return confirm('Tem certeza que deseja suspender esta empresa?');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bx bx-pause"></i> Suspender
                                        </button>
                                    </form>
                                @elseif($empresa->status == 'suspensa')
                                    <form action="{{ route('super-admin.empresas.ativar', $empresa->id) }}" 
                                          method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success">
                                            <i class="bx bx-play"></i> Ativar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Nome:</strong> {{ $empresa->nome }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Razão Social:</strong> {{ $empresa->razao_social ?? '-' }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>CNPJ:</strong> 
                                {{ $empresa->cnpj ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $empresa->cnpj) : '-' }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Status:</strong>
                                <span class="badge bg-{{ $empresa->status == 'ativa' ? 'success' : ($empresa->status == 'suspensa' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($empresa->status) }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Plano:</strong>
                                <span class="badge bg-info">{{ ucfirst($empresa->plano) }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email:</strong> {{ $empresa->email_contato ?? '-' }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Telefone:</strong> {{ $empresa->telefone ?? '-' }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Data de Ativação:</strong> 
                                {{ $empresa->data_ativacao ? $empresa->data_ativacao->format('d/m/Y') : '-' }}
                            </div>
                            @if($empresa->data_expiracao)
                                <div class="col-md-6 mb-3">
                                    <strong>Data de Expiração:</strong> 
                                    {{ $empresa->data_expiracao->format('d/m/Y') }}
                                </div>
                            @endif
                            <div class="col-md-6 mb-3">
                                <strong>Cadastrada em:</strong> 
                                {{ $empresa->created_at->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações da Empresa -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Configurações da Empresa</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        @if($empresa->permiteMultiplasOperacoes())
                                            <i class="bx bx-check-circle text-success" style="font-size: 1.5rem;"></i>
                                        @else
                                            <i class="bx bx-x-circle text-danger" style="font-size: 1.5rem;"></i>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <strong>Permitir Múltiplas Operações</strong>
                                        </h6>
                                        <p class="mb-0">
                                            @if($empresa->permiteMultiplasOperacoes())
                                                <span class="badge bg-success">Ativado</span>
                                                <small class="text-muted d-block mt-1">
                                                    Empresa pode ter múltiplas operações.
                                                </small>
                                            @else
                                                <span class="badge bg-danger">Desativado</span>
                                                <small class="text-muted d-block mt-1">
                                                    Empresa terá apenas uma operação.
                                                </small>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bx bx-info-circle"></i> 
                            <strong>Nota:</strong> As configurações de aprovação e liberação de empréstimos são definidas por operação, não por empresa. 
                            Verifique as configurações de cada operação para ver os detalhes do workflow.
                        </div>

                        <div class="mt-3 pt-3 border-top">
                            <a href="{{ route('super-admin.empresas.edit', $empresa->id) }}" class="btn btn-warning">
                                <i class="bx bx-edit"></i> Editar Configurações
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0 text-white">Estatísticas</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Operações:</strong> 
                            <span class="badge bg-primary">{{ $estatisticas['operacoes'] }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Usuários:</strong> 
                            <span class="badge bg-info">{{ $estatisticas['usuarios'] }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Clientes:</strong> 
                            <span class="badge bg-success">{{ $estatisticas['clientes'] }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Empréstimos Ativos:</strong> 
                            <span class="badge bg-warning">{{ $estatisticas['emprestimos_ativos'] }}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Total de Empréstimos:</strong> 
                            <span class="badge bg-secondary">{{ $estatisticas['emprestimos_total'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
    @endsection
