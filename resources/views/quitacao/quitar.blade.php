@extends('layouts.master')
@section('title')
    Quitar Empréstimo #{{ $emprestimo->id }}
@endsection
@section('page-title')
    Quitar Empréstimo #{{ $emprestimo->id }}
@endsection
@section('body')<body>@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Quitar empréstimo por completo</h4>
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
                        <strong>Empréstimo #{{ $emprestimo->id }}</strong> – {{ $emprestimo->cliente->nome }}<br>
                        <strong>Operação:</strong> {{ $emprestimo->operacao->nome }}<br>
                        <strong>Saldo devedor:</strong>
                        <span class="h5 text-primary ms-1">R$ {{ number_format($saldoDevedor, 2, ',', '.') }}</span>
                    </div>

                    @if($parcelasAbertas->isNotEmpty())
                        <p class="text-muted small mb-4">
                            {{ $parcelasAbertas->count() }} parcela(s) em aberto. O valor informado será distribuído por ordem de vencimento.
                        </p>
                    @endif

                    <form action="{{ route('quitacao.store') }}" method="POST" enctype="multipart/form-data" id="formQuitacao" data-no-loading>
                        @csrf
                        <input type="hidden" name="emprestimo_id" value="{{ $emprestimo->id }}">

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="card border-secondary">
                                    <div class="card-body">
                                        <h6 class="card-title text-secondary"><i class="bx bx-info-circle"></i> Pagar com valor inferior</h6>
                                        <p class="text-muted small mb-2">Opcional. Se informar um valor menor que o saldo devedor (R$ {{ number_format($saldoDevedor, 2, ',', '.') }}), a quitação será enviada para aprovação do gestor ou administrador em Liberações. O valor não pode ser menor que o valor emprestado (R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}). O valor informado aparecerá no "Valor total" abaixo.</p>
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <label class="form-label">Valor a pagar (R$)</label>
                                                <input type="text" name="valor_solicitado" id="valor_solicitado" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Deixe em branco para pagar o total">
                                            </div>
                                            <div class="col-md-7">
                                                <label class="form-label">Motivo <span class="text-muted">(obrigatório quando valor inferior)</span></label>
                                                <textarea name="motivo_desconto" id="motivo_desconto" class="form-control" rows="2" maxlength="1000" placeholder="Obrigatório quando o valor a pagar for inferior ao saldo devedor (mín. 10 caracteres)">{{ old('motivo_desconto') }}</textarea>
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
                                           value="{{ number_format($saldoDevedor, 2, ',', '.') }}"
                                           placeholder="0,00">
                                </div>
                                <small class="text-muted">Valor total a pagar. Se preencheu "Valor a pagar" acima, mostra esse valor; senão mostra o saldo devedor.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data do pagamento <span class="text-danger">*</span></label>
                                <input type="date" name="data_pagamento" class="form-control" required
                                       value="{{ old('data_pagamento', now()->format('Y-m-d')) }}">
                                @error('data_pagamento')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Método <span class="text-danger">*</span></label>
                                <select name="metodo" class="form-select" required>
                                    <option value="dinheiro" {{ old('metodo', 'dinheiro') === 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                                    <option value="pix" {{ old('metodo') === 'pix' ? 'selected' : '' }}>PIX</option>
                                    <option value="transferencia" {{ old('metodo') === 'transferencia' ? 'selected' : '' }}>Transferência</option>
                                    <option value="outro" {{ old('metodo') === 'outro' ? 'selected' : '' }}>Outro</option>
                                </select>
                                @error('metodo')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Comprovante</label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG ou PNG, até 2 MB. O comprovante ficará anexado a cada parcela quitada.</small>
                                @error('comprovante')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="2" maxlength="1000">{{ old('observacoes') }}</textarea>
                                @error('observacoes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <input type="hidden" name="valor" id="valor" value="{{ number_format($saldoDevedor, 2, '.', '') }}">

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-check-circle"></i> <span id="btnTexto">Quitar empréstimo</span>
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
            var saldoDevedor = {{ number_format($saldoDevedor, 2, '.', '') }};
            var valorEmprestado = {{ number_format($emprestimo->valor_total, 2, '.', '') }};

            function parseBRL(str) {
                if (!str || typeof str !== 'string') return 0;
                var s = str.replace(/\s/g, '').replace(/R\$\s?/gi, '').trim();
                if (!s) return 0;
                s = s.replace(/\./g, '').replace(',', '.');
                var n = parseFloat(s);
                return isNaN(n) ? 0 : n;
            }

            function fmt(v) {
                return (typeof v === 'number' ? v : parseFloat(v)).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            var valorTotalExibir = document.getElementById('valor_total_exibir');
            var valorHidden = document.getElementById('valor');
            var valorSolicitadoInput = document.getElementById('valor_solicitado');
            var btnTexto = document.getElementById('btnTexto');

            function atualizarValorTotal() {
                var vInferior = valorSolicitadoInput ? parseBRL(valorSolicitadoInput.value) : 0;
                var total = (vInferior > 0) ? vInferior : saldoDevedor;
                if (valorTotalExibir) valorTotalExibir.value = fmt(total);
                if (valorHidden) valorHidden.value = total;
                var ehInferior = vInferior > 0 && vInferior < saldoDevedor;
                if (btnTexto) btnTexto.textContent = ehInferior ? 'Enviar para aprovação' : 'Quitar empréstimo';
            }

            if (valorSolicitadoInput) {
                valorSolicitadoInput.addEventListener('input', atualizarValorTotal);
                valorSolicitadoInput.addEventListener('keyup', atualizarValorTotal);
                valorSolicitadoInput.addEventListener('blur', atualizarValorTotal);
            }

            var form = document.getElementById('formQuitacao');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var valorSolicitado = valorSolicitadoInput ? parseBRL(valorSolicitadoInput.value) : 0;
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
                    atualizarValorTotal();
                    var msg = ehValorInferior
                        ? 'O valor informado é inferior ao saldo devedor. Esta quitação será enviada para autorização do gestor ou administrador (Liberações). Deseja continuar?'
                        : 'Confirma a quitação do empréstimo por completo?';
                    var msgAutorizacao = 'Esta quitação será enviada para autorização do gestor ou administrador (Liberações).';
                    var titulo = ehValorInferior ? 'Quitação com valor inferior' : 'Confirmar quitação';
                    if (typeof showConfirm === 'function') {
                        showConfirm({ title: titulo, text: msg, confirmText: 'Sim, continuar' }).then(function(result) {
                            if (result && result.isConfirmed) form.submit();
                        });
                    } else if (typeof Swal !== 'undefined') {
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
                });
            }

            atualizarValorTotal();
        });
    </script>
    @endsection
@endsection
