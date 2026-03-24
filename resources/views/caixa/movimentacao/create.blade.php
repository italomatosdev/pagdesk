@extends('layouts.master')
@section('title')
    Nova Movimentação Manual
@endsection
@section('page-title')
    Nova Movimentação Manual
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Criar Movimentação Manual</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('caixa.movimentacao.store') }}" method="POST" enctype="multipart/form-data" id="formCreateMovimentacao">
                            @csrf

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                    <select name="tipo" id="tipo" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <option value="entrada" {{ old('tipo') == 'entrada' ? 'selected' : '' }}>Entrada</option>
                                        <option value="saida" {{ old('tipo') == 'saida' ? 'selected' : '' }}>Saída</option>
                                    </select>
                                    @error('tipo')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Operação <span class="text-danger">*</span></label>
                                    @php
                                        $opSelMov = old('operacao_id', $operacaoIdDefault ?? null);
                                    @endphp
                                    <select name="operacao_id" id="operacao_id" class="form-select" required>
                                        <option value="">Selecione uma operação...</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ $opSelMov !== null && (int) $opSelMov === (int) $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('operacao_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Categoria</label>
                                <div class="input-group">
                                    <select name="categoria_id" id="categoria_id" class="form-select">
                                        <option value="">Nenhuma</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" id="btn-add-categoria" 
                                            data-bs-toggle="modal" data-bs-target="#modalNovaCategoria"
                                            title="Criar nova categoria">
                                        <i class="bx bx-plus"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Opções filtradas pelo tipo da movimentação (entrada ou despesa)</small>
                                @error('categoria_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <script>
                                window.categoriasPorOperacao = @json($categoriasPorOperacao ?? []);
                                window.categoriasPorTipo = { entrada: [], despesa: [] };
                                window.oldCategoriaId = @json(old('categoria_id'));
                                function aplicarCategoriasDaOperacao(operacaoId) {
                                    var porOp = window.categoriasPorOperacao[operacaoId];
                                    if (porOp) {
                                        window.categoriasPorTipo.entrada = porOp.entrada || [];
                                        window.categoriasPorTipo.despesa = porOp.despesa || [];
                                    } else {
                                        window.categoriasPorTipo.entrada = [];
                                        window.categoriasPorTipo.despesa = [];
                                    }
                                }
                            </script>

                            <div class="mb-3">
                                <label class="form-label">Usuário Responsável</label>
                                <select name="consultor_id" id="consultor_id" class="form-select">
                                    <option value="">Caixa da Operação (Sem usuário específico)</option>
                                </select>
                                <small class="text-muted">
                                    <strong>Caixa da Operação:</strong> Movimentação geral (aportes, despesas operacionais, etc.)<br>
                                    <strong>Usuário específico:</strong> Apenas usuários da operação selecionada acima
                                </small>
                                @error('consultor_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <script>
                                window.usuariosPorOperacao = {!! json_encode($usuariosPorOperacao ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                                window.oldConsultorId = {!! json_encode(old('consultor_id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                            </script>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Valor <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" name="valor" id="valor_movimentacao" class="form-control" inputmode="decimal"
                                               data-mask-money="brl" placeholder="0,00" value="{{ old('valor') }}" required>
                                    </div>
                                    @error('valor')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Data da Movimentação <span class="text-danger">*</span></label>
                                    <input type="date" name="data_movimentacao" class="form-control" 
                                           value="{{ old('data_movimentacao', date('Y-m-d')) }}" 
                                           max="{{ date('Y-m-d') }}" required>
                                    <small class="text-muted">Não pode ser uma data futura</small>
                                    @error('data_movimentacao')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Descrição <span class="text-danger">*</span></label>
                                <input type="text" name="descricao" id="descricao" class="form-control" 
                                       placeholder="Ex: Aporte de capital, Despesa com aluguel, etc." 
                                       value="{{ old('descricao') }}" required maxlength="255">
                                <small class="text-muted" id="descricao-help">
                                    Descreva detalhadamente a movimentação
                                </small>
                                @error('descricao')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3" maxlength="1000">{{ old('observacoes') }}</textarea>
                                <small class="text-muted">Informações adicionais sobre esta movimentação</small>
                                @error('observacoes')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Comprovante</label>
                                <input type="file" name="comprovante" class="form-control" 
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF ou imagem (máx. 2MB)</small>
                                @error('comprovante')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-info" id="alert-info">
                                <i class="bx bx-info-circle"></i> 
                                <strong>Atenção:</strong> <span id="alert-text">Esta movimentação será registrada manualmente.</span>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('caixa.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-check"></i> Criar Movimentação
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Nova Categoria -->
        <div class="modal fade" id="modalNovaCategoria" tabindex="-1" aria-labelledby="modalNovaCategoriaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalNovaCategoriaLabel">
                            <i class="bx bx-category me-1"></i> Nova Categoria
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="formNovaCategoria">
                            <div class="mb-3">
                                <label class="form-label">Operação <span class="text-danger">*</span></label>
                                <select name="operacao_id" id="modal_categoria_operacao_id" class="form-select" required>
                                    <option value="">Selecione a operação</option>
                                    @foreach($operacoes ?? [] as $op)
                                        <option value="{{ $op->id }}" {{ isset($opSelMov) && $opSelMov !== null && (int) $opSelMov === (int) $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Operação à qual esta categoria pertencerá.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                                <input type="text" name="nome" id="categoria_nome" class="form-control" 
                                       placeholder="Ex: Aluguel, Energia, Comissão..." required maxlength="100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select name="tipo" id="categoria_tipo" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <option value="entrada">Entrada</option>
                                    <option value="despesa">Despesa (Saída)</option>
                                </select>
                                <small class="text-muted">
                                    <strong>Entrada:</strong> Aportes, receitas extras<br>
                                    <strong>Despesa:</strong> Custos, gastos operacionais
                                </small>
                            </div>
                            <div id="categoria-erro" class="alert alert-danger d-none"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn-salvar-categoria">
                            <i class="bx bx-check"></i> Criar Categoria
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            (function() {
                function init() {
                const form = document.getElementById('formCreateMovimentacao');
                const operacaoSelect = document.getElementById('operacao_id');
                const consultorSelect = document.getElementById('consultor_id');
                const descricaoHelp = document.getElementById('descricao-help');
                const alertText = document.getElementById('alert-text');
                const usuariosPorOperacao = window.usuariosPorOperacao || {};
                const oldConsultorId = window.oldConsultorId;

                if (!operacaoSelect || !consultorSelect) return;

                // Preencher select de usuários apenas com os da operação selecionada
                function atualizarSelectUsuarios() {
                    const operacaoId = operacaoSelect.value;
                    const selectedValue = consultorSelect.value;
                    while (consultorSelect.options.length > 1) {
                        consultorSelect.remove(1);
                    }
                    if (!operacaoId) {
                        consultorSelect.value = '';
                        return;
                    }
                    const usuarios = usuariosPorOperacao[operacaoId] || [];
                    usuarios.forEach(function(u) {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.name + ' - ' + u.roles;
                        consultorSelect.appendChild(opt);
                    });
                    var optOld = oldConsultorId ? consultorSelect.querySelector('option[value="' + String(oldConsultorId) + '"]') : null;
                    var optSelected = selectedValue ? consultorSelect.querySelector('option[value="' + String(selectedValue) + '"]') : null;
                    if (optSelected) {
                        consultorSelect.value = selectedValue;
                    } else if (optOld) {
                        consultorSelect.value = String(oldConsultorId);
                    } else {
                        consultorSelect.value = '';
                    }
                }

                function atualizarSelectCategorias() {
                    const tipoSelect = document.getElementById('tipo');
                    const categoriaSelect = document.getElementById('categoria_id');
                    if (!tipoSelect || !categoriaSelect) return;
                    const tipoMov = tipoSelect.value;
                    // entrada -> categorias tipo entrada; saida -> categorias tipo despesa
                    const tipoCategoria = tipoMov === 'entrada' ? 'entrada' : 'despesa';
                    const categorias = window.categoriasPorTipo[tipoCategoria] || [];
                    while (categoriaSelect.options.length > 1) categoriaSelect.remove(1);
                    categorias.forEach(function(c) {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.nome;
                        categoriaSelect.appendChild(opt);
                    });
                    const oldId = window.oldCategoriaId;
                    if (oldId && categoriaSelect.querySelector('option[value="' + String(oldId) + '"]')) {
                        categoriaSelect.value = String(oldId);
                    } else {
                        categoriaSelect.value = '';
                    }
                }

                // Delegação no form: assim o change da operação sempre é capturado
                form.addEventListener('change', function(e) {
                    if (e.target && e.target.id === 'operacao_id') {
                        aplicarCategoriasDaOperacao(e.target.value);
                        atualizarSelectUsuarios();
                        atualizarSelectCategorias();
                    }
                    if (e.target && e.target.id === 'tipo') {
                        atualizarSelectCategorias();
                    }
                });
                form.addEventListener('input', function(e) {
                    if (e.target && e.target.id === 'operacao_id') {
                        aplicarCategoriasDaOperacao(e.target.value);
                        atualizarSelectUsuarios();
                        atualizarSelectCategorias();
                    }
                });
                var opId = operacaoSelect && operacaoSelect.value ? operacaoSelect.value : (Object.keys(window.categoriasPorOperacao || {})[0] || '');
                aplicarCategoriasDaOperacao(opId);
                atualizarSelectUsuarios();
                atualizarSelectCategorias();

                // Atualizar mensagens quando selecionar caixa da operação
                consultorSelect.addEventListener('change', function() {
                    const isCaixaOperacao = this.value === '';
                    if (isCaixaOperacao) {
                        descricaoHelp.innerHTML = '<strong>Obrigatório:</strong> Para movimentações do caixa da operação, a descrição deve ter pelo menos 20 caracteres.';
                        alertText.textContent = 'Esta movimentação será registrada no caixa central da operação (sem usuário específico).';
                    } else {
                        descricaoHelp.textContent = 'Descreva detalhadamente a movimentação';
                        const usuarioNome = this.options[this.selectedIndex].text.split(' - ')[0];
                        alertText.textContent = 'Esta movimentação será registrada no caixa de ' + usuarioNome + '.';
                    }
                });

                consultorSelect.dispatchEvent(new Event('change'));
                }

                function attachSubmitHandler() {
                    var form = document.getElementById('formCreateMovimentacao');
                    if (!form) return;
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var f = this;
                        var tipo = this.querySelector('select[name="tipo"] option:checked').textContent;
                        var operacao = this.querySelector('select[name="operacao_id"] option:checked').textContent;
                        var consultorSelect = this.querySelector('select[name="consultor_id"]');
                        var consultor = consultorSelect.value === '' ? 'Caixa da Operação' : consultorSelect.options[consultorSelect.selectedIndex].text;
                        var valorInput = this.querySelector('input[name="valor"]');
                        var valorNum = NaN;
                        if (valorInput && valorInput.value) {
                            var s = String(valorInput.value).replace(/\s/g, '').replace(/R\$\s?/g, '').replace(/\./g, '').replace(',', '.');
                            valorNum = parseFloat(s);
                        }
                        var valor = (!isNaN(valorNum) && valorNum >= 0)
                            ? Number(valorNum).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                            : 'R$ 0,00';
                        var data = this.querySelector('input[name="data_movimentacao"]').value.split('-').reverse().join('/');
                        var descricao = this.querySelector('input[name="descricao"]').value;
                        var esc = function(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
                        Swal.fire({
                            title: 'Criar Movimentação Manual?',
                            html: '<div class="text-start">' +
                                '<p><strong>Tipo:</strong> ' + esc(tipo) + '</p>' +
                                '<p><strong>Operação:</strong> ' + esc(operacao) + '</p>' +
                                '<p><strong>Responsável:</strong> ' + esc(consultor) + '</p>' +
                                '<p><strong>Valor:</strong> ' + esc(valor) + '</p>' +
                                '<p><strong>Data:</strong> ' + esc(data) + '</p>' +
                                '<p><strong>Descrição:</strong> ' + esc(descricao) + '</p></div>',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, Criar!',
                            cancelButtonText: 'Cancelar'
                        }).then(function(result) {
                            if (result.isConfirmed) {
                                var inp = f.querySelector('input[name="valor"]');
                                if (inp && inp.value) {
                                    var s = String(inp.value).replace(/\s/g, '').replace(/R\$\s?/g, '');
                                    if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
                                    var n = parseFloat(s);
                                    inp.value = (!isNaN(n) && n >= 0) ? n.toFixed(2) : inp.value;
                                }
                                f.submit();
                            }
                        });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                    document.addEventListener('DOMContentLoaded', attachSubmitHandler);
                } else {
                    init();
                    attachSubmitHandler();
                }
            })();

            // Modal Nova Categoria
            (function() {
                function initModalCategoria() {
                    const modal = document.getElementById('modalNovaCategoria');
                    const btnSalvar = document.getElementById('btn-salvar-categoria');
                    const formCategoria = document.getElementById('formNovaCategoria');
                    const tipoMovimentacao = document.getElementById('tipo');
                    const tipoCategoria = document.getElementById('categoria_tipo');
                    const categoriaErro = document.getElementById('categoria-erro');

                    if (!modal || !btnSalvar) return;

                    // Ao abrir o modal: sincronizar operação com o formulário e pré-selecionar o tipo
                    modal.addEventListener('show.bs.modal', function() {
                        const operacaoPrincipal = document.getElementById('operacao_id');
                        const operacaoModal = document.getElementById('modal_categoria_operacao_id');
                        if (operacaoPrincipal && operacaoModal) {
                            operacaoModal.value = operacaoPrincipal.value || '';
                        }
                        const tipoMov = tipoMovimentacao ? tipoMovimentacao.value : '';
                        if (tipoMov === 'entrada') {
                            tipoCategoria.value = 'entrada';
                        } else if (tipoMov === 'saida') {
                            tipoCategoria.value = 'despesa';
                        } else {
                            tipoCategoria.value = '';
                        }
                        document.getElementById('categoria_nome').value = '';
                        categoriaErro.classList.add('d-none');
                    });

                    // Salvar categoria via AJAX
                    btnSalvar.addEventListener('click', function() {
                        const nome = document.getElementById('categoria_nome').value.trim();
                        const tipo = tipoCategoria.value;

                        const operacaoSelectModal = document.getElementById('modal_categoria_operacao_id');
                        const operacaoId = operacaoSelectModal && operacaoSelectModal.value;
                        if (!nome || !tipo || !operacaoId) {
                            categoriaErro.textContent = 'Preencha todos os campos obrigatórios (incluindo operação).';
                            categoriaErro.classList.remove('d-none');
                            return;
                        }

                        btnSalvar.disabled = true;
                        btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
                        categoriaErro.classList.add('d-none');

                        fetch('{{ route("caixa.categorias.store.ajax") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ nome: nome, tipo: tipo, operacao_id: operacaoId })
                        })
                        .then(function(response) {
                            return response.json().then(function(data) {
                                return { ok: response.ok, data: data };
                            });
                        })
                        .then(function(result) {
                            btnSalvar.disabled = false;
                            btnSalvar.innerHTML = '<i class="bx bx-check"></i> Criar Categoria';

                            if (result.ok && result.data.success) {
                                // Adicionar categoria no array local
                                const novaCategoria = result.data.categoria;
                                if (novaCategoria.tipo === 'entrada') {
                                    window.categoriasPorTipo.entrada.push(novaCategoria);
                                } else {
                                    window.categoriasPorTipo.despesa.push(novaCategoria);
                                }

                                // Atualizar select se o tipo corresponder
                                const tipoMov = tipoMovimentacao.value;
                                const tipoCategoriaMov = tipoMov === 'entrada' ? 'entrada' : 'despesa';
                                if (novaCategoria.tipo === tipoCategoriaMov) {
                                    const categoriaSelect = document.getElementById('categoria_id');
                                    const opt = document.createElement('option');
                                    opt.value = novaCategoria.id;
                                    opt.textContent = novaCategoria.nome;
                                    categoriaSelect.appendChild(opt);
                                    categoriaSelect.value = novaCategoria.id;
                                }

                                // Fechar modal
                                bootstrap.Modal.getInstance(modal).hide();

                                // Notificação de sucesso
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Categoria criada!',
                                        text: 'A categoria "' + novaCategoria.nome + '" foi criada e selecionada.',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                }
                            } else {
                                const msg = result.data.message || result.data.errors?.nome?.[0] || 'Erro ao criar categoria.';
                                categoriaErro.textContent = msg;
                                categoriaErro.classList.remove('d-none');
                            }
                        })
                        .catch(function(error) {
                            btnSalvar.disabled = false;
                            btnSalvar.innerHTML = '<i class="bx bx-check"></i> Criar Categoria';
                            categoriaErro.textContent = 'Erro de conexão. Tente novamente.';
                            categoriaErro.classList.remove('d-none');
                        });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initModalCategoria);
                } else {
                    initModalCategoria();
                }
            })();
        </script>
    @endsection
