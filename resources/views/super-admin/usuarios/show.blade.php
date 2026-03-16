@extends('layouts.master')
@section('title')
    Usuário - {{ $usuario->name }}
@endsection
@section('page-title')
    Detalhes do Usuário
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-4">
                <!-- Card de Informações -->
                <div class="card">
                    <div class="card-body text-center">
                        <div class="avatar-lg mx-auto mb-3">
                            <span class="avatar-title rounded-circle bg-primary text-white font-size-24">
                                {{ strtoupper(substr($usuario->name, 0, 2)) }}
                            </span>
                        </div>
                        <h5 class="mb-1">{{ $usuario->name }}</h5>
                        <p class="text-muted mb-2">{{ $usuario->email }}</p>
                        
                        <div class="mb-3">
                            @foreach($usuario->operacoes as $op)
                                @php $p = $op->pivot->role ?? 'consultor'; @endphp
                                <span class="badge bg-{{ $p === 'administrador' ? 'danger' : ($p === 'gestor' ? 'warning' : 'primary') }} me-1">
                                    {{ $op->nome }} ({{ ucfirst($p) }})
                                </span>
                            @endforeach
                            @if($usuario->operacoes->isEmpty())
                                <span class="text-muted">Nenhuma operação</span>
                            @endif
                        </div>

                        @if($usuario->empresa)
                            <p class="mb-1">
                                <i class="bx bx-building text-muted"></i>
                                <a href="{{ route('super-admin.empresas.show', $usuario->empresa_id) }}">
                                    {{ $usuario->empresa->nome }}
                                </a>
                            </p>
                        @endif

                        <hr>

                        <div class="mb-3">
                            <p class="text-muted mb-1">Status da conta</p>
                            <span class="badge bg-{{ $usuario->isAtivo() ? 'success' : 'danger' }}">
                                {{ $usuario->isAtivo() ? 'Ativo' : 'Bloqueado' }}
                            </span>
                            @if($usuario->isBloqueado() && !empty($usuario->motivo_bloqueio))
                                <p class="small text-muted mt-2 mb-0"><strong>Motivo:</strong> {{ $usuario->motivo_bloqueio }}</p>
                            @endif
                        </div>

                        <div class="row text-start">
                            <div class="col-6">
                                <p class="text-muted mb-1">Criado em</p>
                                <p class="mb-0">{{ $usuario->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="col-6">
                                <p class="text-muted mb-1">Atualizado em</p>
                                <p class="mb-0">{{ $usuario->updated_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        @if($usuario->operacoes->count() > 0)
                            <hr>
                            <p class="text-muted mb-2">Operações e papel</p>
                            @foreach($usuario->operacoes as $operacao)
                                @php $papel = $operacao->pivot->role ?? 'consultor'; @endphp
                                <span class="badge bg-light text-dark mb-1">{{ $operacao->nome }} ({{ ucfirst($papel) }})</span>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Ações -->
                <div class="card">
                    <div class="card-body">
                        <a href="{{ route('super-admin.usuarios.index') }}" class="btn btn-secondary w-100 mb-2">
                            <i class="bx bx-arrow-back"></i> Voltar para Lista
                        </a>
                        @if($usuario->empresa)
                            <a href="{{ route('super-admin.empresas.show', $usuario->empresa_id) }}" class="btn btn-info w-100">
                                <i class="bx bx-building"></i> Ver Empresa
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Formulário de Edição -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-edit"></i> Editar Usuário
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('super-admin.usuarios.update', $usuario->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $usuario->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                           value="{{ old('email', $usuario->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nova Senha</label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                           placeholder="Deixe em branco para manter a atual"
                                           autocomplete="new-password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Mínimo 8 caracteres</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmar Nova Senha</label>
                                    <input type="password" name="password_confirmation" class="form-control"
                                           placeholder="Confirme a nova senha"
                                           autocomplete="new-password">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Empresa <span class="text-danger">*</span></label>
                                <select name="empresa_id" id="empresa_id" class="form-select @error('empresa_id') is-invalid @enderror" required>
                                    <option value="">Selecione uma empresa...</option>
                                    @foreach($empresas as $empresa)
                                        <option value="{{ $empresa->id }}" {{ old('empresa_id', $usuario->empresa_id) == $empresa->id ? 'selected' : '' }}>
                                            {{ $empresa->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('empresa_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Ao mudar de empresa, as operações vinculadas serão removidas.</small>
                            </div>

                            <div class="mb-3" id="operacoes-container">
                                <label class="form-label">Operações e papel</label>
                                <small class="text-muted d-block mb-2">Marque as operações que este usuário terá acesso e escolha o papel em cada uma.</small>
                                @if($operacoes->count() > 0)
                                    @php
                                        $papelPorOperacao = $usuario->operacoes->pluck('pivot.role', 'id')->map(fn ($r) => $r ?? 'consultor')->toArray();
                                        $oldOps = old('operacoes', $usuario->operacoes->pluck('id')->toArray());
                                        $oldRoles = old('operacao_role', $papelPorOperacao);
                                    @endphp
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 60px;">Vincular</th>
                                                    <th>Operação</th>
                                                    <th style="width: 180px;">Papel na operação</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($operacoes as $operacao)
                                                    @php
                                                        $checked = in_array($operacao->id, $oldOps);
                                                        $selectedRole = $oldRoles[$operacao->id] ?? 'consultor';
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="operacoes[]"
                                                                       value="{{ $operacao->id }}" id="operacao_{{ $operacao->id }}"
                                                                       {{ $checked ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="operacao_{{ $operacao->id }}">&nbsp;</label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <label class="form-label mb-0" for="operacao_{{ $operacao->id }}">{{ $operacao->nome }}</label>
                                                        </td>
                                                        <td>
                                                            <select name="operacao_role[{{ $operacao->id }}]" class="form-select form-select-sm">
                                                                <option value="consultor" {{ $selectedRole === 'consultor' ? 'selected' : '' }}>Consultor</option>
                                                                <option value="gestor" {{ $selectedRole === 'gestor' ? 'selected' : '' }}>Gestor</option>
                                                                <option value="administrador" {{ $selectedRole === 'administrador' ? 'selected' : '' }}>Administrador</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">
                                        <i class="bx bx-info-circle"></i> Nenhuma operação disponível para esta empresa.
                                    </p>
                                @endif
                                @error('operacoes')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr>

                            <h6 class="mb-2">Bloquear / Desbloquear conta</h6>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1"
                                           {{ old('ativo', $usuario->isAtivo()) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="ativo">Usuário ativo (pode acessar o sistema)</label>
                                </div>
                                <small class="text-muted">Desmarque para bloquear: o usuário não poderá fazer login e não aparecerá em seleções de novos empréstimos. Ainda aparecerá em listas, relatórios e em Caixa (movimentação manual / fechamento).</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Motivo do bloqueio (opcional)</label>
                                <textarea name="motivo_bloqueio" class="form-control" rows="2" placeholder="Ex.: Afastamento, desligamento...">{{ old('motivo_bloqueio', $usuario->motivo_bloqueio) }}</textarea>
                                <small class="text-muted">Exibido para o usuário na tela de conta bloqueada.</small>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('super-admin.usuarios.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            // Quando mudar a empresa, recarrega a página para buscar operações da nova empresa
            document.getElementById('empresa_id').addEventListener('change', function() {
                const empresaId = this.value;
                if (empresaId && empresaId != '{{ $usuario->empresa_id }}') {
                    if (confirm('Ao mudar de empresa, as operações vinculadas serão removidas. Deseja continuar?')) {
                        // Marcar que as operações devem ser removidas
                        document.querySelectorAll('input[name="operacoes[]"]').forEach(function(checkbox) {
                            checkbox.checked = false;
                        });
                    } else {
                        this.value = '{{ $usuario->empresa_id }}';
                    }
                }
            });
        </script>
    @endsection
