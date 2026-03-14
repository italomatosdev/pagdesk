@extends('layouts.master')
@section('title')
    Minhas Liberações
@endsection
@section('page-title')
    Minhas Liberações
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
                        <h4 class="card-title mb-0">Minhas Liberações</h4>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('liberacoes.minhas') }}" class="mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="status" class="form-select">
                                        <option value="">Todos os Status</option>
                                        <option value="aguardando" {{ $status == 'aguardando' ? 'selected' : '' }}>Aguardando</option>
                                        <option value="liberado" {{ $status == 'liberado' ? 'selected' : '' }}>Liberado</option>
                                        <option value="pago_ao_cliente" {{ $status == 'pago_ao_cliente' ? 'selected' : '' }}>Pago ao Cliente</option>
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
                                        <th>Empréstimo</th>
                                        <th>Cliente</th>
                                        <th>Operação</th>
                                        <th>Valor</th>
                                        <th>Próx. venc.</th>
                                        <th>Status</th>
                                        <th>Liberado em</th>
                                        <th>Pago em</th>
                                        <th>Comprovantes</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($liberacoes as $liberacao)
                                        <tr>
                                            <td>#{{ $liberacao->id }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $liberacao->emprestimo_id) }}">
                                                    #{{ $liberacao->emprestimo_id }}
                                                </a>
                                            </td>
                                            <td>{{ $liberacao->emprestimo->cliente->nome }}</td>
                                            <td>{{ $liberacao->emprestimo->operacao->nome }}</td>
                                            <td class="h6 text-primary">
                                                R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}
                                            </td>
                                            <td>{{ $liberacao->emprestimo->getProximoVencimento()?->format('d/m/Y') ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $badgeClass = match($liberacao->status) {
                                                        'aguardando' => 'warning',
                                                        'liberado' => 'info',
                                                        'pago_ao_cliente' => 'success',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    @if($liberacao->status === 'aguardando')
                                                        Aguardando
                                                    @elseif($liberacao->status === 'liberado')
                                                        Liberado
                                                    @else
                                                        Pago ao Cliente
                                                    @endif
                                                </span>
                                            </td>
                                            <td>
                                                {{ $liberacao->liberado_em ? $liberacao->liberado_em->format('d/m/Y H:i') : '-' }}
                                                @if($liberacao->gestor)
                                                    <br><small class="text-muted">por {{ $liberacao->gestor->name }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $liberacao->pago_ao_cliente_em ? $liberacao->pago_ao_cliente_em->format('d/m/Y H:i') : '-' }}
                                            </td>
                                            <td>
                                                @if($liberacao->hasComprovanteLiberacao())
                                                    <a href="{{ $liberacao->comprovante_liberacao_url }}" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary mb-1">
                                                        <i class="bx bx-file"></i> Liberação
                                                    </a>
                                                @endif
                                                @if($liberacao->hasComprovantePagamentoCliente())
                                                    <a href="{{ $liberacao->comprovante_pagamento_cliente_url }}" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-success mb-1">
                                                        <i class="bx bx-file"></i> Pagamento
                                                    </a>
                                                @elseif($liberacao->status === 'pago_ao_cliente')
                                                    <a href="{{ route('liberacoes.show', $liberacao->id) }}" 
                                                       class="btn btn-sm btn-outline-secondary mb-1" 
                                                       title="Subir comprovante depois">
                                                        <i class="bx bx-upload"></i> Subir comprovante
                                                    </a>
                                                @endif
                                                @if(!$liberacao->hasComprovanteLiberacao() && !$liberacao->hasComprovantePagamentoCliente() && $liberacao->status !== 'pago_ao_cliente')
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($liberacao->status === 'liberado')
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#confirmarModal{{ $liberacao->id }}">
                                                        <i class="bx bx-check"></i> Confirmar Pagamento
                                                    </button>
                                                @endif
                                                <a href="{{ route('emprestimos.show', $liberacao->emprestimo_id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center">Nenhuma liberação encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Modais fora da tabela para evitar erro de abertura no mobile (estrutura table não deve conter modal) --}}
                        @foreach($liberacoes as $liberacao)
                            @if($liberacao->status === 'liberado')
                                <div class="modal fade" id="confirmarModal{{ $liberacao->id }}" tabindex="-1" aria-labelledby="confirmarModalLabel{{ $liberacao->id }}" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('liberacoes.confirmar-pagamento', $liberacao->id) }}" 
                                                  method="POST" enctype="multipart/form-data"
                                                  class="form-confirmar-pagamento-cliente"
                                                  data-valor="{{ number_format($liberacao->valor_liberado, 2, ',', '.') }}"
                                                  data-cliente="{{ $liberacao->emprestimo->cliente->nome }}"
                                                  data-emprestimo-id="{{ $liberacao->emprestimo_id }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="confirmarModalLabel{{ $liberacao->id }}">Confirmar Pagamento ao Cliente</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-info">
                                                        <strong>Valor:</strong> R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}<br>
                                                        <strong>Cliente:</strong> {{ $liberacao->emprestimo->cliente->nome }}<br>
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
                                                                  placeholder="Ex: Pagamento realizado em dinheiro, comprovante anexado, etc."></textarea>
                                                    </div>
                                                    <div class="alert alert-warning">
                                                        <i class="bx bx-info-circle"></i> 
                                                        Confirme apenas após ter efetivamente pago o dinheiro ao cliente.
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-success">Confirmar Pagamento</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.form-confirmar-pagamento-cliente').forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const valor = this.dataset.valor;
                        const cliente = this.dataset.cliente;
                        const emprestimoId = this.dataset.emprestimoId;
                        
                        Swal.fire({
                            title: 'Confirmar Pagamento ao Cliente?',
                            html: `<strong>Valor:</strong> R$ ${valor}<br>
                                   <strong>Cliente:</strong> ${cliente}<br>
                                   <strong>Empréstimo:</strong> #${emprestimoId}<br><br>
                                   <div class="alert alert-warning">
                                       <i class="bx bx-info-circle"></i> 
                                       Confirme apenas se já efetuou o pagamento ao cliente.
                                   </div>`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sim, confirmar!',
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