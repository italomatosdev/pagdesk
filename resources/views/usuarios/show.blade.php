@extends('layouts.master')
@section('title')
    Usuário #{{ $usuario->id }}
@endsection
@section('page-title')
    Usuário: {{ $usuario->name }}
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
                        <h4 class="card-title mb-0">Informações do Usuário</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Nome:</strong> {{ $usuario->name }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email:</strong> {{ $usuario->email }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Cadastrado em:</strong> 
                                {{ $usuario->created_at->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operações -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Operações do Usuário</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Operações Atuais:</h6>
                            @forelse($usuario->operacoes as $operacao)
                                <span class="badge bg-primary me-2 mb-2">
                                    {{ $operacao->nome }}
                                </span>
                            @empty
                                <p class="text-muted">Nenhuma operação vinculada.</p>
                            @endforelse
                        </div>

                        <hr>

                        <div>
                            <h6>Vincular a Operações:</h6>
                            <form action="{{ route('usuarios.atualizar-operacoes', $usuario->id) }}" method="POST" class="form-atualizar-operacoes">
                                @csrf
                                <div class="row">
                                    @forelse($operacoes as $operacao)
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       name="operacoes[]" 
                                                       value="{{ $operacao->id }}" 
                                                       id="operacao_{{ $operacao->id }}"
                                                       {{ $usuario->operacoes->contains($operacao->id) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="operacao_{{ $operacao->id }}">
                                                    {{ $operacao->nome }}
                                                </label>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted">Nenhuma operação disponível.</p>
                                    @endforelse
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-save"></i> Salvar Operações
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Papéis -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Papéis do Usuário</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Papéis Atuais:</h6>
                            @forelse($usuario->roles as $role)
                                <span class="badge bg-primary me-2 mb-2">
                                    {{ $role->display_name }}
                                    <form action="{{ route('usuarios.remover-papel', $usuario->id) }}" 
                                          method="POST" class="d-inline form-remover-papel"
                                          data-role-name="{{ $role->name }}"
                                          data-role-display="{{ $role->display_name }}">
                                        @csrf
                                        <input type="hidden" name="role_name" value="{{ $role->name }}">
                                        <button type="submit" class="btn-close btn-close-white ms-2" 
                                                style="font-size: 0.7em;"></button>
                                    </form>
                                </span>
                            @empty
                                <p class="text-muted">Nenhum papel atribuído.</p>
                            @endforelse
                        </div>

                        <hr>

                        <div>
                            <h6>Atribuir Novo Papel:</h6>
                            <form action="{{ route('usuarios.atribuir-papel', $usuario->id) }}" method="POST" class="d-flex gap-2 form-atribuir-papel">
                                @csrf
                                <select name="role_name" class="form-select" style="max-width: 300px;">
                                    <option value="">Selecione um papel...</option>
                                    @foreach($roles as $role)
                                        @if(!$usuario->roles->contains($role->id))
                                            <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-plus"></i> Atribuir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Remover papel
                document.querySelectorAll('.form-remover-papel').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const roleDisplay = this.dataset.roleDisplay;
                        
                        Swal.fire({
                            title: 'Remover Papel?',
                            html: `Deseja remover o papel <strong>${roleDisplay}</strong> do usuário?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, remover!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });

                // Atribuir papel
                document.querySelectorAll('.form-atribuir-papel').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const roleSelect = this.querySelector('select[name="role_name"]');
                        const roleName = roleSelect.value;
                        const roleText = roleSelect.options[roleSelect.selectedIndex].text;
                        
                        if (!roleName) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, selecione um papel.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        Swal.fire({
                            title: 'Atribuir Papel?',
                            html: `Deseja atribuir o papel <strong>${roleText}</strong> ao usuário?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, atribuir!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });

                // Atualizar operações
                document.querySelectorAll('.form-atualizar-operacoes').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const checkboxes = this.querySelectorAll('input[type="checkbox"]:checked');
                        const operacoesSelecionadas = Array.from(checkboxes).map(cb => cb.nextElementSibling.textContent.trim());
                        
                        let mensagem = 'Deseja atualizar as operações do usuário?';
                        if (operacoesSelecionadas.length > 0) {
                            mensagem += '<br><br><strong>Operações selecionadas:</strong><br>' + operacoesSelecionadas.join('<br>');
                        } else {
                            mensagem += '<br><br><strong class="text-warning">Nenhuma operação selecionada (todas serão removidas).</strong>';
                        }
                        
                        Swal.fire({
                            title: 'Atualizar Operações?',
                            html: mensagem,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, atualizar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });
            });
        </script>
    @endsection