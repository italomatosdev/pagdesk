@if(($liberacoesSemPagamentoClienteCount ?? 0) > 0)
    <div class="alert alert-warning {{ $alertClass ?? '' }}" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bx bx-error-circle fs-4 flex-shrink-0 mt-1"></i>
            <div class="flex-grow-1">
                <strong>Liberação(ões) sem pagamento ao cliente</strong>
                <p class="mb-2 small">
                    {{ $usuarioAlvo->name }} tem <strong>{{ $liberacoesSemPagamentoClienteCount }}</strong>
                    {{ $liberacoesSemPagamentoClienteCount === 1 ? 'empréstimo com dinheiro liberado' : 'empréstimos com dinheiro liberado' }}
                    e ainda <strong>sem confirmação de pagamento ao cliente</strong> nesta operação.
                    O caixa pode refletir entrada de liberação sem a saída correspondente até essa etapa ser concluída.
                </p>
                <ul class="mb-0 small list-unstyled">
                    @foreach($liberacoesSemPagamentoCliente as $lib)
                        @php
                            $emp = $lib->emprestimo;
                            $nomeCliente = $emp && $emp->cliente
                                ? \App\Support\ClienteNomeExibicao::forEmprestimo($emp)
                                : 'Cliente';
                        @endphp
                        <li class="mb-1">
                            <a href="{{ route('emprestimos.show', $lib->emprestimo_id) }}" class="alert-link" target="_blank" rel="noopener">
                                Empréstimo #{{ $lib->emprestimo_id }}
                            </a>
                            <span class="text-muted">— {{ $nomeCliente }}</span>
                            <span class="text-muted">(R$ {{ number_format((float) $lib->valor_liberado, 2, ',', '.') }})</span>
                            @if(auth()->user()->temAlgumPapelNaOperacao($operacao->id, ['gestor', 'administrador']))
                                · <a href="{{ route('liberacoes.show', $lib->id) }}" class="alert-link" target="_blank" rel="noopener">Ver liberação</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($liberacoesSemPagamentoClienteCount > $liberacoesSemPagamentoCliente->count())
                    <p class="mb-0 mt-2 small text-muted">
                        Listamos as {{ $liberacoesSemPagamentoCliente->count() }} mais recentes; há mais {{ $liberacoesSemPagamentoClienteCount - $liberacoesSemPagamentoCliente->count() }} no mesmo status.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
