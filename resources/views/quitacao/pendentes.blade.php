@extends('layouts.master')
@section('title')
    Quitações pendentes de aprovação
@endsection
@section('page-title')
    Quitações pendentes de aprovação
@endsection
@section('body')<body>@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h4 class="card-title mb-0">Solicitações de quitação com desconto</h4>
                            <p class="text-muted small mb-0 mt-1">Aprove ou rejeite as solicitações de quitação com valor menor que o saldo devedor.</p>
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
                    <form method="GET" action="{{ route('quitacao.pendentes') }}" class="mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ (isset($operacaoId) && (string)$operacaoId === (string)$op->id) ? 'selected' : '' }}>{{ $op->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"><i class="bx bx-filter"></i> Filtrar</button>
                                <a href="{{ route('quitacao.pendentes') }}" class="btn btn-light"><i class="bx bx-reset"></i> Limpar</a>
                            </div>
                        </div>
                    </form>
                    @endif

                    @if($solicitacoes->isEmpty())
                        <p class="text-muted mb-0">Nenhuma solicitação pendente.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Empréstimo / Cliente</th>
                                        <th>Solicitante</th>
                                        <th>Saldo devedor</th>
                                        <th>Valor solicitado</th>
                                        <th>Desconto</th>
                                        <th>Motivo do desconto</th>
                                        <th>Data solicitação</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($solicitacoes as $s)
                                        <tr>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $s->emprestimo_id) }}">#{{ $s->emprestimo_id }}</a>
                                                <br><small class="text-muted">{{ $s->emprestimo->cliente->nome ?? '-' }}</small>
                                            </td>
                                            <td>{{ $s->solicitante->name ?? '-' }}</td>
                                            <td>R$ {{ number_format($s->saldo_devedor, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($s->valor_solicitado, 2, ',', '.') }}</td>
                                            <td class="text-warning">R$ {{ number_format($s->valor_desconto, 2, ',', '.') }}</td>
                                            <td>
                                                @if($s->motivo_desconto)
                                                    <span class="small d-block">{{ $s->motivo_desconto }}</span>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <form action="{{ route('quitacao.aprovar', $s->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success" onclick="return confirm('Aprovar esta quitação com desconto? O empréstimo será quitado com o valor solicitado.');">
                                                            <i class="bx bx-check"></i> Aprovar
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejeitarModal{{ $s->id }}">
                                                        <i class="bx bx-x"></i> Rejeitar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- Modal rejeitar --}}
                                        <div class="modal fade" id="rejeitarModal{{ $s->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="{{ route('quitacao.rejeitar', $s->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Rejeitar solicitação de quitação</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Empréstimo #{{ $s->emprestimo_id }} – {{ $s->emprestimo->cliente->nome ?? '' }}. Valor solicitado: R$ {{ number_format($s->valor_solicitado, 2, ',', '.') }}.</p>
                                                            @if($s->motivo_desconto)
                                                                <div class="alert alert-info py-2">
                                                                    <strong>Motivo informado pelo solicitante:</strong>
                                                                    <div class="small mb-0">{{ $s->motivo_desconto }}</div>
                                                                </div>
                                                            @endif
                                                            <div class="mb-3">
                                                                <label class="form-label">Motivo da rejeição <span class="text-danger">*</span></label>
                                                                <textarea name="motivo_rejeicao" class="form-control" rows="3" required minlength="10" maxlength="500" placeholder="Informe o motivo (mín. 10 caracteres)"></textarea>
                                                                @error('motivo_rejeicao')
                                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
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
                        <div class="d-flex justify-content-center mt-3">
                            {{ $solicitacoes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
