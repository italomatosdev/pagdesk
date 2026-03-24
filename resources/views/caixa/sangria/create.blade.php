@extends('layouts.master')
@section('title')
    Sangria — Caixa da Operação
@endsection
@section('page-title')
    Sangria para o Caixa da Operação
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
                        <i class="bx bx-down-arrow-alt"></i> Transferir do seu caixa para o Caixa da Operação
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Será registrada uma <strong>saída</strong> no seu caixa e uma <strong>entrada</strong> no Caixa da Operação (mesmo valor).
                        O valor não pode ser maior que o seu saldo disponível na operação.
                    </p>

                    <form method="POST" action="{{ route('caixa.sangria.store') }}" id="form-sangria" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Operação <span class="text-danger">*</span></label>
                            <select name="operacao_id" id="sangria_operacao_id" class="form-select" required>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}"
                                        data-saldo="{{ $saldosPorOperacao[$op->id] ?? 0 }}"
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
                            <p class="mb-1 text-muted small">Seu saldo nesta operação</p>
                            @php
                                $opSel = old('operacao_id', $operacaoIdDefault ?? $operacoes->first()->id);
                                $saldoIni = (float) ($saldosPorOperacao[$opSel] ?? 0);
                            @endphp
                            <p class="fs-5 mb-0 fw-semibold text-primary" id="sangria-saldo-exibicao">
                                R$ {{ number_format($saldoIni, 2, ',', '.') }}
                            </p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Valor da sangria <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" name="valor" id="sangria_valor" class="form-control" inputmode="decimal"
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
                            <small class="text-muted">PDF ou imagem (máx. 2 MB). Será vinculado aos dois lançamentos da sangria.</small>
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
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-check"></i> Confirmar sangria
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
    var sel = document.getElementById('sangria_operacao_id');
    var saldoEl = document.getElementById('sangria-saldo-exibicao');
    var saldos = {};
    @foreach($operacoes as $op)
    saldos[{{ $op->id }}] = {{ json_encode((float) ($saldosPorOperacao[$op->id] ?? 0)) }};
    @endforeach
    function fmt(v) {
        return v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function atualizarSaldo() {
        if (!sel || !saldoEl) return;
        var id = parseInt(sel.value, 10);
        var s = saldos[id] !== undefined ? saldos[id] : 0;
        saldoEl.textContent = 'R$ ' + fmt(s);
    }
    if (sel) {
        sel.addEventListener('change', atualizarSaldo);
        atualizarSaldo();
    }
});
</script>
@endsection
