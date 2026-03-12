@extends('layouts.master')
@section('title')
    Editar Cliente
@endsection
@section('page-title')
    Editar Cliente: {{ $cliente->nome }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h4 class="card-title mb-0">Editar Cliente</h4>
                            @if(($isSuperAdmin ?? false) && $cliente->empresa)
                                <small class="text-muted">
                                    <i class="bx bx-building"></i> Empresa: <strong>{{ $cliente->empresa->nome }}</strong>
                                </small>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if(!($isEmpresaCriadora ?? true) && !($isSuperAdmin ?? false))
                            <div class="alert alert-info mb-3">
                                <h6 class="alert-heading">
                                    <i class="bx bx-info-circle me-2"></i>Cliente de Outra Empresa
                                </h6>
                                <p class="mb-0">
                                    Este cliente foi cadastrado por <strong>{{ $cliente->empresa->nome ?? 'outra empresa' }}</strong>. 
                                    As alterações que você fizer serão salvas apenas para sua empresa e não afetarão os dados originais.
                                </p>
                            </div>
                        @endif
                        
                        <form action="{{ route('clientes.update', $cliente->id) }}" method="POST" enctype="multipart/form-data" class="form-editar-cliente" id="form-editar-cliente" data-no-loading>
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">{{ $cliente->isPessoaFisica() ? 'CPF' : 'CNPJ' }}</label>
                                <input type="text" class="form-control" 
                                       value="{{ $cliente->documento_formatado }}" disabled>
                                <small class="text-muted">{{ $cliente->isPessoaFisica() ? 'CPF' : 'CNPJ' }} não pode ser alterado</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control" 
                                       value="{{ old('nome', $cliente->nome) }}" required>
                                @error('nome')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" id="telefone" class="form-control" 
                                           value="{{ old('telefone', $cliente->telefone) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="{{ old('email', $cliente->email) }}">
                                </div>
                            </div>

                            <div class="mb-3" id="campo-data-nascimento" style="{{ $cliente->isPessoaJuridica() ? 'display: none;' : '' }}">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" id="data_nascimento" class="form-control" 
                                       value="{{ old('data_nascimento', $cliente->data_nascimento?->format('Y-m-d')) }}">
                            </div>

                            <div id="campos-responsavel" style="{{ $cliente->isPessoaFisica() ? 'display: none;' : '' }}">
                                <hr class="my-4">
                                <h5 class="mb-3">Responsável Legal</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nome do Responsável</label>
                                    <input type="text" name="responsavel_nome" id="responsavel_nome" class="form-control"
                                           value="{{ old('responsavel_nome', $cliente->responsavel_nome) }}" placeholder="Nome completo do responsável">
                                    @error('responsavel_nome')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CPF do Responsável</label>
                                        <input type="text" name="responsavel_cpf" id="responsavel_cpf" class="form-control"
                                               placeholder="000.000.000-00"
                                               value="{{ old('responsavel_cpf', $cliente->responsavel_cpf_formatado) }}">
                                        @error('responsavel_cpf')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">RG do Responsável</label>
                                        <input type="text" name="responsavel_rg" id="responsavel_rg" class="form-control"
                                               value="{{ old('responsavel_rg', $cliente->responsavel_rg) }}">
                                        @error('responsavel_rg')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CNH do Responsável</label>
                                        <input type="text" name="responsavel_cnh" id="responsavel_cnh" class="form-control"
                                               value="{{ old('responsavel_cnh', $cliente->responsavel_cnh) }}">
                                        @error('responsavel_cnh')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cargo/Função</label>
                                        <input type="text" name="responsavel_cargo" id="responsavel_cargo" class="form-control"
                                               placeholder="Ex: Diretor, Sócio, Representante Legal"
                                               value="{{ old('responsavel_cargo', $cliente->responsavel_cargo) }}">
                                        @error('responsavel_cargo')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">CEP</label>
                                    <input type="text" name="cep" id="cep" class="form-control" 
                                           value="{{ old('cep', $cliente->cep) }}">
                                    <small class="text-muted">Digite o CEP e saia do campo para buscar o endereço</small>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Endereço</label>
                                    <input type="text" name="endereco" id="endereco" class="form-control" 
                                           value="{{ old('endereco', $cliente->endereco) }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Número</label>
                                    <input type="text" name="numero" id="numero" class="form-control" 
                                           value="{{ old('numero', $cliente->numero) }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" name="cidade" id="cidade" class="form-control" 
                                           value="{{ old('cidade', $cliente->cidade) }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Estado</label>
                                    <input type="text" name="estado" id="estado" class="form-control" 
                                           maxlength="2" 
                                           value="{{ old('estado', $cliente->estado) }}">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes', $cliente->observacoes) }}</textarea>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3">Documentos do Cliente</h5>

                            @php
                                $documento = $cliente->getDocumentoPorCategoria('documento');
                                $selfie = $cliente->getDocumentoPorCategoria('selfie');
                                // Para anexos, mostra todos (originais + específicos da empresa)
                                $anexos = $cliente->documentos->where('categoria', 'anexo');
                            @endphp

                            <div class="mb-3">
                                <label class="form-label">Documento do Cliente (RG/CNH)</label>
                                @if($documento)
                                    <div class="mb-2">
                                        <a href="{{ $documento->url }}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="bx bx-download"></i> Ver Documento Atual
                                        </a>
                                        <small class="text-muted d-block mt-1">
                                            {{ $documento->nome_arquivo ?? 'Documento' }} - 
                                            Enviado em: {{ $documento->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </div>
                                @else
                                    <div class="alert alert-warning mb-2">
                                        <small>Nenhum documento anexado. Por favor, anexe um documento.</small>
                                    </div>
                                @endif
                                <input type="file" name="documento_cliente" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Deixe em branco para manter o documento atual. Formatos: PDF, JPG, PNG (máx. 5MB)</small>
                                @error('documento_cliente')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Selfie com Documento</label>
                                @if($selfie)
                                    <div class="mb-2">
                                        <a href="{{ $selfie->url }}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="bx bx-download"></i> Ver Selfie Atual
                                        </a>
                                        <small class="text-muted d-block mt-1">
                                            {{ $selfie->nome_arquivo ?? 'Selfie' }} - 
                                            Enviado em: {{ $selfie->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </div>
                                @else
                                    <div class="alert alert-warning mb-2">
                                        <small>Nenhuma selfie anexada. Por favor, anexe uma selfie.</small>
                                    </div>
                                @endif
                                <input type="file" name="selfie_documento" class="form-control" accept=".jpg,.jpeg,.png">
                                <small class="text-muted">Deixe em branco para manter a selfie atual. Formatos: JPG, PNG (máx. 5MB)</small>
                                @error('selfie_documento')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            @if($anexos->count() > 0)
                                <div class="mb-3">
                                    <label class="form-label">Anexos Existentes ({{ $anexos->count() }})</label>
                                    <div class="mb-2">
                                        @foreach($anexos as $anexo)
                                            <div class="mb-2">
                                                <a href="{{ $anexo->url }}" target="_blank" class="btn btn-sm btn-secondary">
                                                    <i class="bx bx-download"></i> {{ $anexo->nome_arquivo ?? 'Ver Anexo' }}
                                                </a>
                                                <small class="text-muted d-block mt-1">
                                                    Enviado em: {{ $anexo->created_at->format('d/m/Y H:i') }}
                                                </small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Adicionar Novos Anexos (opcional)</label>
                                <input type="file" name="anexos[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                <small class="text-muted">Você pode selecionar múltiplos arquivos. Formatos: PDF, JPG, PNG (máx. 5MB cada)</small>
                                @error('anexos.*')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('clientes.show', $cliente->id) }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-check"></i> Atualizar Cliente
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
            document.addEventListener('DOMContentLoaded', function() {
                // Máscara de CPF do responsável
                const responsavelCpfInput = document.getElementById('responsavel_cpf');
                if (responsavelCpfInput) {
                    responsavelCpfInput.addEventListener('input', function (e) {
                        let v = e.target.value.replace(/\D/g, '');
                        if (v.length > 11) v = v.slice(0, 11);
                        if (v.length > 9) {
                            e.target.value = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
                        } else if (v.length > 6) {
                            e.target.value = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
                        } else if (v.length > 3) {
                            e.target.value = v.replace(/(\d{3})(\d{0,3})/, '$1.$2');
                        } else {
                            e.target.value = v;
                        }
                    });
                }

                // Máscara de telefone (fixo/celular)
                const telInput = document.getElementById('telefone');
                if (telInput) {
                    telInput.addEventListener('input', function (e) {
                        let v = e.target.value.replace(/\D/g, '');
                        if (v.length > 11) v = v.slice(0, 11);

                        if (v.length <= 10) {
                            if (v.length > 6) {
                                e.target.value = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                            } else if (v.length > 2) {
                                e.target.value = v.replace(/(\d{2})(\d{0,4})/, '($1) $2');
                            } else {
                                e.target.value = v;
                            }
                        } else {
                            e.target.value = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                        }
                    });
                }

                // Máscara e busca de CEP (ViaCEP)
                const cepInput = document.getElementById('cep');
                if (cepInput) {
                    cepInput.addEventListener('input', function (e) {
                        let v = e.target.value.replace(/\D/g, '');
                        if (v.length > 8) v = v.slice(0, 8);
                        if (v.length > 5) {
                            e.target.value = v.replace(/(\d{5})(\d{0,3})/, '$1-$2');
                        } else {
                            e.target.value = v;
                        }
                    });

                    cepInput.addEventListener('blur', function () {
                        let cep = cepInput.value.replace(/\D/g, '');
                        if (cep.length !== 8) {
                            return;
                        }

                        const enderecoInput = document.getElementById('endereco');
                        const cidadeInput = document.getElementById('cidade');
                        const estadoInput = document.getElementById('estado');

                        // Mostrar loading no campo CEP
                        FieldLoading.show(cepInput);

                        fetch(`https://viacep.com.br/ws/${cep}/json/`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.erro) {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'CEP não encontrado',
                                        text: 'Verifique o CEP informado.',
                                        confirmButtonColor: '#038edc'
                                    });
                                    return;
                                }

                                if (enderecoInput && data.logradouro) {
                                    enderecoInput.value = data.logradouro;
                                }
                                if (cidadeInput && data.localidade) {
                                    cidadeInput.value = data.localidade;
                                }
                                if (estadoInput && data.uf) {
                                    estadoInput.value = data.uf;
                                }
                            })
                            .catch(() => {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro ao buscar CEP',
                                    text: 'Não foi possível consultar o CEP no momento.',
                                    confirmButtonColor: '#038edc'
                                });
                            })
                            .finally(() => {
                                // Esconder loading no campo CEP
                                FieldLoading.hide(cepInput);
                            });
                    });
                }

                document.querySelectorAll('.form-editar-cliente').forEach(form => {
                    const submitHandler = function(e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        
                        const nome = this.querySelector('input[name="nome"]').value.trim();
                        
                        if (!nome) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, preencha o nome do cliente.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        Swal.fire({
                            title: 'Salvar Alterações?',
                            html: `Deseja salvar as alterações no cliente <strong>${nome}</strong>?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, salvar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.removeEventListener('submit', submitHandler, true);
                                form.removeAttribute('data-no-loading');
                                form.submit();
                            }
                        });
                    };
                    
                    form.addEventListener('submit', submitHandler, true);
                });
            });
        </script>
    @endsection