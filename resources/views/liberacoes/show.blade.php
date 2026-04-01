@extends('layouts.master')
@section('title')
    Liberação #{{ $liberacao->id }}
@endsection
@section('page-title')
    Liberação #{{ $liberacao->id }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8">
                <!-- Informações da Liberação -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Detalhes da Liberação</h4>
                            @php
                                $badgeClass = match($liberacao->status) {
                                    'aguardando' => 'warning',
                                    'liberado' => 'info',
                                    'pago_ao_cliente' => 'success',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $badgeClass }} font-size-16">{{ ucfirst($liberacao->status) }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Empréstimo:</strong> 
                                <a href="{{ route('emprestimos.show', $liberacao->emprestimo_id) }}">
                                    #{{ $liberacao->emprestimo_id }}
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Cliente:</strong> 
                                <a href="{{ \App\Support\ClienteUrl::show($liberacao->emprestimo->cliente_id, $liberacao->emprestimo->operacao_id) }}">
                                    {{ $nomeClienteExibicao }}
                                </a>
                                @if(\App\Support\WhatsappLink::temWhatsappPreferindoFicha($fichaContatoLiberacao ?? null, $liberacao->emprestimo->cliente))
                                    <a href="{{ \App\Support\WhatsappLink::urlPreferindoFicha($fichaContatoLiberacao ?? null, $liberacao->emprestimo->cliente) }}"
                                       target="_blank"
                                       class="btn btn-sm btn-success ms-2"
                                       title="Falar no WhatsApp (ficha desta operação quando houver)">
                                        <i class="bx bxl-whatsapp"></i> WhatsApp
                                    </a>
                                @endif
                            </div>
                            @if(($vinculosOutrasOperacoesCount ?? 0) > 0)
                                <div class="col-12 mb-3">
                                    <div class="alert alert-warning mb-0">
                                        <i class="bx bx-link-alt"></i>
                                        <strong>Vínculo em outras operações:</strong>
                                        este CPF possui vínculo com <strong>{{ $vinculosOutrasOperacoesCount }}</strong> outra(s) operação(ões) nesta empresa.
                                        <a href="{{ \App\Support\ClienteUrl::show($liberacao->emprestimo->cliente_id, $liberacao->emprestimo->operacao_id) }}" class="alert-link ms-1">Ver cliente</a>
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-6 mb-3">
                                <strong>Operação:</strong> {{ $liberacao->emprestimo->operacao->nome }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Consultor:</strong> {{ $liberacao->consultor->name }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Valor a Liberar:</strong> 
                                <span class="h5 text-primary">R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Valor do Empréstimo:</strong> 
                                R$ {{ number_format($liberacao->emprestimo->valor_total, 2, ',', '.') }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Criado em:</strong> {{ $liberacao->created_at->format('d/m/Y H:i') }}
                            </div>
                            @if($liberacao->liberado_em)
                                <div class="col-md-6 mb-3">
                                    <strong>Liberado em:</strong> {{ $liberacao->liberado_em->format('d/m/Y H:i') }}
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Liberado por:</strong> {{ $liberacao->gestor->name ?? '-' }}
                                </div>
                            @endif
                            @if($liberacao->pago_ao_cliente_em)
                                <div class="col-md-6 mb-3">
                                    <strong>Pago ao cliente em:</strong> {{ $liberacao->pago_ao_cliente_em->format('d/m/Y H:i') }}
                                </div>
                            @endif
                            @if($liberacao->confirmadoPagamentoPor)
                                <div class="col-md-6 mb-3">
                                    <strong>Confirmado por:</strong> {{ $liberacao->confirmadoPagamentoPor->name }}
                                </div>
                            @endif
                            @if($liberacao->observacoes_liberacao)
                                <div class="col-12 mb-3">
                                    <strong>Observações da Liberação:</strong><br>
                                    <div class="alert alert-info mb-0">{{ $liberacao->observacoes_liberacao }}</div>
                                </div>
                            @endif
                            @if($liberacao->observacoes_pagamento)
                                <div class="col-12 mb-3">
                                    <strong>Observações do Pagamento:</strong><br>
                                    <div class="alert alert-info mb-0">{{ $liberacao->observacoes_pagamento }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Comprovantes -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Comprovantes</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if($liberacao->hasComprovanteLiberacao())
                                <div class="col-md-6 mb-3">
                                    <strong>Comprovante de Liberação:</strong><br>
                                    <div class="mt-2">
                                        @if($liberacao->isComprovanteLiberacaoImagem())
                                            <div class="mb-2">
                                                <img src="{{ $liberacao->comprovante_liberacao_url }}" 
                                                     alt="Comprovante de Liberação" 
                                                     class="img-thumbnail" 
                                                     style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                     onclick="window.open('{{ $liberacao->comprovante_liberacao_url }}', '_blank')">
                                            </div>
                                            <a href="{{ $liberacao->comprovante_liberacao_url }}" 
                                               target="_blank" 
                                               class="btn btn-sm btn-success">
                                                <i class="bx bx-download"></i> Baixar Comprovante
                                            </a>
                                        @else
                                            <a href="{{ $liberacao->comprovante_liberacao_url }}" 
                                               target="_blank" 
                                               class="btn btn-sm btn-success">
                                                <i class="bx bx-download"></i> Baixar Comprovante
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6 mb-3">
                                    <strong>Comprovante de Liberação:</strong><br>
                                    @if(($liberacao->isLiberado() || $liberacao->isPagoAoCliente()) && ($podeAprovarLiberacao ?? false))
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAnexarComprovanteLiberacao" title="Subir comprovante depois">
                                            <i class="bx bx-upload"></i> Subir comprovante
                                        </button>
                                    @else
                                        <span class="text-muted">Não disponível</span>
                                    @endif
                                </div>
                            @endif

                            @if($liberacao->hasComprovantePagamentoCliente())
                                <div class="col-md-6 mb-3">
                                    <strong>Comprovante de Pagamento ao Cliente:</strong><br>
                                    <div class="mt-2">
                                        @if($liberacao->isComprovantePagamentoClienteImagem())
                                            <div class="mb-2">
                                                <img src="{{ $liberacao->comprovante_pagamento_cliente_url }}" 
                                                     alt="Comprovante de Pagamento ao Cliente" 
                                                     class="img-thumbnail" 
                                                     style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                     onclick="window.open('{{ $liberacao->comprovante_pagamento_cliente_url }}', '_blank')">
                                            </div>
                                            <a href="{{ $liberacao->comprovante_pagamento_cliente_url }}" 
                                               target="_blank" 
                                               class="btn btn-sm btn-success">
                                                <i class="bx bx-download"></i> Baixar Comprovante
                                            </a>
                                        @else
                                            <a href="{{ $liberacao->comprovante_pagamento_cliente_url }}" 
                                               target="_blank" 
                                               class="btn btn-sm btn-success">
                                                <i class="bx bx-download"></i> Baixar Comprovante
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6 mb-3">
                                    <strong>Comprovante de Pagamento ao Cliente:</strong><br>
                                    @if($liberacao->isPagoAoCliente() && ($podeConfirmarPagamentoCliente ?? false))
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAnexarComprovantePagamentoCliente" title="Subir comprovante depois">
                                            <i class="bx bx-upload"></i> Subir comprovante
                                        </button>
                                    @else
                                        <span class="text-muted">Não disponível</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Informações do Empréstimo -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Informações do Empréstimo</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Valor Total:</strong> 
                                R$ {{ number_format($liberacao->emprestimo->valor_total, 2, ',', '.') }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Parcelas:</strong> {{ $liberacao->emprestimo->numero_parcelas }}x 
                                ({{ ucfirst($liberacao->emprestimo->frequencia) }})
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Status:</strong>
                                @php
                                    $badgeClass = match($liberacao->emprestimo->status) {
                                        'ativo' => 'success',
                                        'pendente' => 'warning',
                                        'finalizado' => 'info',
                                        'cancelado' => 'danger',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }}">
                                    {{ ucfirst($liberacao->emprestimo->status) }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Data de Início:</strong> 
                                {{ $liberacao->emprestimo->data_inicio->format('d/m/Y') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Ações -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Ações</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('emprestimos.show', $liberacao->emprestimo_id) }}" class="btn btn-info">
                                <i class="bx bx-show"></i> Ver Empréstimo
                            </a>
                            <a href="{{ \App\Support\ClienteUrl::show($liberacao->emprestimo->cliente_id, $liberacao->emprestimo->operacao_id) }}" class="btn btn-secondary">
                                <i class="bx bx-user"></i> Ver Cliente
                            </a>
                            <a href="{{ route('liberacoes.index') }}" class="btn btn-outline-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar para Lista
                            </a>

                            @if($liberacao->isAguardando() && ($podeAprovarLiberacao ?? false))
                                <hr>
                                <button type="button" class="btn btn-success" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#liberarModal">
                                    <i class="bx bx-check"></i> Liberar Dinheiro
                                </button>
                            @endif

                            @if($liberacao->isLiberado() && ($podeConfirmarPagamentoCliente ?? false))
                                <hr>
                                <button type="button" class="btn btn-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#confirmarPagamentoModal">
                                    <i class="bx bx-money"></i> Confirmar Pagamento ao Cliente
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Histórico</h4>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Liberação Criada</h6>
                                    <p class="text-muted mb-0 small">{{ $liberacao->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                            @if($liberacao->liberado_em)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">Dinheiro Liberado</h6>
                                        <p class="text-muted mb-0 small">
                                            Por: {{ $liberacao->gestor->name ?? '-' }}<br>
                                            {{ $liberacao->liberado_em->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                            @if($liberacao->pago_ao_cliente_em)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">Pagamento ao Cliente Confirmado</h6>
                                        <p class="text-muted mb-0 small">{{ $liberacao->pago_ao_cliente_em->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Liberar Dinheiro -->
        @if($liberacao->isAguardando() && ($podeAprovarLiberacao ?? false))
        <div class="modal fade" id="liberarModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('liberacoes.liberar', $liberacao->id) }}" 
                          method="POST" enctype="multipart/form-data"
                          class="form-liberar-dinheiro"
                          data-valor="{{ number_format($liberacao->valor_liberado, 2, ',', '.') }}"
                          data-consultor="{{ $liberacao->consultor->name }}"
                          data-cliente="{{ $nomeClienteExibicao }}"
                          data-emprestimo-id="{{ $liberacao->emprestimo_id }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Liberar Dinheiro</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>Valor:</strong> R$ {{ number_format($liberacao->valor_liberado, 2, ',', '.') }}<br>
                                <strong>Consultor:</strong> {{ $liberacao->consultor->name }}<br>
                                <strong>Cliente:</strong> {{ $nomeClienteExibicao }}<br>
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
        @endif

        <!-- Modal Confirmar Pagamento ao Cliente -->
        @if($liberacao->isLiberado() && ($podeConfirmarPagamentoCliente ?? false))
        <div class="modal fade" id="confirmarPagamentoModal" tabindex="-1" aria-labelledby="confirmarPagamentoModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmarPagamentoModalLabel">Confirmar Pagamento ao Cliente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('liberacoes.confirmar-pagamento', $liberacao->id) }}" method="POST" enctype="multipart/form-data" id="formConfirmarPagamento">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Valor Pago</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control" 
                                           value="{{ number_format($liberacao->valor_liberado, 2, ',', '.') }}" 
                                           readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control" 
                                       value="{{ $nomeClienteExibicao }}" 
                                       readonly>
                            </div>
                            @if($ehGestorAdminConfirmando ?? false)
                            <div class="alert alert-secondary mb-3">
                                <i class="bx bx-info-circle"></i>
                                <strong>Você está confirmando como gestor/administrador.</strong><br>
                                O valor será debitado do caixa do consultor <strong>{{ $liberacao->consultor->name ?? 'responsável' }}</strong>, pois o dinheiro foi liberado para ele.
                            </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">Observações (Opcional)</label>
                                <textarea name="observacoes" class="form-control" rows="3" 
                                          placeholder="Observações sobre o pagamento..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Comprovante de Pagamento (Opcional)</label>
                                <input type="file" name="comprovante" class="form-control" 
                                       accept="application/pdf,image/jpeg,image/png">
                                <small class="text-muted">Max: 2MB (PDF, JPG, PNG)</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Confirmar Pagamento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Modal Anexar Comprovante de Liberação (depois) -->
        @if(!$liberacao->hasComprovanteLiberacao() && ($liberacao->isLiberado() || $liberacao->isPagoAoCliente()) && ($podeAprovarLiberacao ?? false))
        <div class="modal fade" id="modalAnexarComprovanteLiberacao" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('liberacoes.anexar-comprovante-liberacao', $liberacao->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Subir comprovante de liberação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Apenas para liberações que ainda não possuem comprovante. PDF, JPG ou PNG (máx. 2MB).</p>
                            <div class="mb-3">
                                <label class="form-label">Comprovante <span class="text-danger">*</span></label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="bx bx-upload"></i> Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Modal Anexar Comprovante de Pagamento ao Cliente (depois) -->
        @if(!$liberacao->hasComprovantePagamentoCliente() && $liberacao->isPagoAoCliente() && ($podeConfirmarPagamentoCliente ?? false))
        <div class="modal fade" id="modalAnexarComprovantePagamentoCliente" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('liberacoes.anexar-comprovante-pagamento-cliente', $liberacao->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Subir comprovante de pagamento ao cliente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Apenas para liberações que ainda não possuem comprovante de pagamento. PDF, JPG ou PNG (máx. 2MB).</p>
                            <div class="mb-3">
                                <label class="form-label">Comprovante <span class="text-danger">*</span></label>
                                <input type="file" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="bx bx-upload"></i> Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    @endsection
    @section('scripts')
        <style>
            .timeline {
                position: relative;
                padding-left: 30px;
            }
            .timeline-item {
                position: relative;
                padding-bottom: 20px;
            }
            .timeline-item:not(:last-child)::before {
                content: '';
                position: absolute;
                left: -25px;
                top: 20px;
                bottom: -20px;
                width: 2px;
                background-color: #dee2e6;
            }
            .timeline-marker {
                position: absolute;
                left: -30px;
                top: 0;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                border: 2px solid #fff;
                box-shadow: 0 0 0 2px currentColor;
            }
            .timeline-content h6 {
                font-size: 14px;
                margin-bottom: 4px;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Sweet Alert para Liberar Dinheiro (mesmo da index)
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

                // Sweet Alert para Confirmar Pagamento
                document.getElementById('formConfirmarPagamento')?.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const form = this;
                    const valor = parseFloat({{ $liberacao->valor_liberado }}).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    const cliente = @json($nomeClienteExibicao);

                    Swal.fire({
                        title: 'Confirmar Pagamento ao Cliente?',
                        html: `
                            <div class="text-start">
                                <p><strong>Valor:</strong> ${valor}</p>
                                <p><strong>Cliente:</strong> ${cliente}</p>
                                <p class="text-muted mt-2">Ao confirmar, o empréstimo será marcado como ativo.</p>
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#038edc',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim, Confirmar!',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        </script>
    @endsection
