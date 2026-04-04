@extends('layouts.master')
@section('title')
    Liberações de Dinheiro
@endsection
@section('page-title')
    Liberações Pendentes
@endsection
@section('body')
    <body>
@endsection
    @section('content')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h4 class="card-title mb-0">Liberações Aguardando</h4>
                            @php
                                $pendentesProdutoObjeto = \App\Modules\Loans\Models\Pagamento::where('metodo', 'produto_objeto')->whereNull('aceite_gestor_id')->whereNull('rejeitado_por_id');
                                $pendentesJurosParcial = \App\Modules\Loans\Models\SolicitacaoPagamentoJurosParcial::where('status', 'aguardando');
                                $pendentesJurosContratoReduzido = \App\Modules\Loans\Models\SolicitacaoPagamentoJurosContratoReduzido::where('status', 'aguardando');
                                $pendentesRenovacaoAbate = \App\Modules\Loans\Models\SolicitacaoRenovacaoAbate::where('status', 'aguardando');
                                $pendentesDiariaParcial = \App\Modules\Loans\Models\SolicitacaoPagamentoDiariaParcial::where('status', 'aguardando');
                                $pendentesQuitacaoDesconto = \App\Modules\Loans\Models\SolicitacaoQuitacao::where('status', 'pendente');
                                $pendentesNegociacao = \App\Modules\Loans\Models\SolicitacaoNegociacao::where('status', 'pendente');
                                $pendentesRetroativo = \App\Modules\Loans\Models\SolicitacaoEmprestimoRetroativo::where('status', 'aguardando');
                                $aplicarFiltroOps = !auth()->user()->isSuperAdmin();
                                if ($aplicarFiltroOps) {
                                    $opsIds = auth()->user()->getOperacoesIds();
                                    if (!empty($opsIds)) {
                                        $pendentesProdutoObjeto->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                        $pendentesJurosParcial->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                        $pendentesJurosContratoReduzido->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                        $pendentesRenovacaoAbate->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                        $pendentesDiariaParcial->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                        $pendentesQuitacaoDesconto->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                        $pendentesNegociacao->whereIn('operacao_id', $opsIds);
                                        $pendentesRetroativo->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
                                    } else {
                                        $pendentesProdutoObjeto->whereRaw('1 = 0');
                                        $pendentesJurosParcial->whereRaw('1 = 0');
                                        $pendentesJurosContratoReduzido->whereRaw('1 = 0');
                                        $pendentesRenovacaoAbate->whereRaw('1 = 0');
                                        $pendentesDiariaParcial->whereRaw('1 = 0');
                                        $pendentesQuitacaoDesconto->whereRaw('1 = 0');
                                        $pendentesNegociacao->whereRaw('1 = 0');
                                        $pendentesRetroativo->whereRaw('1 = 0');
                                    }
                                }
                                $countProdutoObjeto = $pendentesProdutoObjeto->count();
                                $countJurosParcial = $pendentesJurosParcial->count();
                                $countJurosContratoReduzido = $pendentesJurosContratoReduzido->count();
                                $countRenovacaoAbate = $pendentesRenovacaoAbate->count();
                                $countDiariaParcial = $pendentesDiariaParcial->count();
                                $countQuitacaoDesconto = $pendentesQuitacaoDesconto->count();
                                $countNegociacao = $pendentesNegociacao->count();
                                $countRetroativo = $pendentesRetroativo->count();
                            @endphp
                            <a href="{{ route('liberacoes.negociacoes') }}" class="btn btn-outline-dark btn-sm">
                                <i class="bx bx-transfer-alt"></i> Negociações
                                @if($countNegociacao > 0)
                                    <span class="badge bg-dark">{{ $countNegociacao }}</span>
                                @endif
                            </a>
                            <a href="{{ route('liberacoes.renovacao-abate') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-refresh"></i> Renovação abate
                                @if($countRenovacaoAbate > 0)
                                    <span class="badge bg-primary">{{ $countRenovacaoAbate }}</span>
                                @endif
                            </a>
                            <a href="{{ route('liberacoes.juros-contrato-reduzido') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bx bx-down-arrow-alt"></i> Juros contrato reduzido
                                @if($countJurosContratoReduzido > 0)
                                    <span class="badge bg-secondary">{{ $countJurosContratoReduzido }}</span>
                                @endif
                            </a>
                            <a href="{{ route('liberacoes.juros-parcial') }}" class="btn btn-outline-info btn-sm">
                                <i class="bx bx-percentage"></i> Juros parcial
                                @if($countJurosParcial > 0)
                                    <span class="badge bg-info">{{ $countJurosParcial }}</span>
                                @endif
                            </a>
                            <a href="{{ route('liberacoes.diaria-parcial') }}" class="btn btn-outline-info btn-sm">
                                <i class="bx bx-calendar"></i> Diária parcial
                                @if($countDiariaParcial > 0)
                                    <span class="badge bg-info">{{ $countDiariaParcial }}</span>
                                @endif
                            </a>
                            <a href="{{ route('liberacoes.pagamentos-produto-objeto') }}" class="btn btn-outline-warning btn-sm">
                                <i class="bx bx-package"></i> Pagamentos produto/objeto
                                @if($countProdutoObjeto > 0)
                                    <span class="badge bg-warning text-dark">{{ $countProdutoObjeto }}</span>
                                @endif
                            </a>
                            <a href="{{ route('quitacao.pendentes') }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-check-double"></i> Quitação com desconto
                                @if($countQuitacaoDesconto > 0)
                                    <span class="badge bg-success">{{ $countQuitacaoDesconto }}</span>
                                @endif
                            </a>
                            <a href="{{ route('emprestimos.retroativo.pendentes') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bx bx-history"></i> Empréstimos retroativos
                                @if($countRetroativo > 0)
                                    <span class="badge bg-secondary">{{ $countRetroativo }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('liberacoes.index') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="operacao_id" class="form-select">
                                        <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas as Operações</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ (int) ($operacaoId ?? 0) === (int) $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="mb-3">
                            <button type="button" id="btn-liberar-lote" class="btn btn-success btn-sm" disabled title="Selecione uma ou mais liberações">
                                <i class="bx bx-check-double"></i> Liberar selecionados
                            </button>
                        </div>

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="select-all-liberacoes" class="form-check-input" title="Selecionar todos">
                                        </th>
                                        <th>ID</th>
                                        <th>Empréstimo</th>
                                        <th>Tipo</th>
                                        <th>Frequência</th>
                                        <th>Cliente</th>
                                        <th>Outras ops</th>
                                        <th>Renovação</th>
                                        <th>Operação</th>
                                        <th>Consultor</th>
                                        <th>Valor</th>
                                        <th>Próx. venc.</th>
                                        <th>Criado em</th>
                                        <th>Comprovante</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($liberacoes as $liberacao)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="liberacao_ids[]" value="{{ $liberacao->id }}" 
                                                       class="form-check-input liberacao-check" 
                                                       data-valor="{{ number_format($liberacao->valor_liberado, 2, ',', '.') }}"
                                                       data-consultor="{{ $liberacao->consultor->name }}"
                                                       data-cliente="{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($liberacao->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}"
                                                       data-emprestimo-id="{{ $liberacao->emprestimo_id }}">
                                            </td>
                                            <td>#{{ $liberacao->id }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $liberacao->emprestimo_id) }}">
                                                    #{{ $liberacao->emprestimo_id }}
                                                </a>
                                            </td>
                                            <td>
                                                @php
                                                    $tipoLabels = [
                                                        'dinheiro' => 'Dinheiro',
                                                        'price' => 'Price',
                                                        'empenho' => 'Empenho',
                                                        'troca_cheque' => 'Troca Cheque',
                                                        'crediario' => 'Crediário',
                                                    ];
                                                @endphp
                                                {{ $tipoLabels[$liberacao->emprestimo->tipo] ?? ucfirst($liberacao->emprestimo->tipo) }}
                                            </td>
                                            <td>
                                                @php
                                                    $freqLabels = [
                                                        'diaria' => 'Diária',
                                                        'semanal' => 'Semanal',
                                                        'mensal' => 'Mensal',
                                                    ];
                                                @endphp
                                                {{ $freqLabels[$liberacao->emprestimo->frequencia] ?? ucfirst($liberacao->emprestimo->frequencia) }}
                                            </td>
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($liberacao->emprestimo->cliente_id, $liberacao->emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($liberacao->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $qtdOutrasOps = (int) ($outrosVinculosPorLiberacaoId[$liberacao->id] ?? 0);
                                                @endphp
                                                @if($qtdOutrasOps > 0)
                                                    <a href="{{ \App\Support\ClienteUrl::show($liberacao->emprestimo->cliente_id, $liberacao->emprestimo->operacao_id) }}" class="text-decoration-none" title="Ver cliente para detalhes">
                                                        <span class="badge bg-warning text-dark">Possui ({{ $qtdOutrasOps }})</span>
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center text-nowrap">
                                                @if($ehRenovacaoPorEmprestimoId[$liberacao->emprestimo_id] ?? false)
                                                    @include('partials.badge-renovacao-credito', ['ehRenovacao' => true])
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $liberacao->emprestimo->operacao->nome }}</td>
                                            <td>{{ $liberacao->consultor->name }}</td>
                                            <td class="h6 text-primary">
                                                R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}
                                            </td>
                                            <td>{{ $liberacao->emprestimo->getProximoVencimento()?->format('d/m/Y') ?? '—' }}</td>
                                            <td>{{ $liberacao->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                @if($liberacao->hasComprovanteLiberacao())
                                                    <a href="{{ $liberacao->comprovante_liberacao_url }}" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bx bx-file"></i> Ver
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="{{ route('liberacoes.show', $liberacao->id) }}" 
                                                       class="btn btn-sm btn-info" title="Ver Detalhes">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#liberarModal{{ $liberacao->id }}"
                                                            title="Liberar Dinheiro">
                                                        <i class="bx bx-check"></i> Liberar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="14" class="text-center">Nenhuma liberação aguardando.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-2">
                            {{ $liberacoes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modais de liberação individual (fora da table para não quebrar no mobile) -->
        @foreach($liberacoes as $liberacao)
        <div class="modal fade" id="liberarModal{{ $liberacao->id }}" tabindex="-1" aria-labelledby="liberarModalLabel{{ $liberacao->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('liberacoes.liberar', $liberacao->id) }}" 
                          method="POST" enctype="multipart/form-data"
                          class="form-liberar-dinheiro"
                          data-valor="{{ number_format($liberacao->valor_liberado, 2, ',', '.') }}"
                          data-consultor="{{ $liberacao->consultor->name }}"
                          data-cliente="{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($liberacao->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}"
                          data-emprestimo-id="{{ $liberacao->emprestimo_id }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="liberarModalLabel{{ $liberacao->id }}">Liberar Dinheiro</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>Valor:</strong> R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}<br>
                                <strong>Consultor:</strong> {{ $liberacao->consultor->name }}<br>
                                <strong>Cliente:</strong> {{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($liberacao->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}<br>
                                <strong>Empréstimo:</strong> #{{ $liberacao->emprestimo_id }}
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Comprovante (opcional)</label>
                                <input type="file" name="comprovante" class="form-control" 
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Formatos aceitos: PDF, JPG, PNG (máx. 2MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observações (opcional)</label>
                                <textarea name="observacoes" class="form-control" rows="3" 
                                          placeholder="Ex: Transferência realizada, comprovante anexado, etc."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Confirmar Liberação</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach

        <!-- Modal Liberar em Lote -->
        <div class="modal fade" id="modalLiberarLote" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="form-modal-liberar-lote" action="{{ route('liberacoes.liberar-lote') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Liberar em lote</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="lote-resumo" class="alert alert-info mb-3"></div>
                            <p class="text-muted small mb-3">Um único comprovante será anexado a todas as liberações. A observação incluirá automaticamente a lista dos empréstimos.</p>
                            <div class="mb-3">
                                <label class="form-label">Comprovante (obrigatório para lote)</label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="text-muted">Formatos: PDF, JPG, PNG (máx. 2MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observações adicionais (opcional)</label>
                                <textarea name="observacoes" class="form-control" rows="3" 
                                          placeholder="Ex: Transferência única realizada em 24/01/2026"></textarea>
                            </div>
                            <div id="lote-ids-container"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Confirmar liberação em lote</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Liberação individual
                document.querySelectorAll('.form-liberar-dinheiro').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const valor = this.dataset.valor;
                        const consultor = this.dataset.consultor;
                        const cliente = this.dataset.cliente;
                        const emprestimoId = this.dataset.emprestimoId;
                        
                        Swal.fire({
                            title: 'Liberar Dinheiro?',
                            html: `<strong>Valor:</strong> R$ ${valor}<br>
                                   <strong>Consultor:</strong> ${consultor}<br>
                                   <strong>Cliente:</strong> ${cliente}<br>
                                   <strong>Empréstimo:</strong> #${emprestimoId}`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, liberar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });

                // Liberação em lote
                var selectAll = document.getElementById('select-all-liberacoes');
                var btnLote = document.getElementById('btn-liberar-lote');
                var checks = document.querySelectorAll('.liberacao-check');
                var modal = document.getElementById('modalLiberarLote');
                var formModal = document.getElementById('form-modal-liberar-lote');
                var loteResumo = document.getElementById('lote-resumo');
                var loteIdsContainer = document.getElementById('lote-ids-container');

                function updateSelectAll() {
                    if (selectAll) {
                        var allChecked = Array.from(checks).every(c => c.checked);
                        var someChecked = Array.from(checks).some(c => c.checked);
                        selectAll.checked = allChecked && checks.length > 0;
                        selectAll.indeterminate = someChecked && !allChecked;
                    }
                }

                function updateBtnLote() {
                    var total = Array.from(checks).filter(c => c.checked).length;
                    btnLote.disabled = total === 0;
                    btnLote.title = total > 0 ? 'Liberar ' + total + ' liberação(ões) selecionada(s)' : 'Selecione uma ou mais liberações';
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        checks.forEach(c => { c.checked = selectAll.checked; });
                        updateBtnLote();
                    });
                }
                checks.forEach(function(c) {
                    c.addEventListener('change', function() {
                        updateSelectAll();
                        updateBtnLote();
                    });
                });

                if (btnLote) {
                    btnLote.addEventListener('click', function() {
                        var selecionados = Array.from(checks).filter(c => c.checked);
                        if (selecionados.length === 0) return;

                        var totalValor = 0;
                        var ids = [];
                        var html = '<strong>Liberações selecionadas:</strong><br><ul class="mb-0 mt-2">';
                        selecionados.forEach(function(el) {
                            var valorStr = (el.dataset.valor || '0').replace(/\./g, '').replace(',', '.');
                            var valor = parseFloat(valorStr) || 0;
                            totalValor += valor;
                            ids.push(el.value);
                            html += '<li>Empréstimo #' + el.dataset.emprestimoId + ' - R$ ' + el.dataset.valor + ' (' + el.dataset.consultor + ')</li>';
                        });
                        html += '</ul><strong class="mt-2 d-block">Total: R$ ' + totalValor.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + '</strong>';

                        loteResumo.innerHTML = html;
                        loteIdsContainer.innerHTML = '';
                        ids.forEach(function(id) {
                            var inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'liberacao_ids[]';
                            inp.value = id;
                            loteIdsContainer.appendChild(inp);
                        });

                        var bsModal = new bootstrap.Modal(modal);
                        bsModal.show();
                    });
                }

                formModal.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var comprovante = this.querySelector('input[name="comprovante"]');
                    if (!comprovante.files.length) {
                        Swal.fire({ icon: 'warning', title: 'Erro', text: 'O comprovante é obrigatório para liberação em lote.' });
                        return;
                    }
                    Swal.fire({
                        title: 'Confirmar liberação em lote?',
                        text: 'Todas as liberações selecionadas serão processadas com o mesmo comprovante.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim, liberar!',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });
        </script>
    @endsection