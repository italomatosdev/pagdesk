@extends('layouts.master-without-nav')
@section('title')
    Cadastro de cliente
@endsection
@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">Preencha seus dados</h4>
                    </div>
                    <div class="card-body">
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('cadastro-cliente.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="ref" value="{{ $ref }}">

                            <div class="mb-3">
                                <label class="form-label">Tipo de Pessoa <span class="text-danger">*</span></label>
                                <select name="tipo_pessoa" id="tipo_pessoa" class="form-select" required>
                                    <option value="fisica" {{ old('tipo_pessoa', 'fisica') == 'fisica' ? 'selected' : '' }}>Pessoa Física</option>
                                    <option value="juridica" {{ old('tipo_pessoa') == 'juridica' ? 'selected' : '' }}>Pessoa Jurídica</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" id="label-documento">CPF <span class="text-danger">*</span></label>
                                <input type="text" name="documento" id="documento" class="form-control"
                                       placeholder="000.000.000-00" value="{{ old('documento') }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" name="nome" id="nome" class="form-control" value="{{ old('nome') }}" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telefone (WhatsApp) <span class="text-danger">*</span></label>
                                    <input type="text" name="telefone" id="telefone" class="form-control"
                                           placeholder="(00) 00000-0000" value="{{ old('telefone') }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">E-mail</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                                </div>
                            </div>

                            <div class="mb-3" id="campo-data-nascimento">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" id="data_nascimento" class="form-control"
                                       value="{{ old('data_nascimento') }}">
                            </div>

                            <div id="campos-responsavel" style="display: none;">
                                <hr class="my-4">
                                <h5 class="mb-3">Responsável Legal</h5>
                                <div class="mb-3">
                                    <label class="form-label">Nome do Responsável</label>
                                    <input type="text" name="responsavel_nome" class="form-control"
                                           value="{{ old('responsavel_nome') }}" placeholder="Nome completo do responsável">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CPF do Responsável</label>
                                        <input type="text" name="responsavel_cpf" id="responsavel_cpf" class="form-control"
                                               placeholder="000.000.000-00" value="{{ old('responsavel_cpf') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">RG do Responsável</label>
                                        <input type="text" name="responsavel_rg" class="form-control" value="{{ old('responsavel_rg') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CNH do Responsável</label>
                                        <input type="text" name="responsavel_cnh" class="form-control" value="{{ old('responsavel_cnh') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cargo/Função</label>
                                        <input type="text" name="responsavel_cargo" class="form-control"
                                               placeholder="Ex: Diretor, Sócio" value="{{ old('responsavel_cargo') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">CEP <span class="text-danger">*</span></label>
                                    <input type="text" name="cep" id="cep" class="form-control"
                                           placeholder="00000-000" value="{{ old('cep') }}" required>
                                    <small class="text-muted">Digite o CEP e saia do campo para buscar o endereço</small>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Endereço <span class="text-danger">*</span></label>
                                    <input type="text" name="endereco" id="endereco" class="form-control" value="{{ old('endereco') }}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Número <span class="text-danger">*</span></label>
                                    <input type="text" name="numero" class="form-control" value="{{ old('numero') }}" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cidade <span class="text-danger">*</span></label>
                                    <input type="text" name="cidade" id="cidade" class="form-control" value="{{ old('cidade') }}" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Estado <span class="text-danger">*</span></label>
                                    <input type="text" name="estado" id="estado" class="form-control" placeholder="SP" maxlength="2"
                                           value="{{ old('estado') }}" required>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3">Documentos</h5>
                            <p class="text-muted small mb-2">Campos com <span class="text-danger">*</span> são obrigatórios para esta operação.</p>

                            @php
                                $docObrig = in_array('documento_cliente', $documentosObrigatorios ?? []);
                                $selfieObrig = in_array('selfie_documento', $documentosObrigatorios ?? []);
                            @endphp
                            <div class="mb-3">
                                <label class="form-label">Documento do Cliente (RG/CNH) @if($docObrig)<span class="text-danger">*</span>@endif</label>
                                <input type="file" name="documento_cliente" class="form-control" accept=".pdf,.jpg,.jpeg,.png"
                                       @if($docObrig) required @endif>
                                <small class="text-muted">PDF, JPG ou PNG (máx. 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Selfie com Documento @if($selfieObrig)<span class="text-danger">*</span>@endif</label>
                                <input type="file" name="selfie_documento" class="form-control" accept=".jpg,.jpeg,.png"
                                       @if($selfieObrig) required @endif>
                                <small class="text-muted">Foto segurando o documento. JPG ou PNG (máx. 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Anexos adicionais (opcional)</label>
                                <input type="file" name="anexos[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-check"></i> Enviar cadastro
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            function init() {
            var tipoPessoa = document.getElementById('tipo_pessoa');
            var labelDoc = document.getElementById('label-documento');
            var docInput = document.getElementById('documento');
            var campoDataNasc = document.getElementById('campo-data-nascimento');
            var camposResponsavel = document.getElementById('campos-responsavel');
            var responsavelCpf = document.getElementById('responsavel_cpf');

            function atualizarTipoPessoa() {
                if (!tipoPessoa || !labelDoc || !docInput) return;
                var v = tipoPessoa.value;
                if (v === 'fisica') {
                    labelDoc.innerHTML = 'CPF <span class="text-danger">*</span>';
                    docInput.placeholder = '000.000.000-00';
                    docInput.maxLength = 14;
                    if (campoDataNasc) campoDataNasc.style.display = '';
                    if (camposResponsavel) camposResponsavel.style.display = 'none';
                } else {
                    labelDoc.innerHTML = 'CNPJ <span class="text-danger">*</span>';
                    docInput.placeholder = '00.000.000/0000-00';
                    docInput.maxLength = 18;
                    if (campoDataNasc) campoDataNasc.style.display = 'none';
                    if (camposResponsavel) camposResponsavel.style.display = '';
                }
            }

            if (tipoPessoa) {
                tipoPessoa.addEventListener('change', atualizarTipoPessoa);
                atualizarTipoPessoa();
            }

            // Máscara CPF/CNPJ no documento
            if (docInput) {
                docInput.addEventListener('input', function (e) {
                    var v = e.target.value.replace(/\D/g, '');
                    var tipo = tipoPessoa ? tipoPessoa.value : 'fisica';
                    if (tipo === 'fisica') {
                        if (v.length > 11) v = v.slice(0, 11);
                        if (v.length > 9)
                            e.target.value = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
                        else if (v.length > 6)
                            e.target.value = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
                        else if (v.length > 3)
                            e.target.value = v.replace(/(\d{3})(\d{0,3})/, '$1.$2');
                        else
                            e.target.value = v;
                    } else {
                        if (v.length > 14) v = v.slice(0, 14);
                        if (v.length > 12)
                            e.target.value = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
                        else if (v.length > 8)
                            e.target.value = v.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
                        else if (v.length > 5)
                            e.target.value = v.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
                        else if (v.length > 2)
                            e.target.value = v.replace(/(\d{2})(\d{0,3})/, '$1.$2');
                        else
                            e.target.value = v;
                    }
                });
            }

            // Máscara telefone (fixo ou celular)
            var telInput = document.getElementById('telefone');
            if (telInput) {
                telInput.addEventListener('input', function (e) {
                    var v = e.target.value.replace(/\D/g, '');
                    if (v.length > 11) v = v.slice(0, 11);
                    if (v.length <= 10) {
                        if (v.length > 6)
                            e.target.value = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                        else if (v.length > 2)
                            e.target.value = v.replace(/(\d{2})(\d{0,4})/, '($1) $2');
                        else
                            e.target.value = v;
                    } else {
                        e.target.value = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                    }
                });
            }

            // Máscara CEP e autocomplete ViaCEP
            var cepInput = document.getElementById('cep');
            if (cepInput) {
                cepInput.addEventListener('input', function (e) {
                    var v = e.target.value.replace(/\D/g, '');
                    if (v.length > 8) v = v.slice(0, 8);
                    if (v.length > 5)
                        e.target.value = v.replace(/(\d{5})(\d{0,3})/, '$1-$2');
                    else
                        e.target.value = v;
                });
                cepInput.addEventListener('blur', function () {
                    var cep = cepInput.value.replace(/\D/g, '');
                    if (cep.length !== 8) return;
                    var enderecoInput = document.getElementById('endereco');
                    var cidadeInput = document.getElementById('cidade');
                    var estadoInput = document.getElementById('estado');
                    fetch('https://viacep.com.br/ws/' + cep + '/json/')
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.erro) {
                                if (typeof alert !== 'undefined') alert('CEP não encontrado. Verifique o número.');
                                return;
                            }
                            if (enderecoInput && data.logradouro) enderecoInput.value = data.logradouro;
                            if (cidadeInput && data.localidade) cidadeInput.value = data.localidade;
                            if (estadoInput && data.uf) estadoInput.value = data.uf;
                        })
                        .catch(function () {
                            if (typeof alert !== 'undefined') alert('Não foi possível consultar o CEP. Tente novamente.');
                        });
                });
            }

            // Máscara CPF do responsável
            if (responsavelCpf) {
                responsavelCpf.addEventListener('input', function (e) {
                    var v = e.target.value.replace(/\D/g, '');
                    if (v.length > 11) v = v.slice(0, 11);
                    if (v.length > 9)
                        e.target.value = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
                    else if (v.length > 6)
                        e.target.value = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
                    else if (v.length > 3)
                        e.target.value = v.replace(/(\d{3})(\d{0,3})/, '$1.$2');
                    else
                        e.target.value = v;
                });
            }
            } // fim init()
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endsection
