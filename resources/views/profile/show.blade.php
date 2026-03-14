@extends('layouts.master')
@section('title')
    Meu Perfil
@endsection
@section('page-title')
    Meu Perfil
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-xxl-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="position-relative d-inline-block mb-3">
                                @if($user->avatar)
                                    <img src="{{ $user->avatar_url }}" alt="Avatar" 
                                         class="avatar-xl rounded-circle img-thumbnail"
                                         id="avatar-preview">
                                @else
                                    <div class="avatar-xl rounded-circle img-thumbnail bg-primary-subtle d-flex align-items-center justify-content-center text-primary fw-bold border" 
                                         id="avatar-preview"
                                         style="width: 120px; height: 120px; font-size: 48px;">
                                        {{ $user->initial }}
                                    </div>
                                @endif
                                <div class="avatar-xs position-absolute bottom-0 end-0">
                                    <label for="avatar-input" class="avatar-title rounded-circle bg-primary text-white" style="cursor: pointer;">
                                        <i class="bx bx-camera"></i>
                                    </label>
                                </div>
                            </div>
                            <form action="{{ route('profile.update-avatar') }}" method="POST" enctype="multipart/form-data" id="formAvatar" class="mt-3">
                                @csrf
                                <input type="file" class="d-none" id="avatar-input" name="avatar" accept="image/jpeg,image/jpg,image/png" onchange="previewAvatar(this); this.form.submit();">
                                <label for="avatar-input" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                    <i class="bx bx-upload"></i> Alterar foto
                                </label>
                                @error('avatar')
                                    <div class="small text-danger">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block">JPG ou PNG, máx. 5MB</small>
                            </form>
                            @if($user->avatar)
                                <button type="button" class="btn btn-sm btn-danger btn-remover-avatar w-100" data-url="{{ route('profile.remove-avatar') }}">
                                    <i class="bx bx-trash"></i> Remover Avatar
                                </button>
                            @endif
                            <h5 class="mb-1 mt-3">{{ $user->name }}</h5>
                            <p class="text-muted mb-0">{{ $user->email }}</p>
                            
                            @if($user->roles->count() > 0)
                                <div class="mt-3">
                                    @foreach($user->roles as $role)
                                        <span class="badge bg-{{ $role->name === 'administrador' ? 'danger' : ($role->name === 'gestor' ? 'warning' : 'info') }} me-1">
                                            {{ ucfirst($role->name) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if($user->operacoes->count() > 0)
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-2">Operações:</small>
                                    @foreach($user->operacoes as $operacao)
                                        <span class="badge bg-secondary me-1 mb-1">{{ $operacao->nome }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="fw-bold">Cadastrado em:</th>
                                        <td class="text-muted">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th class="fw-bold">Última atualização:</th>
                                        <td class="text-muted">{{ $user->updated_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Editar Perfil</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('profile.update') }}" method="POST" id="formUpdateProfile">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="name" class="form-label">Nome</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" placeholder="Deixe em branco para manter a senha atual">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Mínimo de 8 caracteres</small>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" name="password_confirmation" 
                                       placeholder="Confirme a nova senha">
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Salvar Alterações
                                </button>
                                <a href="{{ route('dashboard.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('scripts')
        <script>
            function previewAvatar(input) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var preview = document.getElementById('avatar-preview');
                        // Se for div, transformar em img
                        if (preview.tagName === 'DIV') {
                            var img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Avatar';
                            img.className = 'avatar-xl rounded-circle img-thumbnail';
                            img.id = 'avatar-preview';
                            preview.parentNode.replaceChild(img, preview);
                            preview = img;
                        } else {
                            preview.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Remover avatar (DELETE para a rota correta, evita form aninhado)
                document.querySelectorAll('.btn-remover-avatar').forEach(btn => {
                    btn.addEventListener('click', function() {
                        var url = this.getAttribute('data-url');
                        Swal.fire({
                            title: 'Remover Avatar?',
                            text: 'Deseja realmente remover sua foto de perfil?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, remover!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                fetch(url, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': token || '',
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                }).then(function(res) {
                                    if (res.redirected) {
                                        window.location.href = res.url;
                                    } else if (res.ok) {
                                        window.location.reload();
                                    } else {
                                        return res.json().then(function(data) {
                                            Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível remover o avatar.' });
                                        }).catch(function() {
                                            window.location.reload();
                                        });
                                    }
                                }).catch(function() {
                                    window.location.reload();
                                });
                            }
                        });
                    });
                });
            });
        </script>
    @endsection
