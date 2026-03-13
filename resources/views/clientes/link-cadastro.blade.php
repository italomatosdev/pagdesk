@extends('layouts.master')
@section('title')
    Link de cadastro para cliente
@endsection
@section('page-title')
    Link de cadastro para cliente
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Gerar link para o cliente preencher o cadastro</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Selecione a operação e copie o link abaixo. Envie ao cliente por WhatsApp, e-mail ou outro canal.
                        Ao abrir o link, o cliente preenche os dados e fica vinculado a você nesta operação.
                    </p>

                    <form method="get" action="{{ route('clientes.link-cadastro') }}" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Operação <span class="text-danger">*</span></label>
                            <select name="operacao_id" id="operacao_id" class="form-select" required
                                    onchange="this.form.submit()">
                                <option value="">Selecione a operação...</option>
                                @foreach($operacoes as $op)
                                    <option value="{{ $op->id }}" {{ ($operacaoSelecionadaId ?? '') == $op->id ? 'selected' : '' }}>
                                        {{ $op->nome }}@if($op->codigo) ({{ $op->codigo }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    @if($linkCadastro)
                        <div class="mb-3">
                            <label class="form-label">Link para enviar ao cliente</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="link-cadastro-url"
                                       value="{{ $linkCadastro }}" readonly>
                                <button type="button" class="btn btn-primary" id="btn-copiar-link" title="Copiar link">
                                    <i class="bx bx-copy"></i> Copiar
                                </button>
                            </div>
                        </div>
                    @else
                        <p class="text-muted mb-0">Selecione uma operação acima para gerar o link.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    @if($linkCadastro)
        <script>
            document.getElementById('btn-copiar-link').addEventListener('click', function () {
                var input = document.getElementById('link-cadastro-url');
                input.select();
                input.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(input.value).then(function () {
                    var btn = document.getElementById('btn-copiar-link');
                    var orig = btn.innerHTML;
                    btn.innerHTML = '<i class="bx bx-check"></i> Copiado!';
                    btn.classList.add('btn-success');
                    btn.classList.remove('btn-primary');
                    setTimeout(function () {
                        btn.innerHTML = orig;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-primary');
                    }, 2000);
                });
            });
        </script>
    @endif
@endsection
