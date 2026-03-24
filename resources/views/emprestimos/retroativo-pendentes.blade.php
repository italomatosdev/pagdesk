@extends('layouts.master')
@section('title')
    Empréstimos retroativos – Aguardando aceite
@endsection
@section('page-title')
    Empréstimos retroativos – Aguardando aceite
@endsection
@section('body')<body>@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h4 class="card-title mb-0">Empréstimos retroativos criados por consultor</h4>
                            <p class="text-muted small mb-0 mt-1">Aprove ou rejeite os empréstimos retroativos. Após aprovação, o empréstimo ficará ativo e o consultor poderá registrar parcelas já pagas.</p>
                        </div>
                        <a href="{{ route('liberacoes.index') }}" class="btn btn-secondary btn-sm">
                            <i class="bx bx-arrow-back"></i> Voltar a Liberações
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if(($operacoes ?? collect())->isNotEmpty())
                    <form method="GET" action="{{ route('emprestimos.retroativo.pendentes') }}" class="mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" class="form-select">
                                    <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"><i class="bx bx-filter"></i> Filtrar</button>
                                <a href="{{ route('emprestimos.retroativo.pendentes') }}" class="btn btn-light"><i class="bx bx-reset"></i> Limpar</a>
                            </div>
                        </div>
                    </form>
                    @endif

                    @if($solicitacoes->isEmpty())
                        <p class="text-muted mb-0">Nenhum empréstimo retroativo aguardando aceite.</p>
                    @else
                        <form action="{{ route('emprestimos.retroativo.aprovar-lote') }}" method="POST" id="form-aprovar-lote-retroativo">
                            @csrf
                            <div class="mb-3">
                                <button type="submit" class="btn btn-success btn-sm" id="btn-aprovar-lote-retroativo" disabled title="Selecione uma ou mais solicitações">
                                    <i class="bx bx-check-double"></i> Aprovar selecionados
                                </button>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 2.5rem;">
                                            <input type="checkbox" class="form-check-input" id="check-todos-retroativo" title="Selecionar todos da página">
                                        </th>
                                        <th>Empréstimo / Cliente</th>
                                        <th>Operação</th>
                                        <th>Solicitante</th>
                                        <th>Valor</th>
                                        <th>Próx. venc.</th>
                                        <th>Data solicitação</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($solicitacoes as $s)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ids[]" value="{{ $s->id }}" class="form-check-input check-retroativo">
                                            </td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $s->emprestimo_id) }}">#{{ $s->emprestimo_id }}</a>
                                                <br><small class="text-muted">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($s->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</small>
                                            </td>
                                            <td>{{ $s->emprestimo->operacao->nome ?? '-' }}</td>
                                            <td>{{ $s->solicitante->name ?? '-' }}</td>
                                            <td>R$ {{ number_format($s->emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>{{ $s->emprestimo->getProximoVencimento()?->format('d/m/Y') ?? '—' }}</td>
                                            <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('emprestimos.show', $s->emprestimo_id) }}" class="btn btn-outline-primary btn-sm rounded-end-0" title="Ver empréstimo">
                                                        <i class="bx bx-show"></i> Ver
                                                    </a>
                                                    <form action="{{ route('emprestimos.retroativo.aprovar', $s->id) }}" method="POST" class="d-inline-block m-0 p-0 border-0 align-middle" style="margin: 0 !important; padding: 0 !important;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm rounded-0" onclick="return confirm('Aprovar este empréstimo retroativo? O empréstimo ficará ativo.');">
                                                            <i class="bx bx-check"></i> Aprovar
                                                        </button>
                                                    </form><button type="button" class="btn btn-outline-danger btn-sm rounded-start-0" data-bs-toggle="modal" data-bs-target="#rejeitarModal{{ $s->id }}">
                                                        <i class="bx bx-x"></i> Rejeitar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="rejeitarModal{{ $s->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('emprestimos.retroativo.rejeitar', $s->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Rejeitar empréstimo retroativo</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Empréstimo #{{ $s->emprestimo_id }} – {{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($s->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}. O empréstimo será cancelado.</p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Motivo da rejeição <span class="text-danger">*</span></label>
                                                                <textarea name="motivo_rejeicao" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="Informe o motivo (mín. 5 caracteres)"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-danger">Rejeitar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <script>
                        (function() {
                            var form = document.getElementById('form-aprovar-lote-retroativo');
                            var btn = document.getElementById('btn-aprovar-lote-retroativo');
                            var checkTodos = document.getElementById('check-todos-retroativo');
                            var checks = document.querySelectorAll('.check-retroativo');
                            function atualizarBtn() {
                                var n = 0;
                                checks.forEach(function(c) { if (c.checked) n++; });
                                if (btn) btn.disabled = n === 0;
                            }
                            if (checkTodos) {
                                checkTodos.addEventListener('change', function() {
                                    checks.forEach(function(c) { c.checked = checkTodos.checked; });
                                    atualizarBtn();
                                });
                            }
                            checks.forEach(function(c) {
                                c.addEventListener('change', atualizarBtn);
                            });
                            if (form) {
                                form.addEventListener('submit', function(e) {
                                    var ids = [];
                                    checks.forEach(function(c) { if (c.checked) ids.push(c.value); });
                                    if (ids.length === 0) { e.preventDefault(); return; }
                                    if (!confirm('Aprovar ' + ids.length + ' empréstimo(s) retroativo(s) selecionado(s)? Os empréstimos ficarão ativos.')) { e.preventDefault(); return; }
                                    ids.forEach(function(id) {
                                        var input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = 'ids[]';
                                        input.value = id;
                                        form.appendChild(input);
                                    });
                                });
                            }
                        })();
                        </script>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $solicitacoes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
