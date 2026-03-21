@extends('layouts.master')
@section('title')
    Novo Cliente
@endsection
@section('page-title')
    Novo Cliente
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Cadastrar Novo Cliente</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('clientes.store') }}" method="POST" enctype="multipart/form-data" class="form-criar-cliente" data-no-loading>
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Operação <span class="text-danger">*</span></label>
                                <select name="operacao_id" id="operacao_id" class="form-select" required>
                                    <option value="">Selecione a operação...</option>
                                    @foreach($operacoes ?? [] as $op)
                                        <option value="{{ $op->id }}" {{ old('operacao_id', $operacaoSelecionadaId ?? '') == $op->id ? 'selected' : '' }}>
                                            {{ $op->nome }}@if($op->codigo) ({{ $op->codigo }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Os documentos obrigatórios dependem da operação selecionada.</small>
                                @error('operacao_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Pessoa <span class="text-danger">*</span></label>
                                <select name="tipo_pessoa" id="tipo_pessoa" class="form-select" required>
                                    <option value="fisica" {{ old('tipo_pessoa', 'fisica') == 'fisica' ? 'selected' : '' }}>Pessoa Física</option>
                                    <option value="juridica" {{ old('tipo_pessoa') == 'juridica' ? 'selected' : '' }}>Pessoa Jurídica</option>
                                </select>
                                @error('tipo_pessoa')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" id="label-documento">CPF <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="documento" id="documento" class="form-control"
                                           placeholder="000.000.000-00"
                                           value="{{ old('documento') }}" required>
                                    <button type="button" class="btn btn-outline-primary" id="btn-verificar-documento">
                                        <i class="bx bx-search"></i> Verificar
                                    </button>
                                </div>
                                <small class="text-muted" id="help-documento">Digite o CPF para verificar se já existe cadastro</small>
                                @error('documento')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div id="cliente-dados" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" id="nome" class="form-control"
                                           value="{{ old('nome') }}">
                                    @error('nome')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" name="telefone" id="telefone" class="form-control"
                                               placeholder="(00) 00000-0000"
                                               value="{{ old('telefone') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control"
                                               value="{{ old('email') }}">
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
                                        <input type="text" name="responsavel_nome" id="responsavel_nome" class="form-control"
                                               value="{{ old('responsavel_nome') }}" placeholder="Nome completo do responsável">
                                        @error('responsavel_nome')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">CPF do Responsável</label>
                                            <input type="text" name="responsavel_cpf" id="responsavel_cpf" class="form-control"
                                                   placeholder="000.000.000-00"
                                                   value="{{ old('responsavel_cpf') }}">
                                            @error('responsavel_cpf')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">RG do Responsável</label>
                                            <input type="text" name="responsavel_rg" id="responsavel_rg" class="form-control"
                                                   value="{{ old('responsavel_rg') }}">
                                            @error('responsavel_rg')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">CNH do Responsável</label>
                                            <input type="text" name="responsavel_cnh" id="responsavel_cnh" class="form-control"
                                                   value="{{ old('responsavel_cnh') }}">
                                            @error('responsavel_cnh')
                                                <div class="text-danger">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Cargo/Função</label>
                                            <input type="text" name="responsavel_cargo" id="responsavel_cargo" class="form-control"
                                                   placeholder="Ex: Diretor, Sócio, Representante Legal"
                                                   value="{{ old('responsavel_cargo') }}">
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
                                               placeholder="00000-000"
                                               value="{{ old('cep') }}">
                                        <small class="text-muted">Digite o CEP e saia do campo para buscar o endereço</small>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Endereço</label>
                                        <input type="text" name="endereco" id="endereco" class="form-control"
                                               value="{{ old('endereco') }}">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Número</label>
                                        <input type="text" name="numero" id="numero" class="form-control"
                                               value="{{ old('numero') }}">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" id="cidade" class="form-control"
                                               value="{{ old('cidade') }}">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Estado</label>
                                        <input type="text" name="estado" id="estado" class="form-control"
                                               placeholder="SP" maxlength="2"
                                               value="{{ old('estado') }}">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Observações</label>
                                    <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes') }}</textarea>
                                </div>

                                <hr class="my-4">
                                <h5 class="mb-3">Documentos do Cliente</h5>
                                <p class="text-muted small mb-2">Campos marcados com <span class="text-danger">*</span> são obrigatórios para a operação selecionada.</p>

                                <div class="mb-3" id="wrap-documento-cliente">
                                    <label class="form-label" id="label-documento-cliente">Documento do Cliente (RG/CNH)</label>
                                    <input type="file" name="documento_cliente" id="input-documento-cliente" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Formatos aceitos: PDF, JPG, PNG (máx. 5MB)</small>
                                    @error('documento_cliente')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3" id="wrap-selfie-documento">
                                    <label class="form-label" id="label-selfie-documento">Selfie com Documento</label>
                                    <input type="file" name="selfie_documento" id="input-selfie-documento" class="form-control" accept=".jpg,.jpeg,.png">
                                    <small class="text-muted">Foto do cliente segurando o documento. Formatos: JPG, PNG (máx. 5MB)</small>
                                    @error('selfie_documento')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Anexos Adicionais (opcional)</label>
                                    <input type="file" name="anexos[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                    <small class="text-muted">Você pode selecionar múltiplos arquivos. Formatos: PDF, JPG, PNG (máx. 5MB cada)</small>
                                    @error('anexos.*')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary" id="btn-salvar-cliente" style="display:none;">
                                    <i class="bx bx-check"></i> Cadastrar Cliente
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
            window.documentObrigatoriosPorOperacao = @json($documentObrigatoriosPorOperacao ?? []);

            document.addEventListener('DOMContentLoaded', function() {
                const dadosContainer = document.getElementById('cliente-dados');
                const btnVerificarDocumento = document.getElementById('btn-verificar-documento');
                const btnSalvar = document.getElementById('btn-salvar-cliente');
                const documentoInput = document.getElementById('documento');
                const tipoPessoaSelect = document.getElementById('tipo_pessoa');
                const labelDocumento = document.getElementById('label-documento');
                const helpDocumento = document.getElementById('help-documento');
                const operacaoSelect = document.getElementById('operacao_id');
                const docInput = document.getElementById('input-documento-cliente') || document.querySelector('input[name="documento_cliente"]');
                const selfieInput = document.getElementById('input-selfie-documento') || document.querySelector('input[name="selfie_documento"]');
                const labelDocCliente = document.getElementById('label-documento-cliente');
                const labelSelfieDoc = document.getElementById('label-selfie-documento');
                const nomeInput = document.getElementById('nome');

                function updateDocumentosObrigatorios() {
                    const operacaoId = operacaoSelect ? operacaoSelect.value : '';
                    const docs = (window.documentObrigatoriosPorOperacao && operacaoId) ? (window.documentObrigatoriosPorOperacao[operacaoId] || []) : [];
                    const docObrig = docs.indexOf('documento_cliente') !== -1;
                    const selfieObrig = docs.indexOf('selfie_documento') !== -1;
                    if (docInput) {
                        docInput.required = docObrig;
                        docInput.removeAttribute('required');
                        if (docObrig) docInput.setAttribute('required', 'required');
                    }
                    if (selfieInput) {
                        selfieInput.required = selfieObrig;
                        selfieInput.removeAttribute('required');
                        if (selfieObrig) selfieInput.setAttribute('required', 'required');
                    }
                    if (labelDocCliente) {
                        labelDocCliente.innerHTML = 'Documento do Cliente (RG/CNH)' + (docObrig ? ' <span class="text-danger">*</span>' : '');
                    }
                    if (labelSelfieDoc) {
                        labelSelfieDoc.innerHTML = 'Selfie com Documento' + (selfieObrig ? ' <span class="text-danger">*</span>' : '');
                    }
                }

                if (operacaoSelect) {
                    operacaoSelect.addEventListener('change', updateDocumentosObrigatorios);
                    updateDocumentosObrigatorios();
                }

                function setEtapa2Ativa(ativa) {
                    if (!dadosContainer || !btnSalvar || !nomeInput) return;

                    dadosContainer.style.display = ativa ? '' : 'none';
                    btnSalvar.style.display = ativa ? '' : 'none';

                    nomeInput.required = ativa;
                    if (ativa) updateDocumentosObrigatorios();
                }

                // Se voltou com old('nome') (erro de validação), já abre a etapa 2
                const jaTemNome = !!(nomeInput && nomeInput.value && nomeInput.value.trim().length > 0);
                setEtapa2Ativa(jaTemNome);

                // Atualizar label e placeholder conforme tipo de pessoa
                function atualizarCamposDocumento() {
                    if (!tipoPessoaSelect || !documentoInput || !labelDocumento || !helpDocumento) return;
                    
                    const tipoPessoa = tipoPessoaSelect.value;
                    const campoDataNascimento = document.getElementById('campo-data-nascimento');
                    const inputDataNascimento = document.getElementById('data_nascimento');
                    const camposResponsavel = document.getElementById('campos-responsavel');
                    
                    if (tipoPessoa === 'fisica') {
                        labelDocumento.innerHTML = 'CPF <span class="text-danger">*</span>';
                        documentoInput.placeholder = '000.000.000-00';
                        helpDocumento.textContent = 'Digite o CPF para verificar se já existe cadastro';
                        documentoInput.maxLength = 14;
                        
                        // Mostrar campo de data de nascimento
                        if (campoDataNascimento) {
                            campoDataNascimento.style.display = '';
                        }
                        if (inputDataNascimento) {
                            inputDataNascimento.required = false; // Opcional
                        }
                        
                        // Ocultar campos do responsável
                        if (camposResponsavel) {
                            camposResponsavel.style.display = 'none';
                        }
                    } else {
                        labelDocumento.innerHTML = 'CNPJ <span class="text-danger">*</span>';
                        documentoInput.placeholder = '00.000.000/0000-00';
                        helpDocumento.textContent = 'Digite o CNPJ para verificar se já existe cadastro';
                        documentoInput.maxLength = 18;
                        
                        // Ocultar campo de data de nascimento
                        if (campoDataNascimento) {
                            campoDataNascimento.style.display = 'none';
                        }
                        if (inputDataNascimento) {
                            inputDataNascimento.value = ''; // Limpar valor
                            inputDataNascimento.required = false;
                        }
                        
                        // Mostrar campos do responsável
                        if (camposResponsavel) {
                            camposResponsavel.style.display = '';
                        }
                    }
                    
                    // Limpar campo ao mudar tipo
                    documentoInput.value = '';
                }

                // Listener para mudança de tipo de pessoa
                if (tipoPessoaSelect) {
                    tipoPessoaSelect.addEventListener('change', atualizarCamposDocumento);
                    atualizarCamposDocumento(); // Inicializar
                }

                // Máscara dinâmica de CPF ou CNPJ
                if (documentoInput) {
                    documentoInput.addEventListener('input', function (e) {
                        const tipoPessoa = tipoPessoaSelect?.value || 'fisica';
                        let v = e.target.value.replace(/\D/g, '');
                        
                        if (tipoPessoa === 'fisica') {
                            // CPF: 11 dígitos
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
                        } else {
                            // CNPJ: 14 dígitos
                            if (v.length > 14) v = v.slice(0, 14);
                            if (v.length > 12) {
                                e.target.value = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
                            } else if (v.length > 8) {
                                e.target.value = v.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
                            } else if (v.length > 5) {
                                e.target.value = v.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
                            } else if (v.length > 2) {
                                e.target.value = v.replace(/(\d{2})(\d{0,3})/, '$1.$2');
                            } else {
                                e.target.value = v;
                            }
                        }
                    });
                }

                async function verificarDocumento() {
                    const tipoPessoa = tipoPessoaSelect?.value || 'fisica';
                    const documento = (documentoInput?.value || '').replace(/\D/g, '');
                    
                    if (tipoPessoa === 'fisica') {
                        if (documento.length !== 11) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'CPF inválido',
                                text: 'Informe um CPF com 11 dígitos.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                    } else {
                        if (documento.length !== 14) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'CNPJ inválido',
                                text: 'Informe um CNPJ com 14 dígitos.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                    }

                    // Consultar backend
                    const url = `{{ route('clientes.buscar.cpf') }}?cpf=${documento}`;

                    try {
                        // Mostrar loading no botão
                        ButtonLoading.show(btnVerificarDocumento, 'Verificando...');

                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        
                        if (!res.ok) {
                            throw new Error(`Erro HTTP: ${res.status}`);
                        }
                        
                        const data = await res.json();
                        
                        if (data.error) {
                            throw new Error(data.error);
                        }

                        // Se é consulta cruzada (cliente de outra empresa)
                        if (data?.existe && data?.consulta_cruzada) {
                            const cliente = data.cliente;
                            const ficha = data.ficha || {};

                            const fmtMoeda = (v) => {
                                const n = Number(v || 0);
                                return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                            };

                            // Empréstimos ativos por operação
                            const ativosPorOperacaoHtml = (ficha.ativos_por_operacao || [])
                                .filter(item => Number(item.total_ativo || 0) > 0)
                                .map(item => {
                                    return `
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div class="flex-grow-1 text-start">
                                                <div class="fw-semibold small mb-1">${item.operacao || '-'}</div>
                                                <div class="text-muted small">
                                                    ${item.qtd || 0} empréstimo(s)
                                                </div>
                                            </div>
                                            <div class="fw-bold text-primary text-start">${fmtMoeda(item.total_ativo || 0)}</div>
                                        </div>
                                    `;
                                })
                                .join('') || `<div class="text-center text-muted py-3 small">Nenhum empréstimo ativo</div>`;

                            // Pendências por operação
                            const pendenciasPorOperacaoHtml = (ficha.pendencias_por_operacao || [])
                                .filter(item => Number(item.total_em_aberto || 0) > 0)
                                .map(item => {
                                    const empresaBadge = item.empresa && item.empresa !== item.operacao 
                                        ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size: 10px;">${item.empresa}</span>`
                                        : '';
                                    const atrasadasInfo = (item.atrasadas_qtd || 0) > 0 
                                        ? `<span class="text-danger small">${item.atrasadas_qtd} atrasada(s): ${fmtMoeda(item.atrasadas_total || 0)}</span>`
                                        : '';
                                    return `
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div class="flex-grow-1 text-start">
                                                <div class="fw-semibold small mb-1 d-flex align-items-center text-start">
                                                    ${item.operacao || '-'}
                                                    ${empresaBadge}
                                                </div>
                                                <div class="text-muted small text-start">
                                                    ${atrasadasInfo}
                                                </div>
                                            </div>
                                            <div class="fw-bold text-danger text-start">${fmtMoeda(item.total_em_aberto || 0)}</div>
                                        </div>
                                    `;
                                })
                                .join('') || `<div class="text-center text-muted py-3 small">Nenhuma pendência atrasada</div>`;

                            const fichasListCruzada = data.fichas_por_operacao || [];
                            const fichasBlockCruzada = fichasListCruzada.length
                                ? `
                                    <div class="card mb-4 text-start">
                                        <div class="card-header py-2"><h6 class="mb-0 small">Contato por operação (ficha)</h6></div>
                                        <div class="card-body py-2" style="max-height: 140px; overflow-y: auto;">
                                            ${fichasListCruzada.map(f => `
                                                <div class="border-bottom pb-2 mb-2">
                                                    <div class="fw-semibold small">${f.operacao_nome || ('Operação #' + (f.operacao_id || ''))}</div>
                                                    ${f.nome ? `<div class="text-muted small">${f.nome}</div>` : ''}
                                                    ${f.telefone ? `<div class="small"><i class="bx bx-phone"></i> ${f.telefone}</div>` : ''}
                                                    ${f.email ? `<div class="small"><i class="bx bx-envelope"></i> ${f.email}</div>` : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                `
                                : '';

                            const totalAtivos = ficha.emprestimos_ativos_total || 0;
                            const valorTotalAtivos = (ficha.ativos_por_operacao || []).reduce((sum, item) => sum + (Number(item.total_ativo || 0)), 0);
                            
                            let alertaAtivoOutra = '';
                            if (ficha.tem_ativo_em_outra_operacao) {
                                alertaAtivoOutra = `
                                    <div class="alert alert-warning mb-3">
                                        <h6 class="alert-heading">
                                            <i class="bx bx-error-circle me-2"></i>Atenção
                                        </h6>
                                        Este cliente possui <strong>${totalAtivos} empréstimo(s) ativo(s)</strong> 
                                        em outra(s) empresa(s) no valor total de <strong>${fmtMoeda(valorTotalAtivos)}</strong>.
                                    </div>
                                `;
                            }

                            let alertaPendencias = '';
                            const totalPendencias = Number(ficha.pendencias_total_em_aberto || 0);
                            if (totalPendencias > 0) {
                                alertaPendencias = `
                                    <div class="alert alert-danger mb-3">
                                        <h6 class="alert-heading">
                                            <i class="bx bx-error-circle me-2"></i>Pendências Atrasadas
                                        </h6>
                                        Total de <strong>${fmtMoeda(totalPendencias)}</strong> em parcelas vencidas.
                                    </div>
                                `;
                            } else {
                                alertaPendencias = `
                                    <div class="alert alert-success mb-3">
                                        <h6 class="alert-heading">
                                            <i class="bx bx-check-circle me-2"></i>Sem pendências
                                        </h6>
                                        Nenhuma parcela atrasada encontrada.
                                    </div>
                                `;
                            }

                            Swal.fire({
                                icon: 'info',
                                title: 'Cliente encontrado em outra empresa',
                                width: 900,
                                customClass: {
                                    popup: 'text-start',
                                    htmlContainer: 'p-0'
                                },
                                html: `
                                    <div class="p-4">
                                        <!-- Alerta Principal -->
                                        <div class="alert alert-warning mb-4">
                                            <i class="bx bx-info-circle me-2"></i>
                                            <strong>Este CPF já está cadastrado em outra empresa do sistema.</strong>
                                        </div>

                                        <!-- Informações do Cliente -->
                                        <div class="card mb-4">
                                            <div class="card-body text-center">
                                                <h6 class="font-size-15 mb-1">Cliente</h6>
                                                <h4 class="mt-2 mb-1 font-size-22">${cliente.nome}</h4>
                                                <small class="text-muted">
                                                    <i class="bx bx-id-card me-1"></i>
                                                    ${tipoPessoaSelect?.value === 'fisica' ? 'CPF' : 'CNPJ'}: ${documentoInput?.value || ''}
                                                    <span class="badge bg-primary ms-2">ID: ${cliente.id}</span>
                                                </small>
                                            </div>
                                        </div>

                                        ${fichasBlockCruzada}

                                        <!-- Cards de Métricas -->
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-6">
                                                <div class="card h-100">
                                                    <div class="card-body d-flex flex-column">
                                                        <div class="d-flex justify-content-between">
                                                            <div class="text-start">
                                                                <h6 class="font-size-15 mb-0">Empréstimos Ativos</h6>
                                                                <h4 class="mt-3 mb-0 font-size-22">${totalAtivos}</h4>
                                                                ${valorTotalAtivos > 0 ? `<small class="text-muted">Total: ${fmtMoeda(valorTotalAtivos)}</small>` : '<small class="text-muted">Nenhum empréstimo</small>'}
                                                            </div>
                                                            <div class="avatar">
                                                                <div class="avatar-title rounded bg-primary-subtle">
                                                                    <i class="bx bx-wallet font-size-24 mb-0 text-primary"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card h-100">
                                                    <div class="card-body d-flex flex-column">
                                                        <div class="d-flex justify-content-between">
                                                            <div class="text-start">
                                                                <h6 class="font-size-15 mb-0">Pendências Atrasadas</h6>
                                                                <h4 class="mt-3 mb-0 font-size-22">${fmtMoeda(totalPendencias)}</h4>
                                                                <small class="text-muted">${totalPendencias > 0 ? 'Parcelas vencidas' : 'Nenhuma pendência'}</small>
                                                            </div>
                                                            <div class="avatar">
                                                                <div class="avatar-title rounded bg-${totalPendencias > 0 ? 'danger' : 'success'}-subtle">
                                                                    <i class="bx bx-${totalPendencias > 0 ? 'error-circle' : 'check-circle'} font-size-24 mb-0 text-${totalPendencias > 0 ? 'danger' : 'success'}"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Alertas de Status -->
                                        ${alertaAtivoOutra}
                                        ${alertaPendencias}

                                        <!-- Cards de Detalhes -->
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h4 class="card-title mb-0">Empréstimos por Operação</h4>
                                                    </div>
                                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                                        ${ativosPorOperacaoHtml}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h4 class="card-title mb-0">Pendências por Operação</h4>
                                                    </div>
                                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                                        ${pendenciasPorOperacaoHtml}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Instruções -->
                                        <div class="alert alert-info mb-0 text-center">
                                            <div class="small">
                                                <strong>Opções:</strong><br>
                                                <strong>Usar cadastro:</strong> Abre a ficha do cliente no sistema.<br>
                                                <strong>Continuar preenchimento:</strong> Ao enviar o formulário com a operação selecionada, o sistema <strong>vincula</strong> este CPF/CNPJ à operação e grava a ficha da operação (não cria outro cadastro — igual ao link de cadastro).
                                            </div>
                                        </div>
                                    </div>
                                `,
                                showCancelButton: true,
                                showDenyButton: true,
                                confirmButtonText: 'Usar cadastro',
                                denyButtonText: 'Continuar preenchimento',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#038edc',
                                denyButtonColor: '#f1b44c',
                                cancelButtonColor: '#6c757d',
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Usar cadastro existente - redirecionar para vincular
                                    window.location.href = `{{ url('/clientes') }}/${cliente.id}`;
                                } else if (result.isDenied) {
                                    // Preencher dados e enviar: backend vincula à operação (mesmo fluxo do link)
                                    setEtapa2Ativa(true);
                                    nomeInput?.focus();
                                }
                            });
                            return;
                        }

                        // Cliente existe na própria empresa
                        if (data?.existe && data?.cliente?.id) {
                            const cliente = data.cliente;
                            const ficha = data.ficha || {};

                            const fmtMoeda = (v) => {
                                const n = Number(v || 0);
                                return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                            };

                            const chipsOperacoes = (cliente.operation_clients || cliente.operationClients || [])
                                .map(v => v?.operacao?.nome)
                                .filter(Boolean)
                                .map(nome => `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle me-1 mb-1">${nome}</span>`)
                                .join('') || '<span class="text-muted">Nenhuma</span>';

                            const ativosPorOperacaoHtml = (ficha.ativos_por_operacao || [])
                                .map(item => {
                                    const empresaBadge = item.empresa && item.empresa !== item.operacao 
                                        ? `<span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size: 10px;">${item.empresa}</span>`
                                        : '';
                                    return `
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div class="flex-grow-1 text-start">
                                                <div class="fw-semibold small mb-1 d-flex align-items-center">
                                                    ${item.operacao || '-'}
                                                    ${empresaBadge}
                                                </div>
                                                <div class="text-muted small">
                                                    ${item.qtd || 0} empréstimo(s) ativo(s)
                                                </div>
                                            </div>
                                            <div class="fw-bold text-primary text-start">${fmtMoeda(item.total_ativo || 0)}</div>
                                        </div>
                                    `;
                                })
                                .join('') || `<div class="text-center text-muted py-3 small">Nenhum empréstimo ativo</div>`;

                            const pendenciasPorOperacaoHtml = (ficha.pendencias_por_operacao || [])
                                .filter(item => Number(item.total_em_aberto || 0) > 0)
                                .map(item => {
                                    const empresaBadge = item.empresa && item.empresa !== item.operacao 
                                        ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size: 10px;">${item.empresa}</span>`
                                        : '';
                                    const atrasadasInfo = (item.atrasadas_qtd || 0) > 0 
                                        ? `<span class="text-danger small">${item.atrasadas_qtd} atrasada(s): ${fmtMoeda(item.atrasadas_total || 0)}</span>`
                                        : '';
                                    return `
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div class="flex-grow-1 text-start">
                                                <div class="fw-semibold small mb-1 d-flex align-items-center text-start">
                                                    ${item.operacao || '-'}
                                                    ${empresaBadge}
                                                </div>
                                                <div class="text-muted small text-start">
                                                    ${atrasadasInfo}
                                                </div>
                                            </div>
                                            <div class="fw-bold text-danger text-start">${fmtMoeda(item.total_em_aberto || 0)}</div>
                                        </div>
                                    `;
                                })
                                .join('') || `<div class="text-center text-muted py-3 small">Nenhuma pendência atrasada</div>`;

                            const totalAtivos = ficha.emprestimos_ativos_total || 0;
                            const valorTotalAtivos = ficha.emprestimos_ativos_valor_total || (ficha.ativos_por_operacao || []).reduce((sum, item) => sum + (Number(item.total_ativo || 0)), 0);
                            
                            let alertaAtivoOutra = '';
                            if (ficha.tem_ativo_em_outra_empresa) {
                                alertaAtivoOutra = `<div class="alert alert-danger mb-3">
                                    <h6 class="alert-heading">
                                        <i class="bx bx-error-circle me-2"></i>ATENÇÃO
                                    </h6>
                                    Cliente possui <strong>${totalAtivos} empréstimo(s) ativo(s)</strong> 
                                    em <strong>outra(s) empresa(s)</strong> no valor total de <strong>${fmtMoeda(valorTotalAtivos)}</strong>.
                                </div>`;
                            } else if (ficha.tem_ativo_em_outra_operacao) {
                                alertaAtivoOutra = `<div class="alert alert-warning mb-3">
                                    <h6 class="alert-heading">
                                        <i class="bx bx-error-circle me-2"></i>Atenção
                                    </h6>
                                    Cliente possui <strong>${totalAtivos} empréstimo(s) ativo(s)</strong> 
                                    em mais de uma operação no valor total de <strong>${fmtMoeda(valorTotalAtivos)}</strong>.
                                </div>`;
                            } else if (totalAtivos > 0) {
                                alertaAtivoOutra = `<div class="alert alert-info mb-3">
                                    <h6 class="alert-heading">
                                        <i class="bx bx-info-circle me-2"></i>Informação
                                    </h6>
                                    Cliente possui <strong>${totalAtivos} empréstimo(s) ativo(s)</strong> 
                                    no valor total de <strong>${fmtMoeda(valorTotalAtivos)}</strong>.
                                </div>`;
                            } else {
                                alertaAtivoOutra = `<div class="alert alert-success mb-3">
                                    <h6 class="alert-heading">
                                        <i class="bx bx-check-circle me-2"></i>OK
                                    </h6>
                                    Nenhum empréstimo ativo encontrado.
                                </div>`;
                            }

                            const totalPendencias = Number(ficha.pendencias_total_em_aberto || 0);
                            const alertaPendencias = totalPendencias > 0
                                ? `<div class="alert alert-danger mb-3">
                                    <h6 class="alert-heading">
                                        <i class="bx bx-error-circle me-2"></i>PENDÊNCIAS EM ABERTO
                                    </h6>
                                    Total de <strong>${fmtMoeda(totalPendencias)}</strong> em parcelas atrasadas.
                                </div>`
                                : `<div class="alert alert-success mb-3">
                                    <h6 class="alert-heading">
                                        <i class="bx bx-check-circle me-2"></i>OK
                                    </h6>
                                    Nenhuma pendência atrasada encontrada.
                                </div>`;

                            const fichasListMesmaEmpresa = data.fichas_por_operacao || [];
                            const fichasBlockMesmaEmpresa = fichasListMesmaEmpresa.length
                                ? `
                                    <div class="card mb-4 text-start">
                                        <div class="card-header py-2"><h6 class="mb-0 small">Contato por operação (ficha)</h6></div>
                                        <div class="card-body py-2" style="max-height: 140px; overflow-y: auto;">
                                            ${fichasListMesmaEmpresa.map(f => `
                                                <div class="border-bottom pb-2 mb-2">
                                                    <div class="fw-semibold small">${f.operacao_nome || ('Operação #' + (f.operacao_id || ''))}</div>
                                                    ${f.nome ? `<div class="text-muted small">${f.nome}</div>` : ''}
                                                    ${f.telefone ? `<div class="small"><i class="bx bx-phone"></i> ${f.telefone}</div>` : ''}
                                                    ${f.email ? `<div class="small"><i class="bx bx-envelope"></i> ${f.email}</div>` : ''}
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                `
                                : '';

                            Swal.fire({
                                icon: 'info',
                                title: 'Ficha do Cliente',
                                width: 900,
                                customClass: {
                                    popup: 'text-start',
                                    htmlContainer: 'p-0'
                                },
                                html: `
                                    <div class="p-4">
                                        <!-- Informações do Cliente -->
                                        <div class="card mb-4">
                                            <div class="card-body text-center">
                                                <h6 class="font-size-15 mb-1">Cliente</h6>
                                                <h4 class="mt-2 mb-1 font-size-22">${cliente.nome}</h4>
                                                <small class="text-muted">
                                                    <i class="bx bx-id-card me-1"></i>
                                                    ${tipoPessoaSelect?.value === 'fisica' ? 'CPF' : 'CNPJ'}: ${documentoInput?.value || ''}
                                                    <span class="badge bg-primary ms-2">ID: ${cliente.id}</span>
                                                </small>
                                                <div class="mt-2">
                                                    <small class="text-muted">Operações vinculadas:</small>
                                                    <div class="d-flex flex-wrap gap-1 mt-1 justify-content-center">${chipsOperacoes}</div>
                                                </div>
                                            </div>
                                        </div>

                                        ${fichasBlockMesmaEmpresa}

                                        <!-- Cards de Métricas -->
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-6">
                                                <div class="card h-100">
                                                    <div class="card-body d-flex flex-column">
                                                        <div class="d-flex justify-content-between">
                                                            <div class="text-start">
                                                                <h6 class="font-size-15 mb-0">Empréstimos Ativos</h6>
                                                                <h4 class="mt-3 mb-0 font-size-22">${totalAtivos}</h4>
                                                                ${valorTotalAtivos > 0 ? `<small class="text-muted">Total: ${fmtMoeda(valorTotalAtivos)}</small>` : '<small class="text-muted">Nenhum empréstimo</small>'}
                                                            </div>
                                                            <div class="avatar">
                                                                <div class="avatar-title rounded bg-primary-subtle">
                                                                    <i class="bx bx-wallet font-size-24 mb-0 text-primary"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card h-100">
                                                    <div class="card-body d-flex flex-column">
                                                        <div class="d-flex justify-content-between">
                                                            <div class="text-start">
                                                                <h6 class="font-size-15 mb-0">Pendências Atrasadas</h6>
                                                                <h4 class="mt-3 mb-0 font-size-22">${fmtMoeda(totalPendencias)}</h4>
                                                                <small class="text-muted">${totalPendencias > 0 ? 'Parcelas vencidas' : 'Nenhuma pendência'}</small>
                                                            </div>
                                                            <div class="avatar">
                                                                <div class="avatar-title rounded bg-${totalPendencias > 0 ? 'danger' : 'success'}-subtle">
                                                                    <i class="bx bx-${totalPendencias > 0 ? 'error-circle' : 'check-circle'} font-size-24 mb-0 text-${totalPendencias > 0 ? 'danger' : 'success'}"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Alertas de Status -->
                                        ${alertaAtivoOutra}
                                        ${alertaPendencias}

                                        <!-- Cards de Detalhes -->
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h4 class="card-title mb-0">Empréstimos por Operação</h4>
                                                    </div>
                                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                                        ${ativosPorOperacaoHtml}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h4 class="card-title mb-0">Pendências por Operação</h4>
                                                    </div>
                                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                                        ${pendenciasPorOperacaoHtml}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `,
                                showCancelButton: true,
                                showDenyButton: true,
                                confirmButtonText: 'Usar cadastro',
                                denyButtonText: 'Ver/Editar ficha',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#038edc',
                                denyButtonColor: '#f1b44c',
                                cancelButtonColor: '#6c757d',
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = `{{ url('/clientes') }}/${cliente.id}`;
                                } else if (result.isDenied) {
                                    window.location.href = `{{ url('/clientes') }}/${cliente.id}/edit`;
                                }
                            });

                            setEtapa2Ativa(false);
                            return;
                        }

                        // Não existe: abrir etapa 2 (sem alert)
                        setEtapa2Ativa(true);
                        nomeInput?.focus();
                    } catch (e) {
                        console.error('Erro ao verificar documento:', e);
                        const tipoDoc = tipoPessoaSelect?.value === 'fisica' ? 'CPF' : 'CNPJ';
                        Swal.fire({
                            icon: 'error',
                            title: `Erro ao verificar ${tipoDoc}`,
                            text: e.message || `Não foi possível verificar o ${tipoDoc} no momento.`,
                            confirmButtonColor: '#038edc'
                        });
                    } finally {
                        // Esconder loading no botão
                        ButtonLoading.hide(btnVerificarDocumento);
                    }
                }

                btnVerificarDocumento?.addEventListener('click', verificarDocumento);
                documentoInput?.addEventListener('blur', function () {
                    // Evita ficar chamando enquanto usuário ainda está digitando
                    const tipoPessoa = tipoPessoaSelect?.value || 'fisica';
                    const documento = (documentoInput.value || '').replace(/\D/g, '');
                    const tamanhoEsperado = tipoPessoa === 'fisica' ? 11 : 14;
                    if (documento.length === tamanhoEsperado) {
                        verificarDocumento();
                    }
                });

                // Máscara de telefone (fixo/celular)
                const telInput = document.getElementById('telefone');
                if (telInput) {
                    telInput.addEventListener('input', function (e) {
                        let v = e.target.value.replace(/\D/g, '');
                        if (v.length > 11) v = v.slice(0, 11);

                        if (v.length <= 10) {
                            // Fixo: (XX) XXXX-XXXX
                            if (v.length > 6) {
                                e.target.value = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                            } else if (v.length > 2) {
                                e.target.value = v.replace(/(\d{2})(\d{0,4})/, '($1) $2');
                            } else {
                                e.target.value = v;
                            }
                        } else {
                            // Celular: (XX) XXXXX-XXXX
                            e.target.value = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                        }
                    });
                }

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

                // Máscara de CEP
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

                document.querySelectorAll('.form-criar-cliente').forEach(form => {
                    const submitHandler = function(e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        
                        const docInput = this.querySelector('input[name="documento"]') || this.querySelector('input[name="cpf"]');
                        const cpf = docInput ? docInput.value.trim() : '';
                        const nome = this.querySelector('input[name="nome"]')?.value?.trim() || '';
                        
                        // Se ainda está na etapa 1, impede submit
                        if (dadosContainer && dadosContainer.style.display === 'none') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Primeiro verifique o CPF para continuar o cadastro.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }

                        if (!nome || !cpf) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, preencha todos os campos obrigatórios.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        Swal.fire({
                            title: 'Criar Cliente?',
                            html: `Deseja criar o cliente <strong>${nome}</strong>?<br>CPF: ${cpf}`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, criar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.removeEventListener('submit', submitHandler, true);
                                form.removeAttribute('data-no-loading');
                                FormLoading.show(form, 'Salvando cliente...');
                                form.submit();
                            }
                        });
                    };
                    
                    form.addEventListener('submit', submitHandler, true);
                });
            });
        </script>
    @endsection