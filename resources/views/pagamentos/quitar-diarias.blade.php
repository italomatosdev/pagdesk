@extends('layouts.master')
@section('title')
    Quitar todas as parcelas diárias
@endsection
@section('page-title')
    Quitar todas as parcelas diárias
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Quitar todas as parcelas diárias</h4>
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
                        <strong>Parcelas pendentes:</strong> {{ $parcelasPendentes->count() }}<br>
                        Um único comprovante será associado a todos os pagamentos. Você pode quitar <strong>sem juros de atraso</strong> ou <strong>com juros</strong> (automático, manual ou valor fixo).
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Parcela</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Dias atraso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($parcelasPendentes as $p)
                                    @php $dias = $p->calcularDiasAtraso(now()); @endphp
                                    <tr>
                                        <td>#{{ $p->numero }}</td>
                                        <td>{{ $p->data_vencimento->format('d/m/Y') }}</td>
                                        <td>R$ {{ number_format($p->valor, 2, ',', '.') }}</td>
                                        <td>{{ $dias > 0 ? $dias . ' dias' : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mb-0"><strong>Total (principal):</strong> R$ {{ number_format($totalPrincipal, 2, ',', '.') }}</p>
                    </div>

                    <form action="{{ route('pagamentos.quitar-diarias.store', $emprestimo->id) }}" method="POST" enctype="multipart/form-data" id="formQuitarDiarias">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label"><strong>Juros de atraso</strong></label>
                            <div class="card border">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_nenhum" value="nenhum" checked class="form-check-input">
                                        <label for="tipo_juros_nenhum" class="form-check-label ms-2">
                                            <strong>Sem juros de atraso</strong> – Pagar apenas o valor das parcelas
                                        </label>
                                        <div class="ms-4 text-muted">
                                            Total: R$ <span id="total_nenhum">{{ number_format($totalPrincipal, 2, ',', '.') }}</span>
                                        </div>
                                    </div>

                                    @if($operacao->taxa_juros_atraso > 0)
                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_automatico" value="automatico" class="form-check-input">
                                        <label for="tipo_juros_automatico" class="form-check-label ms-2">
                                            <strong>Juros automático</strong> ({{ number_format($operacao->taxa_juros_atraso, 2, ',', '.') }}% {{ $operacao->tipo_calculo_juros === 'por_dia' ? 'ao dia' : 'ao mês' }})
                                        </label>
                                        <div class="ms-4 text-muted">
                                            Juros: R$ <span id="juros_automatico">{{ number_format($jurosAutomaticoTotal, 2, ',', '.') }}</span><br>
                                            <strong>Total: R$ <span id="total_automatico">{{ number_format($totalPrincipal + $jurosAutomaticoTotal, 2, ',', '.') }}</span></strong>
                                        </div>
                                    </div>
                                    @endif

                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_manual" value="manual" class="form-check-input">
                                        <label for="tipo_juros_manual" class="form-check-label ms-2">
                                            <strong>Juros manual</strong> – Informar taxa % no momento
                                        </label>
                                        <div class="ms-4 mt-2" id="campo_taxa_manual" style="display: none;">
                                            <div class="input-group" style="max-width: 280px;">
                                                <input type="number" name="taxa_juros_manual" id="taxa_juros_manual" class="form-control" step="0.01" min="0" max="100" placeholder="Ex: 2.5">
                                                <span class="input-group-text">% {{ $operacao->tipo_calculo_juros === 'por_mes' ? 'ao mês' : 'ao dia' }}</span>
                                            </div>
                                            <div class="text-muted mt-2">
                                                Juros: R$ <span id="juros_manual">0,00</span><br>
                                                <strong>Total: R$ <span id="total_manual">{{ number_format($totalPrincipal, 2, ',', '.') }}</span></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <input type="radio" name="tipo_juros" id="tipo_juros_fixo" value="fixo" class="form-check-input">
                                        <label for="tipo_juros_fixo" class="form-check-label ms-2">
                                            <strong>Juros (valor fixo)</strong> – Informar valor total em R$
                                        </label>
                                        <div class="ms-4 mt-2" id="campo_valor_fixo" style="display: none;">
                                            <div class="input-group" style="max-width: 280px;">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="valor_juros_fixo" id="valor_juros_fixo" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Ex: 50,00">
                                            </div>
                                            <div class="text-muted mt-2">
                                                Juros: R$ <span id="juros_fixo_exibir">0,00</span><br>
                                                <strong>Total: R$ <span id="total_fixo">{{ number_format($totalPrincipal, 2, ',', '.') }}</span></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="card border-secondary">
                                    <div class="card-body">
                                        <h6 class="card-title text-secondary"><i class="bx bx-info-circle"></i> Pagar com valor inferior</h6>
                                        <p class="text-muted small mb-2">Opcional. Se informar um valor menor que o saldo devedor (R$ {{ number_format($saldoDevedor ?? 0, 2, ',', '.') }}), a quitação será enviada para aprovação do gestor ou administrador. O valor não pode ser menor que o valor emprestado (R$ {{ number_format($valorEmprestado ?? 0, 2, ',', '.') }}). O valor informado aparecerá no "Valor total" abaixo.</p>
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <label class="form-label">Valor a pagar (R$)</label>
                                                <input type="text" name="valor_solicitado" id="valor_solicitado" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Deixe em branco para pagar o total">
                                            </div>
                                            <div class="col-md-7">
                                                <label class="form-label">Motivo <span class="text-muted">(obrigatório quando valor inferior)</span></label>
                                                <textarea name="motivo_desconto" id="motivo_desconto" class="form-control" rows="2" placeholder="Obrigatório quando o valor a pagar for inferior ao saldo devedor (mín. 10 caracteres)">{{ old('motivo_desconto') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Valor total</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" id="valor_total_exibir" class="form-control bg-light" readonly
                                           value="{{ number_format($totalPrincipal, 2, ',', '.') }}"
                                           placeholder="0,00">
                                </div>
                                <small class="text-muted">Valor total a pagar. Se preencheu "Valor a pagar" acima, mostra esse valor; senão mostra o total (principal + juros). Não editável.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Método de pagamento <span class="text-danger">*</span></label>
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
                                <label class="form-label">Comprovante (único para todas as parcelas)</label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">O mesmo comprovante será vinculado a todos os pagamentos.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="2">{{ old('observacoes') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-check"></i> Quitar todas as parcelas
                            </button>
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
            var totalPrincipal = {{ number_format($totalPrincipal, 2, '.', '') }};
            var jurosAutomatico = {{ number_format($jurosAutomaticoTotal, 2, '.', '') }};
            var saldoDevedor = {{ number_format($saldoDevedor ?? 0, 2, '.', '') }};
            var valorEmprestado = {{ number_format($valorEmprestado ?? 0, 2, '.', '') }};

            function fmt(v) {
                return (typeof v === 'number' ? v : parseFloat(v)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            /** Converte string BRL (R$ 1.234,56 ou 50,00) para número */
            function parseBRL(str) {
                if (!str || typeof str !== 'string') return 0;
                var s = str.replace(/\s/g, '').replace(/R\$\s?/gi, '').trim();
                if (!s) return 0;
                s = s.replace(/\./g, '').replace(',', '.');
                var n = parseFloat(s);
                return isNaN(n) ? 0 : n;
            }

            var valorTotalExibir = document.getElementById('valor_total_exibir');
            var campoManual = document.getElementById('campo_taxa_manual');
            var campoFixo = document.getElementById('campo_valor_fixo');

            function atualizarValorTotalExibir(total) {
                if (valorTotalExibir) valorTotalExibir.value = fmt(total);
            }

            function getTotalAtual() {
                var t = document.querySelector('input[name="tipo_juros"]:checked');
                var v = t ? t.value : 'nenhum';
                if (v === 'nenhum') return totalPrincipal;
                if (v === 'automatico') return totalPrincipal + jurosAutomatico;
                if (v === 'manual') {
                    var taxaEl = document.getElementById('taxa_juros_manual');
                    var taxa = taxaEl ? (parseFloat(taxaEl.value) || 0) : 0;
                    var juros = totalPrincipal * (taxa / 100) * 1;
                    return totalPrincipal + juros;
                }
                if (v === 'fixo') {
                    var inp = document.getElementById('valor_juros_fixo');
                    var j = inp ? parseBRL(inp.value) : 0;
                    return totalPrincipal + j;
                }
                return totalPrincipal;
            }

            /** Atualiza "Valor total": se tiver valor inferior preenchido, usa ele; senão usa o total calculado (juros). */
            function atualizarValorTotalExibirCompleto() {
                var inpInferior = document.getElementById('valor_solicitado');
                var vInferior = inpInferior ? parseBRL(inpInferior.value) : 0;
                var total = (vInferior > 0) ? vInferior : getTotalAtual();
                atualizarValorTotalExibir(total);
            }

            function toggleCampos() {
                var t = document.querySelector('input[name="tipo_juros"]:checked');
                var v = t ? t.value : 'nenhum';
                if (campoManual) campoManual.style.display = v === 'manual' ? 'block' : 'none';
                if (campoFixo) campoFixo.style.display = v === 'fixo' ? 'block' : 'none';
                atualizarValorTotalExibirCompleto();
            }

            var radios = document.querySelectorAll('input[name="tipo_juros"]');
            if (radios.length) {
                radios.forEach(function(r) {
                    r.addEventListener('change', toggleCampos);
                });
            }

            var manualInput = document.getElementById('taxa_juros_manual');
            if (manualInput) {
                manualInput.addEventListener('input', function() {
                    var taxa = parseFloat(this.value) || 0;
                    var juros = totalPrincipal * (taxa / 100) * 1;
                    var jEl = document.getElementById('juros_manual');
                    var totEl = document.getElementById('total_manual');
                    if (jEl) jEl.textContent = fmt(juros);
                    if (totEl) totEl.textContent = fmt(totalPrincipal + juros);
                    atualizarValorTotalExibirCompleto();
                });
            }

            var fixoInput = document.getElementById('valor_juros_fixo');
            if (fixoInput) {
                function atualizarFixo() {
                    var v = parseBRL(fixoInput.value);
                    var jEl = document.getElementById('juros_fixo_exibir');
                    var totEl = document.getElementById('total_fixo');
                    if (jEl) jEl.textContent = fmt(v);
                    if (totEl) totEl.textContent = fmt(totalPrincipal + v);
                    atualizarValorTotalExibirCompleto();
                }
                fixoInput.addEventListener('input', atualizarFixo);
                fixoInput.addEventListener('keyup', atualizarFixo);
                fixoInput.addEventListener('blur', atualizarFixo);
            }

            var valorSolicitadoInput = document.getElementById('valor_solicitado');
            if (valorSolicitadoInput) {
                function atualizarPorValorInferior() {
                    atualizarValorTotalExibirCompleto();
                }
                valorSolicitadoInput.addEventListener('input', atualizarPorValorInferior);
                valorSolicitadoInput.addEventListener('keyup', atualizarPorValorInferior);
                valorSolicitadoInput.addEventListener('blur', atualizarPorValorInferior);
            }

            var form = document.getElementById('formQuitarDiarias');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var valorSolicitado = document.getElementById('valor_solicitado') ? parseBRL(document.getElementById('valor_solicitado').value) : 0;
                    var ehValorInferior = valorSolicitado > 0 && saldoDevedor > 0 && valorSolicitado < saldoDevedor;
                    if (ehValorInferior) {
                        if (valorEmprestado > 0 && valorSolicitado < valorEmprestado) {
                            var errMsg = 'O valor não pode ser menor que o valor emprestado (R$ ' + valorEmprestado.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ').';
                            if (typeof showError === 'function') showError(errMsg); else if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Erro', text: errMsg, confirmButtonColor: '#038edc' }); else alert(errMsg);
                            return;
                        }
                        var motivoEl = document.getElementById('motivo_desconto');
                        var motivo = motivoEl ? String(motivoEl.value || '').trim() : '';
                        if (motivo.length < 10) {
                            var errMsg = 'Ao pagar com valor inferior ao saldo devedor, o motivo é obrigatório (mínimo 10 caracteres). Preencha o campo "Motivo do valor inferior".';
                            if (typeof showError === 'function') {
                                showError(errMsg);
                            } else if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'error', title: 'Erro', text: errMsg, confirmButtonColor: '#038edc' });
                            } else {
                                alert(errMsg);
                            }
                            if (motivoEl) { motivoEl.focus(); motivoEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                            return;
                        }
                    }
                    var msg = ehValorInferior
                        ? 'O valor informado é inferior ao saldo devedor. Esta quitação será enviada para autorização do gestor ou administrador (Liberações). Deseja continuar?'
                        : 'Confirma a quitação de todas as parcelas?';
                    var msgAutorizacao = 'Esta quitação será enviada para autorização do gestor ou administrador (Liberações).';
                    var titulo = ehValorInferior ? 'Quitação com valor inferior' : 'Confirmar quitação';
                    if (typeof showConfirm === 'function') {
                        showConfirm({ title: titulo, text: msg, confirmText: 'Sim, continuar' }).then(function(result) {
                            if (result && result.isConfirmed) form.submit();
                        });
                    } else {
                        if (typeof Swal !== 'undefined') {
                            var opts = { title: titulo, icon: 'question', showCancelButton: true, confirmButtonColor: '#038edc', cancelButtonText: 'Cancelar', confirmButtonText: 'Sim, continuar' };
                            if (ehValorInferior) {
                                opts.html = '<p class="mb-2">O valor informado é inferior ao saldo devedor.</p><p class="mb-2"><strong>' + msgAutorizacao + '</strong></p><p class="mb-0">Deseja continuar?</p>';
                            } else {
                                opts.text = msg;
                            }
                            Swal.fire(opts).then(function(result) {
                                if (result && result.isConfirmed) form.submit();
                            });
                        } else {
                            if (confirm(msg)) form.submit();
                        }
                    }
                });
            }

            toggleCampos();
        });
    </script>
@endsection
@endsection
