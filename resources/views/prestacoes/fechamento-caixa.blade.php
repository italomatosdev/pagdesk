@extends('layouts.master')
@section('title')
    Fechamento de Caixa
@endsection
@section('page-title')
    Fechamento de Caixa
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-lock-alt"></i> Fechar Caixa de Usuário
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Selecione a operação para ver usuários com saldo diferente de zero. Saldo positivo: pode fechar (notificação para envio). Saldo negativo: apenas conferência no extrato — use a tela unificada de Fechamento de Caixa.
                    </p>

                    <!-- Filtro de Operação -->
                    <form method="GET" action="{{ route('prestacoes.fechamento-caixa') }}" class="mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" class="form-select" onchange="this.form.submit()">
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ $operacaoId == $op->id ? 'selected' : '' }}>
                                            {{ $op->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>

                    @if($operacaoId)
                        @if($usuariosComSaldo->isEmpty())
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle"></i> Nenhum usuário com saldo diferente de zero nesta operação.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Função</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-center" width="200">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($usuariosComSaldo as $usuario)
                                            <tr>
                                                <td>
                                                    <strong>{{ $usuario->name }}</strong>
                                                    <br><small class="text-muted">{{ $usuario->email }}</small>
                                                </td>
                                                <td>
                                                    @foreach($usuario->roles as $role)
                                                        <span class="badge bg-{{ $role->name === 'administrador' ? 'danger' : ($role->name === 'gestor' ? 'warning' : 'primary') }}">
                                                            {{ ucfirst($role->name) }}
                                                        </span>
                                                    @endforeach
                                                </td>
                                                <td class="text-end">
                                                    <span class="fw-bold fs-5 {{ round((float) $usuario->saldo_operacao, 2) >= 0 ? 'text-success' : 'text-danger' }}">
                                                        R$ {{ number_format($usuario->saldo_operacao, 2, ',', '.') }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    @if(round((float) $usuario->saldo_operacao, 2) > 0)
                                                        <button type="button" class="btn btn-danger btn-sm"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalFechar{{ $usuario->id }}">
                                                            <i class="bx bx-lock-alt"></i> Fechar Caixa
                                                        </button>
                                                    @else
                                                        <a href="{{ route('fechamento-caixa.conferir', ['usuario_id' => $usuario->id, 'operacao_id' => $operacaoId]) }}" class="btn btn-outline-secondary btn-sm">
                                                            <i class="bx bx-list-ul"></i> Ver extrato
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>

                                            @if(round((float) $usuario->saldo_operacao, 2) > 0)
                                            <!-- Modal de Confirmação -->
                                            <div class="modal fade" id="modalFechar{{ $usuario->id }}" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="{{ route('prestacoes.fechar-caixa') }}">
                                                            @csrf
                                                            <input type="hidden" name="usuario_id" value="{{ $usuario->id }}">
                                                            <input type="hidden" name="operacao_id" value="{{ $operacaoId }}">
                                                            
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    <i class="bx bx-lock-alt text-danger"></i> Confirmar Fechamento
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Você está prestes a fechar o caixa de:</p>
                                                                <div class="alert alert-secondary">
                                                                    <strong>{{ $usuario->name }}</strong><br>
                                                                    <span class="text-success fw-bold">
                                                                        Saldo: R$ {{ number_format($usuario->saldo_operacao, 2, ',', '.') }}
                                                                    </span>
                                                                </div>
                                                                <p class="text-muted small">
                                                                    O usuário receberá uma notificação e deverá enviar o valor e anexar o comprovante.
                                                                </p>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Observações (opcional)</label>
                                                                    <textarea name="observacoes" class="form-control" rows="2" 
                                                                              placeholder="Motivo do fechamento, instruções, etc."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-danger">
                                                                    <i class="bx bx-lock-alt"></i> Confirmar Fechamento
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-warning">
                            <i class="bx bx-info-circle"></i> Selecione uma operação para ver os usuários com saldo.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <a href="{{ route('prestacoes.index') }}" class="btn btn-secondary">
                <i class="bx bx-arrow-back"></i> Voltar
            </a>
        </div>
    </div>
@endsection
