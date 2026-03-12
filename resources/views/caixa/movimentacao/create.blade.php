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
                                    <select name="operacao_id" id="operacao_id" class="form-select" required>
                                        <option value="">Selecione uma operação...</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ old('operacao_id') == $operacao->id ? 'selected' : '' }}>
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
                                <select name="categoria_id" id="categoria_id" class="form-select">
                                    <option value="">Nenhuma</option>
                                </select>
                                <small class="text-muted">Opções filtradas pelo tipo da movimentação (entrada ou despesa)</small>
                                @error('categoria_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <script>
                                window.categoriasPorTipo = {
                                    entrada: @json($categoriasEntrada ?? []),
                                    despesa: @json($categoriasDespesa ?? [])
                                };
                                window.oldCategoriaId = @json(old('categoria_id'));
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
                        atualizarSelectUsuarios();
                    }
                    if (e.target && e.target.id === 'tipo') {
                        atualizarSelectCategorias();
                    }
                });
                form.addEventListener('input', function(e) {
                    if (e.target && e.target.id === 'operacao_id') {
                        atualizarSelectUsuarios();
                    }
                });
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
        </script>
    @endsection
