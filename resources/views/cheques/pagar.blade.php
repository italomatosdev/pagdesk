@extends('layouts.master')

@section('title')
    Registrar Pagamento – Cheque #{{ $cheque->numero_cheque }}
@endsection

@section('page-title')
    Registrar Pagamento
@endsection

@section('content')
    @php
        $operacao = $emprestimo->operacao ?? null;
    @endphp
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-money"></i> Registrar Pagamento – Cheque #{{ $cheque->numero_cheque }}
                    </h4>
                    <a href="{{ route('emprestimos.show', $emprestimo->id) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bx bx-arrow-back"></i> Voltar ao empréstimo
                    </a>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <strong>Cheque devolvido.</strong> Registre o pagamento do cliente. O valor será lançado como entrada no caixa.
                    </div>

                    <div class="card border mb-4">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-muted">Resumo do cheque</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Cliente:</strong> {{ $emprestimo->cliente->nome ?? '-' }}</p>
                                    <p class="mb-1"><strong>Empréstimo:</strong> #{{ $emprestimo->id }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Valor do cheque:</strong> R$ {{ number_format($cheque->valor_cheque, 2, ',', '.') }}</p>
                                    <p class="mb-1"><strong>Vencimento:</strong> {{ $cheque->data_vencimento->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('emprestimos.cheques.pagar-dinheiro', $cheque->id) }}" method="POST" id="formRegistrarPagamento" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label"><strong>Valor do pagamento</strong> <span class="text-danger">*</span></label>
                            <div class="input-group" style="max-width: 220px;">
                                <span class="input-group-text">R$</span>
                                <input type="text" name="valor_exibicao" id="valor_pagamento" class="form-control" 
                                       inputmode="decimal" data-mask-money="brl" readonly 
                                       value="{{ number_format($cheque->valor_cheque, 2, ',', '.') }}">
                            </div>
                            <small class="text-muted">Valor calculado conforme a opção de juros selecionada.</small>
                        </div>

                        <div class="mb-4">
                            <strong>Dias em atraso:</strong>
                            <span id="dias_atraso_exibicao" class="ms-2 fw-bold">0</span>
                            <span class="text-muted ms-1">(entre vencimento e data do pagamento)</span>
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><strong>Tipo de juros / valor</strong></label>
                            <div class="card border">
                                <div class="card-body">
                                        <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_nenhum" value="nenhum" {{ old('tipo_juros', 'nenhum') === 'nenhum' ? 'checked' : '' }} class="form-check-input" onchange="toggleCamposJuros(); atualizarValorPagamento();">
                                        <label for="tipo_juros_nenhum" class="form-check-label ms-2">
                                            <strong>Sem juros</strong> – Pagar apenas o valor do cheque
                                        </label>
                                    </div>
                                    @php
                                        $operacao = $emprestimo->operacao ?? null;
                                        $temTaxaOperacao = $operacao && $operacao->taxa_juros_atraso > 0;
                                        $tipoCalculo = $operacao ? ($operacao->tipo_calculo_juros ?? 'por_dia') : 'por_dia';
                                    @endphp
                                    @if($temTaxaOperacao)
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_automatico" value="automatico" {{ old('tipo_juros') === 'automatico' ? 'checked' : '' }} class="form-check-input" onchange="toggleCamposJuros(); atualizarValorPagamento();">
                                        <label for="tipo_juros_automatico" class="form-check-label ms-2">
                                            <strong>Juros automático</strong> – Taxa da operação ({{ $operacao ? number_format($operacao->taxa_juros_atraso, 2, ',', '.') : '0' }}% {{ $tipoCalculo === 'por_dia' ? 'ao dia' : 'ao mês' }})
                                        </label>
                                    </div>
                                    @endif
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_manual" value="manual" {{ old('tipo_juros') === 'manual' ? 'checked' : '' }} class="form-check-input" onchange="toggleCamposJuros(); atualizarValorPagamento();">
                                        <label for="tipo_juros_manual" class="form-check-label ms-2">
                                            <strong>Juros manual</strong> – Informar taxa % no momento
                                        </label>
                                        <div class="ms-4 mt-2" id="campo_taxa_manual" style="display: none;">
                                            <div class="input-group" style="max-width: 200px;">
                                                <input type="number" name="taxa_juros_manual" id="taxa_juros_manual" class="form-control" step="0.01" min="0" max="100" placeholder="Ex: 2.5" value="{{ old('taxa_juros_manual') }}">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            @error('taxa_juros_manual')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_fixo" value="fixo" {{ old('tipo_juros') === 'fixo' ? 'checked' : '' }} class="form-check-input" onchange="toggleCamposJuros(); atualizarValorPagamento();">
                                        <label for="tipo_juros_fixo" class="form-check-label ms-2">
                                            <strong>Valor total fixo</strong> – Informar valor em R$
                                        </label>
                                        <div class="ms-4 mt-2" id="campo_valor_fixo" style="display: none;">
                                            <div class="input-group" style="max-width: 200px;">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="valor_total_fixo" id="valor_total_fixo" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Ex: 1.050,00" value="{{ old('valor_total_fixo') }}">
                                            </div>
                                            @error('valor_total_fixo')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @error('tipo_juros')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><strong>Método de pagamento</strong> <span class="text-danger">*</span></label>
                            <select name="metodo_pagamento" id="metodo_pagamento" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="dinheiro" {{ old('metodo_pagamento') === 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                                <option value="pix" {{ old('metodo_pagamento') === 'pix' ? 'selected' : '' }}>PIX</option>
                                <option value="transferencia" {{ old('metodo_pagamento') === 'transferencia' ? 'selected' : '' }}>Transferência</option>
                                <option value="cartao_debito" {{ old('metodo_pagamento') === 'cartao_debito' ? 'selected' : '' }}>Cartão de débito</option>
                                <option value="cartao_credito" {{ old('metodo_pagamento') === 'cartao_credito' ? 'selected' : '' }}>Cartão de crédito</option>
                                <option value="boleto" {{ old('metodo_pagamento') === 'boleto' ? 'selected' : '' }}>Boleto</option>
                                <option value="outro" {{ old('metodo_pagamento') === 'outro' ? 'selected' : '' }}>Outro</option>
                            </select>
                            @error('metodo_pagamento')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data do pagamento <span class="text-danger">*</span></label>
                                <input type="date" name="data_pagamento" id="data_pagamento" class="form-control" required value="{{ old('data_pagamento', date('Y-m-d')) }}">
                                @error('data_pagamento')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Comprovante</label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Formatos aceitos: PDF, JPG, PNG (máx. 2MB)</small>
                                @error('comprovante')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" id="observacoes" class="form-control" rows="3" maxlength="1000">{{ old('observacoes') }}</textarea>
                            @error('observacoes')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('emprestimos.show', $emprestimo->id) }}" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-check"></i> Registrar pagamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toggleCamposJuros();
            atualizarValorPagamento();
            document.querySelectorAll('input[name="tipo_juros"]').forEach(function(el) { el.addEventListener('change', function() { toggleCamposJuros(); atualizarValorPagamento(); }); });
            document.getElementById('taxa_juros_manual').addEventListener('input', atualizarValorPagamento);
            document.getElementById('valor_total_fixo').addEventListener('input', atualizarValorPagamento);
            document.getElementById('data_pagamento').addEventListener('change', atualizarValorPagamento);
        });

        function toggleCamposJuros() {
            var manual = document.getElementById('tipo_juros_manual');
            var fixo = document.getElementById('tipo_juros_fixo');
            document.getElementById('campo_taxa_manual').style.display = manual && manual.checked ? 'block' : 'none';
            document.getElementById('campo_valor_fixo').style.display = fixo && fixo.checked ? 'block' : 'none';
        }

        /** Converte string YYYY-MM-DD em Date (meia-noite local) e retorna diferença em dias (pag - venc). */
        function diasEntreDatas(dataVencStr, dataPagStr) {
            var partV = (dataVencStr || '').split('-');
            var partP = (dataPagStr || '').split('-');
            if (partV.length !== 3 || partP.length !== 3) return 0;
            var venc = new Date(parseInt(partV[0], 10), parseInt(partV[1], 10) - 1, parseInt(partV[2], 10));
            var pag = new Date(parseInt(partP[0], 10), parseInt(partP[1], 10) - 1, parseInt(partP[2], 10));
            if (isNaN(venc.getTime()) || isNaN(pag.getTime())) return 0;
            var diffMs = pag.getTime() - venc.getTime();
            var diffDias = Math.round(diffMs / (1000 * 60 * 60 * 24));
            return Math.max(0, diffDias);
        }

        function atualizarValorPagamento() {
            var valorCheque = parseFloat("{{ number_format($cheque->valor_cheque, 2, '.', '') }}") || 0;
            var dataVencimento = '{{ $cheque->data_vencimento->format('Y-m-d') }}';
            var dataPagamentoEl = document.getElementById('data_pagamento');
            var dataPagamento = (dataPagamentoEl && dataPagamentoEl.value) ? dataPagamentoEl.value : '{{ date('Y-m-d') }}';

            var diasAtraso = diasEntreDatas(dataVencimento, dataPagamento);

            var tipoCalculo = '{{ optional($operacao ?? null)->tipo_calculo_juros ?? 'por_dia' }}';
            var taxaOperacao = parseFloat("{{ number_format(optional($operacao ?? null)->taxa_juros_atraso ?? 0, 2, '.', '') }}") || 0;

            var radioChecked = document.querySelector('input[name="tipo_juros"]:checked');
            var tipo = radioChecked ? radioChecked.value : 'nenhum';

            var total = valorCheque;
            if (tipo === 'nenhum') {
                total = valorCheque;
            } else if (tipo === 'automatico') {
                if (taxaOperacao > 0 && diasAtraso > 0) {
                    var juros = tipoCalculo === 'por_dia'
                        ? valorCheque * (taxaOperacao / 100) * diasAtraso
                        : valorCheque * (taxaOperacao / 100) * (diasAtraso / 30);
                    total = valorCheque + Math.round(juros * 100) / 100;
                } else {
                    total = valorCheque;
                }
            } else if (tipo === 'manual') {
                var elTaxa = document.getElementById('taxa_juros_manual');
                var taxaManual = elTaxa ? (parseFloat(elTaxa.value) || 0) : 0;
                if (taxaManual > 0 && diasAtraso > 0) {
                    var jurosM = tipoCalculo === 'por_dia'
                        ? valorCheque * (taxaManual / 100) * diasAtraso
                        : valorCheque * (taxaManual / 100) * (diasAtraso / 30);
                    total = valorCheque + Math.round(jurosM * 100) / 100;
                } else {
                    total = valorCheque;
                }
            } else if (tipo === 'fixo') {
                var elFixo = document.getElementById('valor_total_fixo');
                var rawFixo = elFixo && (window.MoneyMaskBRL && window.MoneyMaskBRL.unformat(elFixo.value) || elFixo.value);
                total = elFixo ? (parseFloat(rawFixo) || valorCheque) : valorCheque;
            }

            var elValor = document.getElementById('valor_pagamento');
            if (elValor) {
                var totalNum = typeof total === 'number' && !isNaN(total) ? total : valorCheque;
                elValor.value = (window.MoneyMaskBRL && window.MoneyMaskBRL.format(totalNum.toFixed(2))) || totalNum.toFixed(2).replace('.', ',');
            }

            var elDias = document.getElementById('dias_atraso_exibicao');
            if (elDias) {
                elDias.textContent = typeof diasAtraso === 'number' && !isNaN(diasAtraso) ? diasAtraso : 0;
                elDias.className = 'ms-2 fw-bold ' + (diasAtraso > 0 ? 'text-danger' : 'text-muted');
            }
        }
    </script>
@endsection
@section('scripts')
@endsection