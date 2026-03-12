@extends('layouts.master')
@section('title')
    Editar Empresa
@endsection
@section('page-title')
    Editar Empresa: {{ $empresa->nome }}
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Editar Empresa</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('super-admin.empresas.update', $empresa->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror" 
                                           value="{{ old('nome', $empresa->nome) }}" required>
                                    @error('nome')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Razão Social</label>
                                    <input type="text" name="razao_social" class="form-control @error('razao_social') is-invalid @enderror" 
                                           value="{{ old('razao_social', $empresa->razao_social) }}">
                                    @error('razao_social')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CNPJ</label>
                                    <input type="text" name="cnpj" class="form-control @error('cnpj') is-invalid @enderror" 
                                           value="{{ old('cnpj', $empresa->cnpj) }}" placeholder="00000000000000">
                                    @error('cnpj')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email de Contato</label>
                                    <input type="email" name="email_contato" class="form-control @error('email_contato') is-invalid @enderror" 
                                           value="{{ old('email_contato', $empresa->email_contato) }}">
                                    @error('email_contato')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror" 
                                           value="{{ old('telefone', $empresa->telefone) }}">
                                    @error('telefone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                        <option value="ativa" {{ old('status', $empresa->status) == 'ativa' ? 'selected' : '' }}>Ativa</option>
                                        <option value="suspensa" {{ old('status', $empresa->status) == 'suspensa' ? 'selected' : '' }}>Suspensa</option>
                                        <option value="cancelada" {{ old('status', $empresa->status) == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Plano <span class="text-danger">*</span></label>
                                    <select name="plano" class="form-select @error('plano') is-invalid @enderror" required>
                                        <option value="basico" {{ old('plano', $empresa->plano) == 'basico' ? 'selected' : '' }}>Básico</option>
                                        <option value="profissional" {{ old('plano', $empresa->plano) == 'profissional' ? 'selected' : '' }}>Profissional</option>
                                        <option value="enterprise" {{ old('plano', $empresa->plano) == 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                                    </select>
                                    @error('plano')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data de Ativação</label>
                                    <input type="date" name="data_ativacao" class="form-control @error('data_ativacao') is-invalid @enderror" 
                                           value="{{ old('data_ativacao', $empresa->data_ativacao?->format('Y-m-d')) }}">
                                    @error('data_ativacao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data de Expiração</label>
                                    <input type="date" name="data_expiracao" class="form-control @error('data_expiracao') is-invalid @enderror" 
                                           value="{{ old('data_expiracao', $empresa->data_expiracao?->format('Y-m-d')) }}">
                                    @error('data_expiracao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3">Configurações da Empresa</h5>

                            @php
                                $config = $empresa->configuracoes ?? [];
                                $operacoes = $config['operacoes'] ?? [];
                            @endphp

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="permite_multiplas_operacoes" id="permite_multiplas_operacoes" 
                                               value="1" {{ old('permite_multiplas_operacoes', $operacoes['permite_multiplas_operacoes'] ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="permite_multiplas_operacoes">
                                            <strong>Permitir Múltiplas Operações</strong>
                                        </label>
                                        <small class="d-block text-muted">
                                            Se desmarcado, a empresa terá apenas uma operação
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bx bx-info-circle"></i> 
                                <strong>Nota:</strong> As configurações de aprovação e liberação de empréstimos são definidas por operação, não por empresa. 
                                Configure essas opções ao criar ou editar cada operação.
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('super-admin.empresas.show', $empresa->id) }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Salvar
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
