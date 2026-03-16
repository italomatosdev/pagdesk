@extends('layouts.master')
@section('title')
    Novo Usuário
@endsection
@section('page-title')
    Novo Usuário
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Criar Novo Usuário</h4>
                            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle"></i> Este usuário será criado na sua empresa <strong>{{ $empresa->nome }}</strong> e poderá ser vinculado às operações abaixo.
                        </div>

                        <form action="{{ route('usuarios.store') }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                           value="{{ old('email') }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Senha <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                           required minlength="8">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Mínimo de 8 caracteres</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirmar Senha <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" class="form-control"
                                           required minlength="8">
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="mb-3">
                                <label class="form-label">Operações e papel</label>
                                <small class="text-muted d-block mb-2">Marque as operações que este usuário terá acesso e escolha o papel em cada uma.</small>
                                @if($operacoes->count() > 0)
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
                                                        $oldOps = old('operacoes', []);
                                                        $oldRoles = old('operacao_role', []);
                                                        $checked = in_array($operacao->id, $oldOps);
                                                        $selectedRole = $oldRoles[$operacao->id] ?? 'consultor';
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="form-check">
                                                                <input class="form-check-input row-operacao-check" type="checkbox" name="operacoes[]"
                                                                       value="{{ $operacao->id }}" id="operacao_{{ $operacao->id }}"
                                                                       {{ $checked ? 'checked' : '' }}
                                                                       data-op-id="{{ $operacao->id }}">
                                                                <label class="form-check-label" for="operacao_{{ $operacao->id }}">&nbsp;</label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <label class="form-label mb-0" for="operacao_{{ $operacao->id }}">
                                                                {{ $operacao->nome }}
                                                                @if($operacao->codigo)
                                                                    <span class="text-muted">({{ $operacao->codigo }})</span>
                                                                @endif
                                                            </label>
                                                        </td>
                                                        <td>
                                                            <select name="operacao_role[{{ $operacao->id }}]" class="form-select form-select-sm row-operacao-role" data-op-id="{{ $operacao->id }}">
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
                                    <div class="alert alert-warning mb-0">
                                        <i class="bx bx-info-circle"></i> Sua empresa ainda não possui operações cadastradas.
                                    </div>
                                @endif
                                @error('operacoes')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Criar Usuário
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
    @endsection
