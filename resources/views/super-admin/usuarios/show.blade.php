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
                            @foreach($usuario->roles as $role)
                                <span class="badge bg-{{ $role->name == 'administrador' ? 'danger' : ($role->name == 'gestor' ? 'warning' : 'primary') }} me-1">
                                    {{ ucfirst($role->name) }}
                                </span>
                            @endforeach
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
                            <p class="text-muted mb-2">Operações Vinculadas</p>
                            @foreach($usuario->operacoes as $operacao)
                                <span class="badge bg-light text-dark mb-1">{{ $operacao->nome }}</span>
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

                            <div class="mb-3">
                                <label class="form-label">Papéis <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach($roles as $role)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="roles[]" 
                                                   value="{{ $role->name }}" id="role_{{ $role->id }}"
                                                   {{ in_array($role->name, old('roles', $usuario->roles->pluck('name')->toArray())) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="role_{{ $role->id }}">
                                                {{ ucfirst($role->name) }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('roles')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3" id="operacoes-container">
                                <label class="form-label">Operações Vinculadas</label>
                                @if($operacoes->count() > 0)
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach($operacoes as $operacao)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="operacoes[]" 
                                                       value="{{ $operacao->id }}" id="operacao_{{ $operacao->id }}"
                                                       {{ in_array($operacao->id, old('operacoes', $usuario->operacoes->pluck('id')->toArray())) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="operacao_{{ $operacao->id }}">
                                                    {{ $operacao->nome }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">
                                        <i class="bx bx-info-circle"></i> 
                                        Nenhuma operação disponível para esta empresa.
                                    </p>
                                @endif
                                @error('operacoes')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
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
