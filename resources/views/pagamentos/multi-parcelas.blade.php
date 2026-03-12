@extends('layouts.master')
@section('title')
    Pagar mais de uma parcela – Empréstimo #{{ $emprestimo->id }}
@endsection
@section('page-title')
    Pagar mais de uma parcela
@endsection
@section('body')<body>@endsection
@section('content')
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Pagar mais de uma parcela</h4>
                        <a href="{{ route('emprestimos.show', $emprestimo->id) }}" class="btn btn-secondary btn-sm">
                            <i class="bx bx-arrow-back"></i> Voltar ao empréstimo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="alert alert-info mb-3">
                        <strong>Empréstimo #{{ $emprestimo->id }}</strong> – {{ $emprestimo->cliente->nome ?? 'Cliente' }}<br>
                        Selecione <strong>pelo menos duas</strong> parcelas em aberto. O valor total é a <strong>soma</strong> do que falta em cada uma + juros de atraso (conforme opção abaixo). Um único comprovante vale para todos os pagamentos.
                    </div>

                    <form action="{{ route('pagamentos.multi-parcelas.store', $emprestimo->id) }}" method="POST" enctype="multipart/form-data" id="formMultiParcelas">
                        @csrf

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="check_todas" title="Marcar todas">
                                        </th>
                                        <th>Parcela</th>
                                        <th>Vencimento</th>
                                        <th>Valor parcela</th>
                                        <th>Já pago</th>
                                        <th>Falta pagar</th>
                                        <th>Dias atraso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($parcelasAbertas as $p)
                                        @php
                                            $falta = (float)$p->valor - (float)($p->valor_pago ?? 0);
                                            $dias = $p->calcularDiasAtraso(now());
                                        @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="parcela_ids[]" value="{{ $p->id }}" class="form-check-input parcela-check"
                                                       data-falta="{{ number_format($falta, 2, '.', '') }}"
                                                       data-valor="{{ number_format($p->valor, 2, '.', '') }}"
                                                       data-dias="{{ $dias }}">
                                            </td>
                                            <td>#{{ $p->numero }}</td>
                                            <td>{{ $p->data_vencimento->format('d/m/Y') }}</td>
                                            <td>R$ {{ number_format($p->valor, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($p->valor_pago ?? 0, 2, ',', '.') }}</td>
                                            <td><strong>R$ {{ number_format($falta, 2, ',', '.') }}</strong></td>
                                            <td>{{ $dias > 0 ? $dias . ' dias' : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Juros de atraso</strong> (sobre as parcelas selecionadas)</label>
                            <div class="card border">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_nenhum" value="nenhum" checked class="form-check-input">
                                        <label for="tipo_juros_nenhum" class="form-check-label ms-2"><strong>Sem juros de atraso</strong></label>
                                        <div class="ms-4 text-muted">Total principal selecionado: R$ <span id="total_principal_sel">0,00</span></div>
                                    </div>
                                    @if($operacao->taxa_juros_atraso > 0)
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_automatico" value="automatico" class="form-check-input">
                                        <label for="tipo_juros_automatico" class="form-check-label ms-2">
                                            <strong>Juros automático</strong> ({{ number_format($operacao->taxa_juros_atraso, 2, ',', '.') }}% {{ $operacao->tipo_calculo_juros === 'por_dia' ? 'ao dia' : 'ao mês' }})
                                        </label>
                                        <div class="ms-4 text-muted">Juros: R$ <span id="juros_auto_sel">0,00</span> – <strong>Total: R$ <span id="total_auto_sel">0,00</span></strong></div>
                                    </div>
                                    @endif
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_manual" value="manual" class="form-check-input">
                                        <label for="tipo_juros_manual" class="form-check-label ms-2"><strong>Juros manual</strong> (%)</label>
                                        <div class="ms-4 mt-2" id="campo_taxa_manual" style="display: none;">
                                            <div class="input-group" style="max-width: 280px;">
                                                <input type="number" name="taxa_juros_manual" id="taxa_juros_manual" class="form-control" step="0.01" min="0" max="100" placeholder="Ex: 2.5">
                                                <span class="input-group-text">% {{ $operacao->tipo_calculo_juros === 'por_mes' ? 'ao mês' : 'ao dia' }}</span>
                                            </div>
                                            <div class="text-muted mt-2">Juros: R$ <span id="juros_manual_sel">0,00</span> – <strong>Total: R$ <span id="total_manual_sel">0,00</span></strong></div>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_fixo" value="fixo" class="form-check-input">
                                        <label for="tipo_juros_fixo" class="form-check-label ms-2"><strong>Juros (valor fixo total)</strong></label>
                                        <div class="ms-4 mt-2" id="campo_valor_fixo" style="display: none;">
                                            <div class="input-group" style="max-width: 280px;">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="valor_juros_fixo" id="valor_juros_fixo" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Ex: 50,00">
                                            </div>
                                            <div class="text-muted mt-2"><strong>Total: R$ <span id="total_fixo_sel">0,00</span></strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Valor total (referência)</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" id="valor_total_exibir" class="form-control bg-light" readonly value="0,00">
                            </div>
                            <small class="text-muted">Soma do que falta nas parcelas marcadas + juros conforme opção acima. O servidor recalcula na confirmação.</small>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Método <span class="text-danger">*</span></label>
                                <select name="metodo" class="form-select" required>
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="pix">PIX</option>
                                    <option value="transferencia">Transferência</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data do pagamento <span class="text-danger">*</span></label>
                                <input type="date" name="data_pagamento" class="form-control" value="{{ old('data_pagamento', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Comprovante (único para todas)</label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="2">{{ old('observacoes') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bx bx-check"></i> Registrar pagamento</button>
                            <a href="{{ route('emprestimos.show', $emprestimo->id) }}" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var taxaOperacao = {{ number_format($operacao->taxa_juros_atraso ?? 0, 4, '.', '') }};
            var tipoCalculo = '{{ $operacao->tipo_calculo_juros ?? "por_dia" }}';

            function fmt(v) {
                return (typeof v === 'number' ? v : parseFloat(v)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function parseBRL(str) {
                if (!str || typeof str !== 'string') return 0;
                var s = str.replace(/\s/g, '').replace(/R\$\s?/gi, '').trim();
                if (!s) return 0;
                s = s.replace(/\./g, '').replace(',', '.');
                var n = parseFloat(s);
                return isNaN(n) ? 0 : n;
            }

            function getSelecionadas() {
                return Array.prototype.slice.call(document.querySelectorAll('.parcela-check:checked'));
            }

            function somaPrincipal() {
                var total = 0;
                getSelecionadas().forEach(function(cb) {
                    total += parseFloat(cb.dataset.falta) || 0;
                });
                return total;
            }

            function jurosAutomatico() {
                var total = 0;
                getSelecionadas().forEach(function(cb) {
                    var valorP = parseFloat(cb.dataset.valor) || 0;
                    var dias = parseInt(cb.dataset.dias, 10) || 0;
                    if (dias <= 0 || taxaOperacao <= 0) return;
                    if (tipoCalculo === 'por_dia') total += valorP * (taxaOperacao / 100) * dias;
                    else total += valorP * (taxaOperacao / 100) * (dias / 30);
                });
                return Math.round(total * 100) / 100;
            }

            function jurosManual(taxa) {
                var total = 0;
                getSelecionadas().forEach(function(cb) {
                    var valorP = parseFloat(cb.dataset.valor) || 0;
                    var dias = parseInt(cb.dataset.dias, 10) || 0;
                    if (dias <= 0 || taxa <= 0) return;
                    if (tipoCalculo === 'por_dia') total += valorP * (taxa / 100) * dias;
                    else total += valorP * (taxa / 100) * (dias / 30);
                });
                return Math.round(total * 100) / 100;
            }

            function atualizar() {
                var principal = somaPrincipal();
                var tipo = document.querySelector('input[name="tipo_juros"]:checked');
                var v = tipo ? tipo.value : 'nenhum';
                var juros = 0;
                if (v === 'automatico') juros = jurosAutomatico();
                if (v === 'manual') {
                    var taxa = parseFloat(document.getElementById('taxa_juros_manual').value) || 0;
                    juros = jurosManual(taxa);
                }
                if (v === 'fixo') juros = parseBRL(document.getElementById('valor_juros_fixo').value);
                document.getElementById('total_principal_sel').textContent = fmt(principal);
                document.getElementById('juros_auto_sel').textContent = fmt(jurosAutomatico());
                document.getElementById('total_auto_sel').textContent = fmt(principal + jurosAutomatico());
                document.getElementById('juros_manual_sel').textContent = fmt(juros);
                document.getElementById('total_manual_sel').textContent = fmt(principal + juros);
                document.getElementById('total_fixo_sel').textContent = fmt(principal + juros);
                document.getElementById('valor_total_exibir').value = fmt(principal + juros);

                var campoManual = document.getElementById('campo_taxa_manual');
                var campoFixo = document.getElementById('campo_valor_fixo');
                if (campoManual) campoManual.style.display = v === 'manual' ? 'block' : 'none';
                if (campoFixo) campoFixo.style.display = v === 'fixo' ? 'block' : 'none';
            }

            document.querySelectorAll('.parcela-check').forEach(function(cb) {
                cb.addEventListener('change', atualizar);
            });
            document.getElementById('check_todas').addEventListener('change', function() {
                var on = this.checked;
                document.querySelectorAll('.parcela-check').forEach(function(cb) { cb.checked = on; });
                atualizar();
            });
            document.querySelectorAll('input[name="tipo_juros"]').forEach(function(r) {
                r.addEventListener('change', atualizar);
            });
            var taxaManual = document.getElementById('taxa_juros_manual');
            if (taxaManual) taxaManual.addEventListener('input', atualizar);
            var valorFixo = document.getElementById('valor_juros_fixo');
            if (valorFixo) {
                valorFixo.addEventListener('input', atualizar);
                valorFixo.addEventListener('blur', atualizar);
            }

            document.getElementById('formMultiParcelas').addEventListener('submit', function(e) {
                if (getSelecionadas().length < 2) {
                    e.preventDefault();
                    if (typeof showError === 'function') showError('Selecione pelo menos duas parcelas.');
                    else if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione pelo menos duas parcelas.', confirmButtonColor: '#038edc' });
                    else alert('Selecione pelo menos duas parcelas.');
                }
            });

            atualizar();
        });
    </script>
    @endsection
@endsection
