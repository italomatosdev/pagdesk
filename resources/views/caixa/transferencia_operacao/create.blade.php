@extends('layouts.master')
@section('title')
    Transferência — Caixa da Operação
@endsection
@section('page-title')
    Transferência do Caixa da Operação
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
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

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-arrow-back"></i> Transferir do Caixa da Operação para um responsável
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">
                        Apenas <strong>administradores da operação</strong> podem executar. Será registrada uma
                        <strong>saída</strong> no Caixa da Operação e uma <strong>entrada</strong> no caixa do destinatário (mesmo valor).
                    </p>

                    <form method="POST" action="{{ route('caixa.transferencia_operacao.store') }}" id="form-transferencia-operacao" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Operação <span class="text-danger">*</span></label>
                            <select name="operacao_id" id="transf_operacao_id" class="form-select" required>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}"
                                        data-saldo="{{ $saldosCaixaOperacao[$op->id] ?? 0 }}"
                                        {{ (int) old('operacao_id', $operacaoIdDefault ?? $operacoes->first()->id) === (int) $op->id ? 'selected' : '' }}>
                                        {{ $op->nome }}
                                    </option>
                                @endforeach
                            </select>
                            @error('operacao_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <p class="mb-1 text-muted small">Saldo do Caixa da Operação nesta operação</p>
                            @php
                                $opSel = old('operacao_id', $operacaoIdDefault ?? $operacoes->first()->id);
                                $saldoIni = (float) ($saldosCaixaOperacao[$opSel] ?? 0);
                            @endphp
                            <p class="fs-5 mb-0 fw-semibold text-primary" id="transf-saldo-exibicao">
                                R$ {{ number_format($saldoIni, 2, ',', '.') }}
                            </p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Destinatário <span class="text-danger">*</span></label>
                            <select name="destinatario_id" id="transf_destinatario_id" class="form-select" required>
                                @foreach($usuariosDestinoPorOperacao[$opSel] ?? [] as $u)
                                    <option value="{{ $u['id'] }}" {{ (int) old('destinatario_id') === (int) $u['id'] ? 'selected' : '' }}>
                                        {{ $u['name'] }}{{ $u['id'] === auth()->id() ? ' (Você)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Gestor ou administrador vinculado à operação (pode ser você).</small>
                            @error('destinatario_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-warning d-none mb-3" id="transf-aviso-proprio" role="alert">
                            <i class="bx bx-info-circle"></i>
                            <strong>Atenção:</strong> o valor sairá do Caixa da Operação e entrará no <strong>seu caixa pessoal</strong> nesta operação.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Valor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" name="valor" id="transf_valor" class="form-control" inputmode="decimal"
                                       data-mask-money="brl" placeholder="0,00" value="{{ old('valor') }}" required>
                            </div>
                            @error('valor')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="2" maxlength="1000">{{ old('observacoes') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Comprovante <span class="text-muted">(opcional)</span></label>
                            <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">PDF ou imagem (máx. 2 MB). Será vinculado aos dois lançamentos.</small>
                            @error('comprovante')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-secondary small mb-3">
                            <i class="bx bx-info-circle"></i>
                            Este registro reflete a posição contábil no sistema. A conferência do dinheiro físico segue o processo da sua operação.
                        </div>

                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <a href="{{ route('caixa.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar
                            </a>
                            <button type="submit" class="btn btn-primary" id="transf-btn-submit">
                                <i class="bx bx-check"></i> Confirmar transferência
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function() {
    var selOp = document.getElementById('transf_operacao_id');
    var selDest = document.getElementById('transf_destinatario_id');
    var saldoEl = document.getElementById('transf-saldo-exibicao');
    var avisoProprio = document.getElementById('transf-aviso-proprio');
    var form = document.getElementById('form-transferencia-operacao');
    var meuId = {{ (int) auth()->id() }};

    var saldos = {};
    @foreach($operacoes as $op)
    saldos[{{ $op->id }}] = {{ json_encode((float) ($saldosCaixaOperacao[$op->id] ?? 0)) }};
    @endforeach

    var usuariosPorOp = @json($usuariosDestinoPorOperacao);

    function fmt(v) {
        return v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function atualizarSaldo() {
        if (!selOp || !saldoEl) return;
        var id = parseInt(selOp.value, 10);
        var s = saldos[id] !== undefined ? saldos[id] : 0;
        saldoEl.textContent = 'R$ ' + fmt(s);
    }

    function popularDestinatarios() {
        if (!selOp || !selDest) return;
        var opId = selOp.value;
        var lista = usuariosPorOp[opId] || [];
        var oldDest = {{ (int) old('destinatario_id', 0) }};
        selDest.innerHTML = '';
        var found = false;
        lista.forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name + (u.id === meuId ? ' (Você)' : '');
            if (oldDest && u.id === oldDest) {
                opt.selected = true;
                found = true;
            }
            selDest.appendChild(opt);
        });
        if (!found && lista.length) {
            selDest.selectedIndex = 0;
        }
        toggleAvisoProprio();
    }

    function toggleAvisoProprio() {
        if (!avisoProprio || !selDest) return;
        var self = parseInt(selDest.value, 10) === meuId;
        avisoProprio.classList.toggle('d-none', !self);
    }

    if (selOp) {
        selOp.addEventListener('change', function() {
            atualizarSaldo();
            popularDestinatarios();
        });
        if (selDest) selDest.addEventListener('change', toggleAvisoProprio);
        atualizarSaldo();
        popularDestinatarios();
    }

    if (form && typeof Swal !== 'undefined') {
        form.addEventListener('submit', function onSubmitTransferencia(e) {
            e.preventDefault();
            var self = selDest && parseInt(selDest.value, 10) === meuId;
            Swal.fire({
                title: 'Confirmar transferência?',
                html: self
                    ? 'O valor sairá do <strong>Caixa da Operação</strong> e entrará no <strong>seu caixa pessoal</strong> nesta operação.'
                    : 'Será registrada uma <strong>saída</strong> no Caixa da Operação e uma <strong>entrada</strong> no caixa do destinatário (mesmo valor).',
                icon: self ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, confirmar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                focusCancel: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.removeEventListener('submit', onSubmitTransferencia);
                    form.submit();
                }
            });
        });
    }
});
</script>
@endsection
