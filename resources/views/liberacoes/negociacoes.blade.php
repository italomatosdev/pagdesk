@extends('layouts.master')
@section('title')
    Negociações Pendentes
@endsection
@section('page-title')
    Negociações Pendentes
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
                            <h4 class="card-title mb-0">
                                <i class="bx bx-transfer-alt"></i> Negociações de Empréstimos – Aguardando Aprovação
                            </h4>
                            <a href="{{ route('liberacoes.index') }}" class="btn btn-secondary btn-sm">
                                <i class="bx bx-arrow-back"></i> Voltar às Liberações
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

                        <p class="text-muted mb-3">
                            O consultor solicitou <strong>negociar um empréstimo</strong>, criando um novo com o saldo devedor e novas condições. 
                            Ao aprovar, o empréstimo original será finalizado e o novo será criado automaticamente.
                        </p>

                        <form method="GET" action="{{ route('liberacoes.negociacoes') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="operacao_id" class="form-select">
                                        <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas as Operações</option>
                                        @foreach($operacoes as $op)
                                            <option value="{{ $op->id }}" {{ (int) ($operacaoId ?? 0) === (int) $op->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>{{ $op->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary"><i class="bx bx-search"></i> Filtrar</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Empréstimo Original</th>
                                        <th>Cliente</th>
                                        <th>Operação</th>
                                        <th>Saldo Devedor</th>
                                        <th>Novas Condições</th>
                                        <th>Motivo</th>
                                        <th>Consultor</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($solicitacoes as $s)
                                        @php
                                            $emp = $s->emprestimo;
                                            $dados = $s->dados_formatados;
                                        @endphp
                                        <tr>
                                            <td>{{ $s->id }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emp->id) }}">#{{ $emp->id }}</a>
                                            </td>
                                            <td>{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($s->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</td>
                                            <td>{{ $s->operacao->nome ?? '-' }}</td>
                                            <td class="fw-bold text-primary">R$ {{ number_format($s->saldo_devedor, 2, ',', '.') }}</td>
                                            <td>
                                                <small>
                                                    <strong>Tipo:</strong> {{ $dados['tipo'] }}<br>
                                                    <strong>Frequência:</strong> {{ $dados['frequencia'] }}<br>
                                                    <strong>Taxa:</strong> {{ $dados['taxa_juros'] }}<br>
                                                    <strong>Parcelas:</strong> {{ $dados['numero_parcelas'] }}x<br>
                                                    <strong>Início:</strong> {{ $dados['data_inicio'] }}
                                                </small>
                                            </td>
                                            <td>
                                                <span title="{{ $s->motivo }}">
                                                    {{ Str::limit($s->motivo, 50) }}
                                                </span>
                                            </td>
                                            <td>{{ $s->consultor->name ?? '-' }}</td>
                                            <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalAprovar{{ $s->id }}"
                                                            title="Aprovar">
                                                        <i class="bx bx-check"></i> Aprovar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalRejeitar{{ $s->id }}"
                                                            title="Rejeitar">
                                                        <i class="bx bx-x"></i> Rejeitar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Modal Aprovar -->
                                        <div class="modal fade" id="modalAprovar{{ $s->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('liberacoes.negociacoes.aprovar', $s->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title text-white">
                                                                <i class="bx bx-check"></i> Aprovar Negociação
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Você está prestes a aprovar a negociação do empréstimo <strong>#{{ $emp->id }}</strong>.</p>
                                                            <div class="alert alert-info">
                                                                <strong>O que acontecerá:</strong>
                                                                <ul class="mb-0 mt-2">
                                                                    <li>Empréstimo #{{ $emp->id }} será finalizado</li>
                                                                    <li>Novo empréstimo será criado com valor R$ {{ number_format($s->saldo_devedor, 2, ',', '.') }}</li>
                                                                    <li>Novas condições: {{ $dados['tipo'] }}, {{ $dados['frequencia'] }}, {{ $dados['taxa_juros'] }}, {{ $dados['numero_parcelas'] }}x</li>
                                                                </ul>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Observação (opcional)</label>
                                                                <textarea name="observacao" class="form-control" rows="2" placeholder="Adicione uma observação se necessário..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="bx bx-check"></i> Confirmar Aprovação
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Rejeitar -->
                                        <div class="modal fade" id="modalRejeitar{{ $s->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('liberacoes.negociacoes.rejeitar', $s->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title text-white">
                                                                <i class="bx bx-x"></i> Rejeitar Negociação
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Você está prestes a rejeitar a solicitação de negociação do empréstimo <strong>#{{ $emp->id }}</strong>.</p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Motivo da rejeição</label>
                                                                <textarea name="observacao" class="form-control" rows="3" placeholder="Informe o motivo da rejeição..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="bx bx-x"></i> Confirmar Rejeição
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">
                                                <i class="bx bx-check-circle font-size-24 d-block mb-2"></i>
                                                Nenhuma solicitação de negociação aguardando aprovação.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $solicitacoes->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection
