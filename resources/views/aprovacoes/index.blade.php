@extends('layouts.master')
@section('title')
    Aprovações
@endsection
@section('page-title')
    Aprovações Pendentes
@endsection
@section('body')

    <body>
    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Empréstimos Pendentes de Aprovação</h4>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('aprovacoes.index') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="operacao_id" class="form-select">
                                        <option value="" {{ ($operacaoId ?? null) === null ? 'selected' : '' }}>Todas as Operações</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ (int) ($operacaoId ?? 0) === (int) $operacao->id && ($operacaoId ?? null) !== null ? 'selected' : '' }}>
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

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Outras ops</th>
                                        <th>Operação</th>
                                        <th>Tipo</th>
                                        <th>Valor</th>
                                        <th>Parcelas</th>
                                        <th>Consultor</th>
                                        <th>Próx. venc.</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendentes as $emprestimo)
                                        <tr>
                                            <td>#{{ $emprestimo->id }}</td>
                                            <td>
                                                <a href="{{ \App\Support\ClienteUrl::show($emprestimo->cliente_id, $emprestimo->operacao_id) }}">{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}</a>
                                                @if($ehRenovacaoPorEmprestimoId[$emprestimo->id] ?? false)
                                                    @include('partials.badge-renovacao-credito', ['ehRenovacao' => true])
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $qtdOutrasOps = (int) ($outrosVinculosPorEmprestimoId[$emprestimo->id] ?? 0);
                                                @endphp
                                                @if($qtdOutrasOps > 0)
                                                    <a href="{{ \App\Support\ClienteUrl::show($emprestimo->cliente_id, $emprestimo->operacao_id) }}" class="text-decoration-none" title="Ver cliente para detalhes">
                                                        <span class="badge bg-warning text-dark">Possui ({{ $qtdOutrasOps }})</span>
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $emprestimo->operacao->nome }}</td>
                                            <td>{{ ($tipoLabels ?? [
                                                'dinheiro' => 'Dinheiro',
                                                'price' => 'Price',
                                                'empenho' => 'Empenho',
                                                'troca_cheque' => 'Troca de Cheque',
                                                'crediario' => 'Crediário',
                                            ])[$emprestimo->tipo] ?? ucfirst($emprestimo->tipo ?? '—') }}</td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>{{ $emprestimo->numero_parcelas }}x ({{ ucfirst($emprestimo->frequencia) }})</td>
                                            <td>{{ $emprestimo->consultor->name }}</td>
                                            <td>{{ $emprestimo->getProximoVencimento()?->format('d/m/Y') ?? '—' }}</td>
                                            <td>{{ $emprestimo->created_at->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <form action="{{ route('aprovacoes.aprovar', $emprestimo->id) }}" 
                                                          method="POST" class="d-inline form-aprovar-emprestimo"
                                                          data-emprestimo-id="{{ $emprestimo->id }}"
                                                          data-cliente="{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}"
                                                          data-valor="{{ number_format($emprestimo->valor_total, 2, ',', '.') }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="bx bx-check"></i> Aprovar
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#rejeitarModal{{ $emprestimo->id }}">
                                                        <i class="bx bx-x"></i> Rejeitar
                                                    </button>
                                                    <a href="{{ route('emprestimos.show', $emprestimo->id) }}" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                </div>

                                                <!-- Modal Rejeitar -->
                                                <div class="modal fade" id="rejeitarModal{{ $emprestimo->id }}" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form action="{{ route('aprovacoes.rejeitar', $emprestimo->id) }}" 
                                                                  method="POST" 
                                                                  class="form-rejeitar-emprestimo"
                                                                  data-emprestimo-id="{{ $emprestimo->id }}"
                                                                  data-cliente="{{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}">
                                                                @csrf
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Rejeitar Empréstimo #{{ $emprestimo->id }}</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                                                                        <textarea name="motivo_rejeicao" class="form-control" rows="3" required></textarea>
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
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center">Nenhum empréstimo pendente de aprovação.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Aprovar empréstimo
                document.querySelectorAll('.form-aprovar-emprestimo').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const emprestimoId = this.dataset.emprestimoId;
                        const cliente = this.dataset.cliente;
                        const valor = this.dataset.valor;
                        
                        Swal.fire({
                            title: 'Aprovar Empréstimo?',
                            html: `<strong>Empréstimo #${emprestimoId}</strong><br>
                                   <strong>Cliente:</strong> ${cliente}<br>
                                   <strong>Valor:</strong> R$ ${valor}`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, aprovar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });

                // Rejeitar empréstimo
                document.querySelectorAll('.form-rejeitar-emprestimo').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const emprestimoId = this.dataset.emprestimoId;
                        const cliente = this.dataset.cliente;
                        const motivo = this.querySelector('textarea[name="motivo_rejeicao"]').value.trim();
                        
                        if (!motivo) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, informe o motivo da rejeição.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        Swal.fire({
                            title: 'Rejeitar Empréstimo?',
                            html: `<strong>Empréstimo #${emprestimoId}</strong><br>
                                   <strong>Cliente:</strong> ${cliente}<br>
                                   <strong>Motivo:</strong> ${motivo}`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, rejeitar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });
            });
        </script>
    @endsection