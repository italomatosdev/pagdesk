@extends('layouts.master')
@section('title')
    Operação #{{ $operacao->id }} - Super Admin
@endsection
@section('page-title')
    Operação: {{ $operacao->nome }}
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
                            <h4 class="card-title mb-0">Informações da Operação</h4>
                            <a href="{{ route('super-admin.operacoes.edit', $operacao->id) }}" class="btn btn-warning">
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
                                <strong>Empresa:</strong>
                                @if($operacao->empresa)
                                    <a href="{{ route('super-admin.empresas.show', $operacao->empresa_id) }}">
                                        {{ $operacao->empresa->nome }}
                                    </a>
                                @else
                                    <span class="text-muted">Sem empresa vinculada</span>
                                @endif
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
                            @if($operacao->taxa_juros_atraso)
                                <div class="col-md-6 mb-3">
                                    <strong>Taxa de Juros por Atraso:</strong>
                                    {{ number_format($operacao->taxa_juros_atraso, 2, ',', '.') }}%
                                    <small class="text-muted">({{ $operacao->tipo_calculo_juros === 'por_dia' ? 'Por Dia' : 'Por Mês' }})</small>
                                </div>
                            @endif
                            @if($operacao->descricao)
                                <div class="col-12 mb-3">
                                    <strong>Descrição:</strong><br>
                                    {{ $operacao->descricao }}
                                </div>
                            @endif
                            <div class="col-md-6 mb-3">
                                <strong>Criado em:</strong> {{ $operacao->created_at->format('d/m/Y H:i') }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Atualizado em:</strong> {{ $operacao->updated_at->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Estatísticas</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="text-center">
                                    <h3 class="text-primary">{{ $operacao->emprestimos->count() }}</h3>
                                    <small class="text-muted">Total de Empréstimos</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-center">
                                    <h3 class="text-info">{{ $operacao->usuarios->count() }}</h3>
                                    <small class="text-muted">Usuários Vinculados</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ações</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('super-admin.operacoes.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar para Lista
                            </a>
                            <a href="{{ route('super-admin.operacoes.edit', $operacao->id) }}" class="btn btn-warning">
                                <i class="bx bx-edit"></i> Editar Operação
                            </a>
                            @if($operacao->empresa)
                                <a href="{{ route('super-admin.empresas.show', $operacao->empresa_id) }}" class="btn btn-info">
                                    <i class="bx bx-building"></i> Ver Empresa
                                </a>
                                <a href="{{ route('super-admin.empresas.usuarios.create', $operacao->empresa_id) }}" class="btn btn-primary">
                                    <i class="bx bx-user-plus"></i> Criar usuário
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
