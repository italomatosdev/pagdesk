@extends('layouts.master')
@section('title')
    Nova Venda
@endsection
@section('page-title')
    Nova Venda
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Registrar Venda</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('vendas.store') }}" method="POST" id="form-venda" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select name="cliente_id" id="cliente-select" class="form-select" required>
                                    @php
                                        $clienteSelecionado = null;
                                        if (old('cliente_id')) {
                                            $clienteSelecionado = \App\Modules\Core\Models\Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find(old('cliente_id'));
                                        } elseif (isset($clientePreSelecionado) && $clientePreSelecionado) {
                                            $clienteSelecionado = $clientePreSelecionado;
                                        }
                                    @endphp
                                    @if($clienteSelecionado)
                                        <option value="{{ $clienteSelecionado->id }}" selected>
                                            {{ $clienteSelecionado->nome }} - {{ $clienteSelecionado->documento_formatado }}
                                        </option>
                                    @endif
                                </select>
                                <small class="text-muted">Digite o nome ou CPF do cliente para buscar</small>
                                @error('cliente_id')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Operação <span class="text-danger">*</span></label>
                                <select name="operacao_id" id="operacao-select" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ old('operacao_id') == $op->id ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Os produtos listados nos itens serão da operação selecionada.</small>
                                @error('operacao_id')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data da venda <span class="text-danger">*</span></label>
                                <input type="date" name="data_venda" class="form-control" value="{{ old('data_venda', date('Y-m-d')) }}" required>
                                @error('data_venda')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Desconto (R$)</label>
                                <input type="text" id="valor_desconto" name="valor_desconto" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="{{ old('valor_desconto', 0) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Observações</label>
                                <input type="text" name="observacoes" class="form-control" value="{{ old('observacoes') }}" maxlength="1000">
                            </div>
                        </div>

                        <h5 class="mb-2">Itens da venda</h5>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered" id="tabela-itens">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produto / Descrição</th>
                                        <th width="100">Qtd</th>
                                        <th width="120">Preço à vista</th>
                                        <th width="120">Preço crediário</th>
                                        <th width="60"></th>
                                    </tr>
                                </thead>
                                <tbody id="corpo-itens">
                                    <tr class="linha-item">
                                        <td>
                                            <select name="itens[0][produto_id]" class="form-select form-select-produto">
                                                <option value="">— Descrição livre —</option>
                                                @foreach($produtos as $p)
                                                    <option value="{{ $p->id }}" data-operacao-id="{{ $p->operacao_id }}" data-preco-vista="{{ $p->preco_venda }}" data-preco-crediario="{{ $p->preco_venda }}" data-estoque="{{ $p->estoque }}">{{ $p->nome }} (R$ {{ number_format($p->preco_venda, 2, ',', '.') }}) — Estoque: {{ number_format((float)$p->estoque, 3, ',', '.') }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted d-block mt-1 estoque-disponivel" style="display:none !important;"></small>
                                            <input type="text" name="itens[0][descricao]" class="form-control mt-1 d-none input-descricao" placeholder="Descrição do item">
                                        </td>
                                        <td><input type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" value="1" required></td>
                                        <td><input type="text" name="itens[0][preco_unitario_vista]" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="0" required></td>
                                        <td><input type="text" name="itens[0][preco_unitario_crediario]" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="0" required></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remover-item" title="Remover"><i class="bx bx-trash"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-adicionar-item"><i class="bx bx-plus me-1"></i> Adicionar item</button>
                        </div>

                        <h5 class="mb-2">Formas de pagamento</h5>
                        <p class="text-muted small mb-2">Para Dinheiro, PIX e Cartão você pode informar uma descrição (ex: "Entrada", "Sinal") e anexar comprovante. Crediário gera parcelas do valor informado.</p>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered" id="tabela-formas">
                                <thead class="table-light">
                                    <tr>
                                        <th>Forma</th>
                                        <th width="140">Valor (R$)</th>
                                        <th>Descrição</th>
                                        <th>Comprovante</th>
                                        <th width="100">Parcelas (crediário)</th>
                                        <th width="120">Frequência (crediário)</th>
                                        <th width="60"></th>
                                    </tr>
                                </thead>
                                <tbody id="corpo-formas">
                                    <tr class="linha-forma">
                                        <td>
                                            <select name="formas[0][forma]" class="form-select form-select-forma">
                                                @foreach($formasDisponiveis as $valor => $label)
                                                    <option value="{{ $valor }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="text" name="formas[0][valor]" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="0" required></td>
                                        <td><input type="text" name="formas[0][descricao]" class="form-control" placeholder="Ex: Entrada, Sinal" maxlength="255"></td>
                                        <td class="celula-comprovante"><input type="file" name="formas[0][comprovante]" class="form-control form-control-sm input-comprovante" accept=".pdf,image/jpeg,image/jpg,image/png" style="display:none;"></td>
                                        <td class="celula-parcelas"><input type="number" name="formas[0][numero_parcelas]" class="form-control input-parcelas" min="1" placeholder="—" style="display:none;"></td>
                                        <td class="celula-frequencia">
                                            <select name="formas[0][frequencia]" class="form-select form-select-frequencia" style="display:none;">
                                                <option value="mensal" selected>Mensal</option>
                                                <option value="semanal">Semanal</option>
                                                <option value="diaria">Diária</option>
                                            </select>
                                        </td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remover-forma" title="Remover"><i class="bx bx-trash"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-adicionar-forma"><i class="bx bx-plus me-1"></i> Adicionar forma</button>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Registrar venda</button>
                            <a href="{{ route('vendas.index') }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var indiceItem = 1;
        var indiceForma = 1;

        function atualizarIndicesItens() {
            document.querySelectorAll('#corpo-itens tr').forEach(function(tr, i) {
                tr.querySelectorAll('select, input').forEach(function(el) {
                    if (el.name) el.name = el.name.replace(/itens\[\d+\]/, 'itens[' + i + ']');
                });
            });
        }
        var FORMAS_COM_COMPROVANTE = ['vista', 'pix', 'cartao'];
        function atualizarIndicesFormas() {
            document.querySelectorAll('#corpo-formas tr').forEach(function(tr, i) {
                tr.querySelectorAll('select, input').forEach(function(el) {
                    if (el.name) el.name = el.name.replace(/formas\[\d+\]/, 'formas[' + i + ']');
                });
            });
        }

        document.getElementById('btn-adicionar-item').addEventListener('click', function() {
            var tr = document.querySelector('#corpo-itens tr').cloneNode(true);
            tr.classList.add('linha-item');
            tr.querySelector('.form-select-produto').selectedIndex = 0;
            tr.querySelector('.input-descricao').value = '';
            tr.querySelector('input[name*="quantidade"]').value = '1';
            tr.querySelectorAll('input[name*="preco_unitario"]').forEach(function(i){ i.value = '0'; });
            var est = tr.querySelector('.estoque-disponivel');
            if (est) { est.textContent = ''; est.style.display = 'none'; }
            document.getElementById('corpo-itens').appendChild(tr);
            atualizarIndicesItens();
            bindProdutoSelect(tr);
            bindRemoverItem();
            if (typeof filtrarProdutosPorOperacao === 'function') filtrarProdutosPorOperacao();
            if (window.applyMaskBRL) window.applyMaskBRL();
        });

        document.querySelectorAll('.form-select-produto').forEach(function(sel) { bindProdutoSelect(sel.closest('tr')); });
        function bindProdutoSelect(tr) {
            var sel = tr.querySelector('.form-select-produto');
            var desc = tr.querySelector('.input-descricao');
            var pv = tr.querySelector('input[name*="preco_unitario_vista"]');
            var pc = tr.querySelector('input[name*="preco_unitario_crediario"]');
            var estoqueSpan = tr.querySelector('.estoque-disponivel');
            sel.addEventListener('change', function() {
                var opt = this.options[this.selectedIndex];
                if (opt.value) {
                    pv.value = opt.dataset.precoVista || 0;
                    pc.value = opt.dataset.precoCrediario || opt.dataset.precoVista || 0;
                    desc.classList.add('d-none');
                    if (estoqueSpan) {
                        estoqueSpan.textContent = 'Estoque disponível: ' + parseFloat(opt.dataset.estoque || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
                        estoqueSpan.style.display = 'block';
                    }
                } else {
                    desc.classList.remove('d-none');
                    if (estoqueSpan) estoqueSpan.style.display = 'none';
                }
            });
        }

        document.getElementById('btn-adicionar-forma').addEventListener('click', function() {
            var tr = document.querySelector('#corpo-formas tr').cloneNode(true);
            tr.classList.add('linha-forma');
            tr.querySelector('.form-select-forma').selectedIndex = 0;
            tr.querySelector('input[name*="valor"]').value = '0';
            tr.querySelector('input[name*="descricao"]').value = '';
            var parcelas = tr.querySelector('.input-parcelas');
            parcelas.value = '';
            parcelas.style.display = 'none';
            var freq = tr.querySelector('.form-select-frequencia');
            if (freq) { freq.value = 'mensal'; freq.style.display = 'none'; }
            var comprovante = tr.querySelector('.input-comprovante');
            comprovante.value = '';
            comprovante.style.display = 'none';
            document.getElementById('corpo-formas').appendChild(tr);
            atualizarIndicesFormas();
            bindFormaSelect(tr);
            bindRemoverForma();
            if (window.applyMaskBRL) window.applyMaskBRL();
        });

        function toggleParcelas(tr) {
            var forma = tr.querySelector('.form-select-forma').value;
            var celParcelas = tr.querySelector('.celula-parcelas input');
            celParcelas.style.display = forma === 'crediario' ? 'block' : 'none';
            if (forma !== 'crediario') celParcelas.removeAttribute('required'); else celParcelas.setAttribute('required', 'required');
            var selFrequencia = tr.querySelector('.form-select-frequencia');
            if (selFrequencia) {
                selFrequencia.style.display = forma === 'crediario' ? 'block' : 'none';
                if (forma !== 'crediario') selFrequencia.removeAttribute('required'); else selFrequencia.setAttribute('required', 'required');
            }
            var inputComprovante = tr.querySelector('.input-comprovante');
            inputComprovante.style.display = FORMAS_COM_COMPROVANTE.indexOf(forma) !== -1 ? 'block' : 'none';
        }
        document.querySelectorAll('#corpo-formas tr').forEach(function(tr) {
            bindFormaSelect(tr);
        });
        function bindFormaSelect(tr) {
            tr.querySelector('.form-select-forma').addEventListener('change', function() { toggleParcelas(tr); });
            toggleParcelas(tr);
        }

        function bindRemoverItem() {
            document.querySelectorAll('.btn-remover-item').forEach(function(btn) {
                btn.onclick = function() {
                    if (document.querySelectorAll('#corpo-itens tr').length <= 1) return;
                    this.closest('tr').remove();
                    atualizarIndicesItens();
                };
            });
        }
        function bindRemoverForma() {
            document.querySelectorAll('.btn-remover-forma').forEach(function(btn) {
                btn.onclick = function() {
                    if (document.querySelectorAll('#corpo-formas tr').length <= 1) return;
                    this.closest('tr').remove();
                    atualizarIndicesFormas();
                };
            });
        }
        bindRemoverItem();
        bindRemoverForma();

        // Filtrar produtos pelo select de operação (produtos são da operação)
        function filtrarProdutosPorOperacao() {
            var operacaoId = document.getElementById('operacao-select').value;
            document.querySelectorAll('.form-select-produto').forEach(function(sel) {
                var opts = sel.querySelectorAll('option');
                opts.forEach(function(opt) {
                    if (opt.value === '') {
                        opt.style.display = '';
                        opt.disabled = false;
                        return;
                    }
                    var opId = opt.getAttribute('data-operacao-id');
                    if (opId === operacaoId) {
                        opt.style.display = '';
                        opt.disabled = false;
                    } else {
                        opt.style.display = 'none';
                        opt.disabled = true;
                        if (opt.selected) { opt.selected = false; sel.selectedIndex = 0; }
                    }
                });
            });
        }
        document.getElementById('operacao-select').addEventListener('change', filtrarProdutosPorOperacao);
        filtrarProdutosPorOperacao();

        // Cliente Select2 (mesma configuração da criação de empréstimo - API clientes.api.buscar)
        if (typeof $ !== 'undefined' && $.fn.select2) {
            var clienteSelectEl = document.getElementById('cliente-select');
            var clienteJaSelecionado = clienteSelectEl && clienteSelectEl.options.length > 0 && clienteSelectEl.options[0].value;
            var msgOperacaoObrigatoriaVenda = 'Selecione a operação antes de buscar o cliente.';
            function getOperacaoIdVenda() {
                var opEl = document.getElementById('operacao-select');
                return opEl && opEl.value ? String(opEl.value) : '';
            }
            var select2Config = {
                theme: 'bootstrap-5',
                placeholder: 'Selecione a operação acima, depois busque o cliente…',
                allowClear: true,
                minimumInputLength: 2,
                language: {
                    searching: function() { return 'Buscando...'; },
                    loadingMore: function() { return 'Carregando mais resultados...'; },
                    noResults: function() { return 'Nenhum cliente encontrado'; },
                    inputTooShort: function() { return 'Digite pelo menos 2 caracteres para buscar'; }
                },
                ajax: {
                    url: '{{ route("clientes.api.buscar") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term, operacao_id: getOperacaoIdVenda(), page: params.page || 1 };
                    },
                    transport: function(params, success, failure) {
                        if (!getOperacaoIdVenda()) {
                            alert(msgOperacaoObrigatoriaVenda);
                            failure('no_operacao');
                            return;
                        }
                        var $request = $.ajax(params);
                        $request.then(success);
                        $request.fail(failure);
                        return $request;
                    },
                    processResults: function(data, params) {
                        if (data.error) {
                            alert(data.error);
                            return { results: [], pagination: { more: false } };
                        }
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: { more: (params.page * 20) < (data.total_count || 0) }
                        };
                    },
                    cache: true
                }
            };
            if (clienteJaSelecionado) select2Config.minimumInputLength = 0;
            $('#cliente-select').select2(select2Config);
            $('#cliente-select').on('select2:opening', function(e) {
                if (!getOperacaoIdVenda()) {
                    e.preventDefault();
                    alert(msgOperacaoObrigatoriaVenda);
                }
            });
            var operacaoSelVenda = document.getElementById('operacao-select');
            if (operacaoSelVenda) {
                operacaoSelVenda.addEventListener('change', function () {
                    $('#cliente-select').val(null).trigger('change');
                });
            }
        }
    });
    </script>
@endsection
@section('scripts')
@endsection