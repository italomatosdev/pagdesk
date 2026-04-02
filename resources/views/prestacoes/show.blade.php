@extends('layouts.master')
@section('title')
    Detalhes da Prestação de Contas #{{ $settlement->id }}
@endsection
@section('page-title')
    Detalhes da Prestação de Contas #{{ $settlement->id }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Card de Resumo -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0 text-white">
                                <i class="bx bx-file-blank me-2"></i>Resumo da Prestação de Contas #{{ $settlement->id }}
                            </h4>
                            <div>
                                @php
                                    $badgeClass = match($settlement->status) {
                                        'concluido' => 'success',
                                        'enviado' => 'info',
                                        'aprovado' => 'primary',
                                        'pendente' => 'warning',
                                        'rejeitado' => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusText = match($settlement->status) {
                                        'concluido' => 'Concluído',
                                        'enviado' => 'Enviado',
                                        'aprovado' => 'Aprovado',
                                        'pendente' => 'Pendente',
                                        'rejeitado' => 'Rejeitado',
                                        default => ucfirst($settlement->status)
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }} fs-6">{{ $statusText }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($settlement->isFechamentoPorGestor())
                            <div class="alert alert-warning mb-3">
                                <i class="bx bx-lock-alt"></i> <strong>Fechamento de Caixa</strong> - 
                                Iniciado por <strong>{{ $settlement->criador?->name ?? 'Gestor' }}</strong>
                                em {{ $settlement->created_at->format('d/m/Y H:i') }}
                            </div>
                        @endif
                        @if($settlement->isEnviado() && !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])) && $settlement->criado_por !== null && (int) auth()->id() !== (int) $settlement->criado_por)
                            <div class="alert alert-secondary mb-3">
                                <i class="bx bx-time-five me-1"></i>
                                Aguardando confirmação de recebimento por <strong>{{ $settlement->criador?->name ?? 'quem iniciou o fechamento' }}</strong>.
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Usuário</p>
                                    <h5 class="mb-0">{{ $settlement->consultor->name }}</h5>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Operação</p>
                                    <h5 class="mb-0">{{ $settlement->operacao->nome }}</h5>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Período</p>
                                    <h5 class="mb-0">
                                        {{ $settlement->data_inicio->format('d/m/Y') }} até
                                        {{ $settlement->data_fim->format('d/m/Y') }}
                                    </h5>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Movimentações</p>
                                    <h5 class="mb-0">{{ $quantidadeMovimentacoes }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-3 mb-3 d-flex">
                                <div class="card border-{{ $saldoInicial >= 0 ? 'success' : 'danger' }} w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Saldo Inicial</p>
                                        <h4 class="mb-0 text-{{ $saldoInicial >= 0 ? 'success' : 'danger' }} flex-grow-1">
                                            <i class="bx bx-wallet me-2"></i>R$ {{ number_format($saldoInicial, 2, ',', '.') }}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 d-flex">
                                <div class="card border-success w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Total de Entradas</p>
                                        <h4 class="mb-0 text-success flex-grow-1">
                                            <i class="bx bx-trending-up me-2"></i>R$ {{ number_format($totalEntradas, 2, ',', '.') }}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 d-flex">
                                <div class="card border-danger w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Total de Saídas</p>
                                        <h4 class="mb-0 text-danger flex-grow-1">
                                            <i class="bx bx-trending-down me-2"></i>R$ {{ number_format($totalSaidas, 2, ',', '.') }}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 d-flex">
                                <div class="card border-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }} w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Saldo Final (Valor da Prestação)</p>
                                        <h4 class="mb-0 text-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }} flex-grow-1">
                                            <i class="bx bx-dollar-circle me-2"></i>R$ {{ number_format($saldoFinal, 2, ',', '.') }}
                                        </h4>
                                        <small class="text-muted mt-auto">Saldo Inicial + Entradas - Saídas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($saldoFinal < 0)
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">
                                        <i class="bx bx-error-circle me-2"></i>
                                        <strong>Atenção!</strong> O saldo final é negativo (R$ {{ number_format(abs($saldoFinal), 2, ',', '.') }}).
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card de Informações Adicionais -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informações Adicionais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Criado em:</strong> {{ $settlement->created_at->format('d/m/Y H:i') }}</p>
                                @if($settlement->conferidor)
                                    <p><strong>Aprovado por:</strong> {{ $settlement->conferidor->name }}</p>
                                    <p><strong>Aprovado em:</strong> {{ $settlement->conferido_em->format('d/m/Y H:i') }}</p>
                                @endif
                                @if($settlement->recebedor)
                                    <p><strong>Recebido por:</strong> {{ $settlement->recebedor->name }}</p>
                                    <p><strong>Recebido em:</strong> {{ $settlement->recebido_em->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($settlement->observacoes)
                                    <p><strong>Observações:</strong></p>
                                    <p>{{ $settlement->observacoes }}</p>
                                @endif
                                @if($settlement->motivo_rejeicao)
                                    <p><strong>Motivo da Rejeição:</strong></p>
                                    <p class="text-danger">{{ $settlement->motivo_rejeicao }}</p>
                                @endif
                                @if($settlement->comprovante_path)
                                    <p><strong>Comprovante:</strong></p>
                                    <a href="{{ asset('storage/' . $settlement->comprovante_path) }}" target="_blank" class="btn btn-sm btn-info">
                                        <i class="bx bx-file-blank"></i> Ver Comprovante
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card de Observações (se houver) -->
                @if($settlement->observacoes)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Observações</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $settlement->observacoes }}</p>
                        </div>
                    </div>
                @endif

                <!-- Card de Movimentações -->
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-list-ul me-2"></i>Movimentações Incluídas
                            </h5>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse"
                                data-bs-target="#movimentacoesCollapse" aria-expanded="true"
                                aria-controls="movimentacoesCollapse">
                                <i class="bx bx-chevron-down"></i> Ver Detalhes
                            </button>
                        </div>
                    </div>
                    <div class="collapse show" id="movimentacoesCollapse">
                        <div class="card-body">
                            @if($movimentacoes->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Tipo</th>
                                                <th>Descrição</th>
                                                <th>Origem</th>
                                                <th class="text-end">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($movimentacoes as $movimentacao)
                                                <tr>
                                                    <td>{{ $movimentacao->data_movimentacao->format('d/m/Y') }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $movimentacao->isEntrada() ? 'success' : 'danger' }}">
                                                            {{ $movimentacao->isEntrada() ? 'Entrada' : 'Saída' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        {{ $movimentacao->descricao }}
                                                        @if($movimentacao->pagamento && $movimentacao->pagamento->parcela && $movimentacao->pagamento->parcela->emprestimo)
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="bx bx-user"></i>
                                                                {{ \App\Support\ClienteNomeExibicao::fromParcelaMap($movimentacao->pagamento->parcela, $fichasContatoPorClienteOperacao ?? collect()) }}
                                                            </small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-{{ $movimentacao->isManual() ? 'info' : 'secondary' }}">
                                                            {{ ucfirst($movimentacao->origem) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end {{ $movimentacao->isEntrada() ? 'text-success' : 'text-danger' }}">
                                                        <strong>
                                                            {{ $movimentacao->isEntrada() ? '+' : '-' }} 
                                                            R$ {{ number_format($movimentacao->valor, 2, ',', '.') }}
                                                        </strong>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th colspan="4" class="text-end">Saldo Inicial:</th>
                                                <th class="text-end {{ $saldoInicial >= 0 ? 'text-success' : 'text-danger' }}">
                                                    R$ {{ number_format($saldoInicial, 2, ',', '.') }}
                                                </th>
                                            </tr>
                                            <tr class="table-success">
                                                <th colspan="4" class="text-end">Total Entradas:</th>
                                                <th class="text-end text-success">
                                                    + R$ {{ number_format($totalEntradas, 2, ',', '.') }}
                                                </th>
                                            </tr>
                                            <tr class="table-danger">
                                                <th colspan="4" class="text-end">Total Saídas:</th>
                                                <th class="text-end text-danger">
                                                    - R$ {{ number_format($totalSaidas, 2, ',', '.') }}
                                                </th>
                                            </tr>
                                            <tr class="table-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }}">
                                                <th colspan="4" class="text-end"><strong>Saldo Final (Valor da Prestação):</strong></th>
                                                <th class="text-end text-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }}">
                                                    <strong>R$ {{ number_format($saldoFinal, 2, ',', '.') }}</strong>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Atenção:</strong> Nenhuma movimentação foi encontrada no período.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card de Ações -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <a href="{{ route('prestacoes.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar
                            </a>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                @if($settlement->isPendente() && !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])))
                                    <form action="{{ route('prestacoes.aprovar', $settlement->id) }}" method="POST" class="d-inline" id="formAprovar">
                                        @csrf
                                        <button type="submit" class="btn btn-success" id="btnAprovar">
                                            <i class="bx bx-check"></i> Aprovar Prestação
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger" onclick="mostrarModalRejeitar()">
                                        <i class="bx bx-x"></i> Rejeitar Prestação
                                    </button>
                                @endif
                                
                                @if($settlement->isAprovado() && $settlement->consultor_id == auth()->id())
                                    <button type="button" class="btn btn-primary" onclick="mostrarModalComprovante()">
                                        <i class="bx bx-file"></i> Anexar Comprovante
                                    </button>
                                @endif
                                
                                @if($settlement->isEnviado() && !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])) && ($settlement->criado_por === null || (int) auth()->id() === (int) $settlement->criado_por))
                                    <form action="{{ route('prestacoes.confirmar-recebimento', $settlement->id) }}" method="POST" class="d-inline" id="formConfirmarRecebimento">
                                        @csrf
                                        <button type="submit" class="btn btn-success" id="btnConfirmarRecebimento">
                                            <i class="bx bx-check-circle"></i> Confirmar Recebimento
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Rejeitar -->
        <div class="modal fade" id="rejeitarModal" tabindex="-1" aria-labelledby="rejeitarModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejeitarModalLabel">Rejeitar Prestação de Contas</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formRejeitar" method="POST" action="{{ route('prestacoes.rejeitar', $settlement->id) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="motivo_rejeicao" class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="motivo_rejeicao" name="motivo_rejeicao" rows="3" required maxlength="500"></textarea>
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

        <!-- Modal Anexar Comprovante -->
        <div class="modal fade" id="comprovanteModal" tabindex="-1" aria-labelledby="comprovanteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="comprovanteModalLabel">Anexar Comprovante de Envio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formComprovante" method="POST" action="{{ route('prestacoes.anexar-comprovante', $settlement->id) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="comprovante" class="form-label">Comprovante <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="comprovante" name="comprovante" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="text-muted">PDF ou imagem (máx. 2MB)</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Anexar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Formulário de Aprovar
                const formAprovar = document.getElementById('formAprovar');
                if (formAprovar) {
                    formAprovar.addEventListener('submit', function(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Aprovar Prestação de Contas?',
                            html: `
                                <div class="text-start">
                                    <p><strong>Consultor:</strong> {{ $settlement->consultor->name }}</p>
                                    <p><strong>Operação:</strong> {{ $settlement->operacao->nome }}</p>
                                    <p><strong>Período:</strong> {{ $settlement->data_inicio->format('d/m/Y') }} até {{ $settlement->data_fim->format('d/m/Y') }}</p>
                                    <p><strong>Valor:</strong> R$ {{ number_format($settlement->valor_total, 2, ',', '.') }}</p>
                                </div>
                                <p class="text-muted mt-3">Ao aprovar, o consultor poderá anexar o comprovante de envio.</p>
                            `,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="bx bx-check"></i> Sim, Aprovar!',
                            cancelButtonText: '<i class="bx bx-x"></i> Cancelar',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const btnAprovar = document.getElementById('btnAprovar');
                                btnAprovar.disabled = true;
                                btnAprovar.innerHTML = '<i class="bx bx-loader bx-spin"></i> Aprovando...';
                                formAprovar.submit();
                            }
                        });
                    });
                }

                // Formulário de Confirmar Recebimento
                const formConfirmarRecebimento = document.getElementById('formConfirmarRecebimento');
                if (formConfirmarRecebimento) {
                    formConfirmarRecebimento.addEventListener('submit', function(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Confirmar Recebimento?',
                            html: `
                                <div class="text-start">
                                    <p><strong>Consultor:</strong> {{ $settlement->consultor->name }}</p>
                                    <p><strong>Valor:</strong> R$ {{ number_format($settlement->valor_total, 2, ',', '.') }}</p>
                                </div>
                                <p class="text-warning mt-3"><strong>Atenção:</strong> Isso irá gerar as movimentações de caixa automaticamente. Esta ação não pode ser desfeita.</p>
                            `,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="bx bx-check"></i> Sim, Confirmar!',
                            cancelButtonText: '<i class="bx bx-x"></i> Cancelar',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const btnConfirmarRecebimento = document.getElementById('btnConfirmarRecebimento');
                                btnConfirmarRecebimento.disabled = true;
                                btnConfirmarRecebimento.innerHTML = '<i class="bx bx-loader bx-spin"></i> Confirmando...';
                                formConfirmarRecebimento.submit();
                            }
                        });
                    });
                }
            });

            function mostrarModalRejeitar() {
                const modal = new bootstrap.Modal(document.getElementById('rejeitarModal'));
                modal.show();
            }

            function mostrarModalComprovante() {
                const modal = new bootstrap.Modal(document.getElementById('comprovanteModal'));
                modal.show();
            }
        </script>
    @endsection
