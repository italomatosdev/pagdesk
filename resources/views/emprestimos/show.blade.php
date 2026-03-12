@extends('layouts.master')
@section('title')
    Empréstimo #{{ $emprestimo->id }}
@endsection
@section('page-title')
    Empréstimo #{{ $emprestimo->id }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8">
                <!-- Informações do Empréstimo -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Informações do Empréstimo</h4>
                            <div class="d-flex gap-2">
                                @if($emprestimo->emprestimoOrigem)
                                    <span class="badge bg-info">
                                        Renovação do empréstimo 
                                        <a href="{{ route('emprestimos.show', $emprestimo->emprestimoOrigem->id) }}" class="text-white text-decoration-underline">
                                            #{{ $emprestimo->emprestimoOrigem->id }}
                                        </a>
                                    </span>
                                @endif
                                @if($emprestimo->renovacoes->isNotEmpty())
                                    @php
                                        $ultimaRenovacao = $emprestimo->renovacoes->sortByDesc('data_inicio')->first();
                                    @endphp
                                    <span class="badge bg-secondary">
                                        Renovado para o empréstimo 
                                        <a href="{{ route('emprestimos.show', $ultimaRenovacao->id) }}" class="text-white text-decoration-underline">
                                            #{{ $ultimaRenovacao->id }}
                                        </a>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            $solicitacaoQuitacaoDesconto = \App\Modules\Loans\Models\SolicitacaoQuitacao::where('emprestimo_id', $emprestimo->id)->where('status', 'aprovado')->first();
                        @endphp
                        @if($solicitacaoQuitacaoDesconto)
                            <div class="alert alert-info mb-3 mb-md-4">
                                <i class="bx bx-info-circle"></i>
                                <strong>Empréstimo quitado com desconto:</strong>
                                valor pago de <strong>R$ {{ number_format($solicitacaoQuitacaoDesconto->valor_solicitado, 2, ',', '.') }}</strong>
                                (saldo devedor na época era R$ {{ number_format($solicitacaoQuitacaoDesconto->saldo_devedor, 2, ',', '.') }}).
                                As parcelas foram encerradas com esse valor; o total pago pode ser menor que a soma dos valores nominais das parcelas.
                            </div>
                        @endif
                        @if($emprestimo->juros_incorporados > 0)
                            <div class="alert alert-warning mb-3 mb-md-4">
                                <i class="bx bx-transfer-alt"></i>
                                <strong>Empréstimo de negociação:</strong>
                                Este empréstimo incorpora <strong>R$ {{ number_format($emprestimo->juros_incorporados, 2, ',', '.') }}</strong> 
                                de juros do empréstimo anterior.
                                Nos relatórios de juros, esse valor será contabilizado proporcionalmente aos pagamentos recebidos.
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Cliente:</strong> 
                                <a href="{{ route('clientes.show', $emprestimo->cliente_id) }}">
                                    {{ $emprestimo->cliente->nome }}
                                </a>
                                @if($emprestimo->cliente->temWhatsapp())
                                    <a href="{{ $emprestimo->cliente->whatsapp_link }}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-success ms-2" 
                                       title="Falar no WhatsApp">
                                        <i class="bx bxl-whatsapp"></i> WhatsApp
                                    </a>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Operação:</strong> {{ $emprestimo->operacao->nome }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Tipo:</strong>
                                @php
                                    $tipoLabel = match($emprestimo->tipo) {
                                        'dinheiro' => ['Empréstimo em Dinheiro', 'primary', 'bx-money'],
                                        'price' => ['Sistema Price', 'info', 'bx-table'],
                                        'empenho' => ['Empenho (Com Garantia)', 'warning', 'bx-shield-quarter'],
                                        'troca_cheque' => ['Troca de Cheque', 'info', 'bx-money'],
                                        'crediario' => ['Crediário (Venda)', 'success', 'bx-cart'],
                                        default => ['Outro', 'secondary', 'bx-help-circle']
                                    };
                                @endphp
                                <span class="badge bg-{{ $tipoLabel[1] }}">
                                    <i class="bx {{ $tipoLabel[2] }}"></i> {{ $tipoLabel[0] }}
                                </span>
                            </div>
                            @if($emprestimo->isTrocaCheque())
                                {{-- Informações específicas para Troca de Cheque --}}
                                <div class="col-md-6 mb-3">
                                    <strong>Valor Total dos Cheques:</strong> 
                                    <span class="h5 text-primary">R$ {{ number_format($emprestimo->valor_total_cheques ?? $emprestimo->valor_total, 2, ',', '.') }}</span>
                                </div>
                                @if($emprestimo->taxa_juros > 0)
                                    <div class="col-md-6 mb-3">
                                        <strong>Taxa de Juros:</strong> 
                                        {{ number_format($emprestimo->taxa_juros, 2, ',', '.') }}%
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Juros Descontados:</strong> 
                                        <span class="text-warning">R$ {{ number_format($emprestimo->valor_total_juros_cheques ?? 0, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Valor Líquido a Pagar ao Cliente:</strong> 
                                        <span class="h5 text-success">R$ {{ number_format($emprestimo->valor_liquido_cheques ?? ($emprestimo->valor_total - ($emprestimo->valor_total_juros_cheques ?? 0)), 2, ',', '.') }}</span>
                                    </div>
                                @else
                                    <div class="col-md-6 mb-3">
                                        <strong>Valor Líquido a Pagar ao Cliente:</strong> 
                                        <span class="h5 text-success">R$ {{ number_format($emprestimo->valor_total_cheques ?? $emprestimo->valor_total, 2, ',', '.') }}</span>
                                    </div>
                                @endif
                                <div class="col-md-6 mb-3">
                                    <strong>Quantidade de Cheques:</strong> 
                                    {{ $emprestimo->cheques->count() }} cheque(s)
                                </div>
                            @else
                                {{-- Informações para outros tipos de empréstimo --}}
                                <div class="col-md-6 mb-3">
                                    <strong>Valor do Empréstimo:</strong> 
                                    <span class="h5 text-primary">R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</span>
                                </div>
                                @if($emprestimo->taxa_juros > 0)
                                    <div class="col-md-6 mb-3">
                                        <strong>Taxa de Juros:</strong> 
                                        {{ number_format($emprestimo->taxa_juros, 2, ',', '.') }}%
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Valor dos Juros:</strong> 
                                        <span class="text-warning">R$ {{ number_format($emprestimo->calcularValorJuros(), 2, ',', '.') }}</span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Valor Total a Pagar:</strong> 
                                        <span class="h5 text-success">R$ {{ number_format($emprestimo->calcularValorTotalComJuros(), 2, ',', '.') }}</span>
                                    </div>
                                @else
                                    <div class="col-md-6 mb-3">
                                        <strong>Valor Total a Pagar:</strong> 
                                        <span class="h5 text-success">R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</span>
                                    </div>
                                @endif
                                <div class="col-md-6 mb-3">
                                    <strong>Valor da Parcela:</strong> 
                                    <span class="h6">R$ {{ number_format($emprestimo->calcularValorParcela(), 2, ',', '.') }}</span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Parcelas:</strong> {{ $emprestimo->numero_parcelas }}x 
                                    ({{ ucfirst($emprestimo->frequencia) }})
                                </div>
                                @php
                                    $valorJaPago = $emprestimo->parcelas->sum('valor_pago') ?? 0;
                                    $parcelasPagas = $emprestimo->parcelas->where('status', 'paga')->count();
                                    $quitacaoServiceInfo = app(\App\Modules\Loans\Services\QuitacaoService::class);
                                    $saldoDevedorInfo = $quitacaoServiceInfo->getSaldoDevedor($emprestimo);
                                @endphp
                                <div class="col-md-6 mb-3">
                                    <strong>Valor Já Pago:</strong> 
                                    <span class="h6 text-success">R$ {{ number_format($valorJaPago, 2, ',', '.') }}</span>
                                    <small class="text-muted">({{ $parcelasPagas }}/{{ $emprestimo->numero_parcelas }} parcelas)</small>
                                </div>
                                @if($saldoDevedorInfo > 0)
                                    <div class="col-md-6 mb-3">
                                        <strong>Saldo Devedor:</strong> 
                                        <span class="h6 text-danger">R$ {{ number_format($saldoDevedorInfo, 2, ',', '.') }}</span>
                                    </div>
                                @endif
                            @endif
                            <div class="col-md-6 mb-3">
                                <strong>Status:</strong>
                                @php
                                    $badgeClass = match($emprestimo->status) {
                                        'ativo' => 'success',
                                        'pendente' => 'warning',
                                        'finalizado' => 'info',
                                        'cancelado' => 'danger',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }}">
                                    {{ ucfirst($emprestimo->status) }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Data de Início:</strong> 
                                {{ $emprestimo->data_inicio->format('d/m/Y') }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Consultor:</strong> {{ $emprestimo->consultor->name }}
                            </div>
                            @if($emprestimo->aprovado_por)
                                <div class="col-md-6 mb-3">
                                    <strong>Aprovado por:</strong> {{ $emprestimo->aprovador->name }}
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Data de Aprovação:</strong> 
                                    {{ $emprestimo->aprovado_em->format('d/m/Y H:i') }}
                                </div>
                            @endif
                            @if($emprestimo->motivo_rejeicao)
                                <div class="col-12 mb-3">
                                    <strong>Motivo da Rejeição/Cancelamento:</strong><br>
                                    <div class="alert alert-danger mb-0">{{ $emprestimo->motivo_rejeicao }}</div>
                                </div>
                            @endif
                            @if($emprestimo->observacoes)
                                <div class="col-12 mb-3">
                                    <strong>Observações:</strong><br>
                                    {{ $emprestimo->observacoes }}
                                </div>
                            @endif
                        </div>

                        <!-- Botão de Renovação (redireciona para página de pagamento) -->
                        @php
                            $parcela = $emprestimo->parcelas->first();
                            $estaAtrasada = $parcela && $parcela->isAtrasada();
                            $podeRenovar = $emprestimo->isAtivo() && 
                                          $emprestimo->numero_parcelas === 1 && 
                                          !$emprestimo->jurosJaForamPagos() &&
                                          $estaAtrasada; // Apenas se estiver atrasada
                        @endphp
                        @if($podeRenovar && $parcela)
                            <hr>
                            <div class="alert alert-info mb-3">
                                <h6 class="alert-heading">
                                    <i class="bx bx-info-circle"></i> Renovação de Empréstimo Disponível
                                </h6>
                                <p class="mb-2">
                                    Este empréstimo pode ser renovado. O cliente pagará apenas os <strong>juros do período</strong> 
                                    e o valor principal será renovado com novo prazo.
                                </p>
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <strong>Valor Principal:</strong> R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Valor dos Juros:</strong> R$ {{ number_format($emprestimo->calcularValorJuros(), 2, ',', '.') }}
                                    </div>
                                </div>
                                <a href="{{ route('pagamentos.create', ['parcela_id' => $parcela->id, 'renovar' => '1']) }}" class="btn btn-primary">
                                    <i class="bx bx-refresh"></i> Renovar Empréstimo (Pagar Só Juros)
                                </a>
                            </div>
                        @elseif($emprestimo->isAtivo() && $emprestimo->numero_parcelas === 1 && $emprestimo->jurosJaForamPagos())
                            <hr>
                            <div class="alert alert-warning mb-0">
                                <i class="bx bx-info-circle"></i> 
                                Os juros deste empréstimo já foram pagos. Não é necessário renovar.
                            </div>
                        @endif

                        <!-- Botão de Executar Garantia (apenas para gestores/administradores, tipo empenho, com parcela atrasada) -->
                        @php
                            $parcelaAtrasada = null;
                            foreach ($emprestimo->parcelas as $parcela) {
                                if ($parcela->isAtrasada()) {
                                    $parcelaAtrasada = $parcela;
                                    break;
                                }
                            }
                            
                            $podeExecutarGarantia = auth()->user()->hasAnyRole(['administrador', 'gestor']) &&
                                $emprestimo->isEmpenho() &&
                                $emprestimo->isAtivo() &&
                                $parcelaAtrasada &&
                                $emprestimo->garantias->where('status', 'ativa')->count() > 0;
                        @endphp
                        @if($podeExecutarGarantia && $parcelaAtrasada)
                            <hr>
                            <div class="alert alert-warning mb-3">
                                <h6 class="alert-heading">
                                    <i class="bx bx-error-circle"></i> Parcela Atrasada - Executar Garantia
                                </h6>
                                <p class="mb-2">
                                    Este empréstimo possui parcelas atrasadas. Você pode executar a garantia para finalizar o empréstimo.
                                </p>
                                <a href="{{ route('pagamentos.create', ['parcela_id' => $parcelaAtrasada->id, 'executar_garantia' => '1']) }}" 
                                   class="btn btn-danger">
                                    <i class="bx bx-shield-x"></i> Executar Garantia
                                </a>
                            </div>
                        @endif

                        <!-- Quitar empréstimo: diária = "Quitar todas as parcelas diárias"; demais = "Quitar empréstimo por completo" -->
                        @php
                            $parcelasAbertasQuitacao = $emprestimo->parcelas->filter(fn($p) => !in_array($p->status, ['paga', 'quitada_garantia']));
                            $podeQuitarCompleto = $emprestimo->isAtivo() && $parcelasAbertasQuitacao->isNotEmpty() &&
                                (auth()->user()->hasAnyRole(['administrador', 'gestor']) || $emprestimo->consultor_id === auth()->id());
                            $ehDiaria = $emprestimo->frequencia === 'diaria';
                        @endphp
                        @if($podeQuitarCompleto)
                            <hr>
                            <div class="alert alert-success mb-3">
                                @if($ehDiaria)
                                    <h6 class="alert-heading">
                                        <i class="bx bx-check-double"></i> Quitar todas as parcelas diárias
                                    </h6>
                                    <p class="mb-2">
                                        Registre um único pagamento e um comprovante para quitar todas as parcelas diárias em aberto. 
                                        Você pode pagar sem juros de atraso ou com juros (automático, manual ou valor fixo).
                                    </p>
                                    <a href="{{ route('pagamentos.quitar-diarias.create', $emprestimo->id) }}" class="btn btn-success">
                                        <i class="bx bx-money"></i> Quitar todas as parcelas diárias
                                    </a>
                                @else
                                    <h6 class="alert-heading">
                                        <i class="bx bx-check-double"></i> Quitar empréstimo por completo
                                    </h6>
                                    <p class="mb-2">
                                        Registre um único pagamento para quitar todas as parcelas em aberto. 
                                        Se o valor for menor que o saldo devedor (desconto), a quitação precisará de aprovação do gestor ou administrador.
                                    </p>
                                    <a href="{{ route('emprestimos.quitar', $emprestimo->id) }}" class="btn btn-success">
                                        <i class="bx bx-money"></i> Quitar empréstimo
                                    </a>
                                @endif
                            </div>
                        @endif

                        <!-- Botão de Negociação (para empréstimos ativos com saldo devedor) -->
                        @php
                            $quitacaoServiceNeg = app(\App\Modules\Loans\Services\QuitacaoService::class);
                            $saldoDevedorNeg = $emprestimo->isAtivo() ? $quitacaoServiceNeg->getSaldoDevedor($emprestimo) : 0;
                            $jaEhNegociacao = !empty($emprestimo->emprestimo_origem_id);
                            $podeNegociar = $emprestimo->isAtivo() && $saldoDevedorNeg > 0 && !$jaEhNegociacao;
                            
                            // Verificar se já existe solicitação de negociação pendente
                            $solicitacaoNegociacaoPendente = \App\Modules\Loans\Models\SolicitacaoNegociacao::where('emprestimo_id', $emprestimo->id)
                                ->where('status', 'pendente')
                                ->first();
                        @endphp
                        @if($podeNegociar && !$solicitacaoNegociacaoPendente)
                            <hr>
                            <div class="alert alert-primary mb-3">
                                <h6 class="alert-heading">
                                    <i class="bx bx-transfer-alt"></i> Negociar Empréstimo
                                </h6>
                                <p class="mb-2">
                                    Renegocie este empréstimo criando um novo com o saldo devedor e novas condições (tipo, frequência, taxa de juros, parcelas).
                                </p>
                                <div class="mb-2">
                                    <strong>Saldo Devedor Atual:</strong> 
                                    <span class="text-primary fw-bold">R$ {{ number_format($saldoDevedorNeg, 2, ',', '.') }}</span>
                                </div>
                                <a href="{{ route('emprestimos.create', ['negociacao_de' => $emprestimo->id]) }}" class="btn btn-primary">
                                    <i class="bx bx-transfer-alt"></i> Negociar Empréstimo
                                </a>
                            </div>
                        @elseif($solicitacaoNegociacaoPendente)
                            <hr>
                            <div class="alert alert-warning mb-3">
                                <i class="bx bx-time"></i> 
                                <strong>Negociação Pendente:</strong> Já existe uma solicitação de negociação aguardando aprovação para este empréstimo.
                            </div>
                        @elseif($jaEhNegociacao && $emprestimo->isAtivo() && $saldoDevedorNeg > 0)
                            <hr>
                            <div class="alert alert-secondary mb-3">
                                <i class="bx bx-block"></i> 
                                <strong>Negociação não disponível:</strong> Este empréstimo já é resultado de uma negociação anterior e não pode ser negociado novamente.
                                Considere outras opções como quitação com desconto.
                            </div>
                        @endif

                        <!-- Botão de Cancelamento (apenas para administradores) -->
                        @php
                            $podeCancelar = auth()->user()->hasRole('administrador') && 
                                !$emprestimo->isCancelado() && 
                                !$emprestimo->isFinalizado() &&
                                !$emprestimo->isRenovacao() && // Não pode cancelar empréstimos renovados
                                (!$emprestimo->liberacao || !$emprestimo->liberacao->isPagoAoCliente());
                            
                            // Verificar se há parcelas com pagamentos
                            $temParcelasPagas = false;
                            foreach ($emprestimo->parcelas as $parcela) {
                                if ($parcela->valor_pago > 0 || $parcela->pagamentos->count() > 0) {
                                    $temParcelasPagas = true;
                                    break;
                                }
                            }
                            $podeCancelar = $podeCancelar && !$temParcelasPagas;
                        @endphp
                        @if($emprestimo->isRenovacao() && auth()->user()->hasRole('administrador'))
                            <hr>
                            <div class="alert alert-info mb-0">
                                <i class="bx bx-info-circle"></i> 
                                <strong>Empréstimo Renovado:</strong> Este empréstimo não pode ser cancelado, pois é uma renovação. 
                                O empréstimo original já foi finalizado e o dinheiro foi pago ao cliente. 
                                As garantias foram transferidas e não podem ser revertidas automaticamente.
                            </div>
                        @endif
                        @if($podeCancelar)
                            <hr>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#cancelarEmprestimoModal">
                                    <i class="bx bx-x-circle"></i> Cancelar Empréstimo
                                </button>
                            </div>
                        @endif
                        
                    </div>
                </div>

                <!-- Liberação e Comprovantes -->
                @if($emprestimo->liberacao)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Liberação de Dinheiro</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <strong>Status:</strong>
                                    @php
                                        $badgeClass = match($emprestimo->liberacao->status) {
                                            'aguardando' => 'warning',
                                            'liberado' => 'info',
                                            'pago_ao_cliente' => 'success',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $emprestimo->liberacao->status)) }}
                                    </span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <strong>Valor Liberado:</strong> 
                                    <span class="h6 text-primary">R$ {{ number_format($emprestimo->liberacao->valor_liberado, 2, ',', '.') }}</span>
                                </div>
                                @if($emprestimo->liberacao->consultor)
                                    <div class="col-md-6 mb-3">
                                        <strong>Consultor:</strong> {{ $emprestimo->liberacao->consultor->name }}
                                    </div>
                                @endif
                                @if($emprestimo->liberacao->gestor)
                                    <div class="col-md-6 mb-3">
                                        <strong>Gestor que Liberou:</strong> {{ $emprestimo->liberacao->gestor->name }}
                                    </div>
                                @endif
                                @if($emprestimo->liberacao->liberado_em)
                                    <div class="col-md-6 mb-3">
                                        <strong>Liberado em:</strong> 
                                        {{ $emprestimo->liberacao->liberado_em->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                                @if($emprestimo->liberacao->pago_ao_cliente_em)
                                    <div class="col-md-6 mb-3">
                                        <strong>Pago ao Cliente em:</strong> 
                                        {{ $emprestimo->liberacao->pago_ao_cliente_em->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                                @if($emprestimo->liberacao->observacoes_liberacao)
                                    <div class="col-12 mb-3">
                                        <strong>Observações da Liberação:</strong><br>
                                        <div class="alert alert-info mb-0">{{ $emprestimo->liberacao->observacoes_liberacao }}</div>
                                    </div>
                                @endif
                                @if($emprestimo->liberacao->observacoes_pagamento)
                                    <div class="col-12 mb-3">
                                        <strong>Observações do Pagamento:</strong><br>
                                        <div class="alert alert-info mb-0">{{ $emprestimo->liberacao->observacoes_pagamento }}</div>
                                    </div>
                                @endif
                            </div>

                            <!-- Botão para confirmar pagamento ao cliente -->
                            @if($emprestimo->liberacao->status === 'liberado' && 
                                $emprestimo->status === 'aprovado' && 
                                $emprestimo->liberacao->consultor_id == auth()->id())
                                <div class="mb-3">
                                    <button type="button" class="btn btn-success btn-lg w-100" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#confirmarPagamentoModal">
                                        <i class="bx bx-check-circle"></i> Confirmar Pagamento ao Cliente
                                    </button>
                                </div>
                            @endif

                            <hr>

                            <h5 class="mb-3">Comprovantes</h5>
                            <div class="row">
                                @if($emprestimo->liberacao->comprovante_liberacao)
                                    <div class="col-md-6 mb-3">
                                        <strong>Comprovante de Liberação:</strong><br>
                                        <div class="mt-2">
                                            @if($emprestimo->liberacao->isComprovanteLiberacaoImagem())
                                                <div class="mb-2">
                                                    <img src="{{ $emprestimo->liberacao->comprovante_liberacao_url }}" 
                                                         alt="Comprovante de Liberação" 
                                                         class="img-thumbnail" 
                                                         style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                         onclick="window.open('{{ $emprestimo->liberacao->comprovante_liberacao_url }}', '_blank')">
                                                </div>
                                                <a href="{{ $emprestimo->liberacao->comprovante_liberacao_url }}" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bx bx-download"></i> Baixar Comprovante
                                                </a>
                                            @else
                                                <a href="{{ $emprestimo->liberacao->comprovante_liberacao_url }}" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bx bx-download"></i> Baixar Comprovante
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="col-md-6 mb-3">
                                        <strong>Comprovante de Liberação:</strong><br>
                                        <span class="text-muted">Não disponível</span>
                                    </div>
                                @endif

                                @if($emprestimo->liberacao->comprovante_pagamento_cliente)
                                    <div class="col-md-6 mb-3">
                                        <strong>Comprovante de Pagamento ao Cliente:</strong><br>
                                        <div class="mt-2">
                                            @if($emprestimo->liberacao->isComprovantePagamentoClienteImagem())
                                                <div class="mb-2">
                                                    <img src="{{ $emprestimo->liberacao->comprovante_pagamento_cliente_url }}" 
                                                         alt="Comprovante de Pagamento ao Cliente" 
                                                         class="img-thumbnail" 
                                                         style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                         onclick="window.open('{{ $emprestimo->liberacao->comprovante_pagamento_cliente_url }}', '_blank')">
                                                </div>
                                                <a href="{{ $emprestimo->liberacao->comprovante_pagamento_cliente_url }}" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="bx bx-download"></i> Baixar Comprovante
                                                </a>
                                            @else
                                                <a href="{{ $emprestimo->liberacao->comprovante_pagamento_cliente_url }}" 
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
                                        <span class="text-muted">Não disponível</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Tabela de Amortização (Sistema Price) -->
                @if($emprestimo->isPrice())
                    <div class="card mt-3 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h4 class="card-title mb-0 text-white">
                                <i class="bx bx-table text-white"></i> Tabela de Amortização (Sistema Price)
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Parcela</th>
                                            <th>Valor Parcela</th>
                                            <th>Juros</th>
                                            <th>Amortização</th>
                                            <th>Saldo Devedor</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($emprestimo->parcelas->sortBy('numero') as $parcela)
                                            <tr class="{{ $parcela->isAtrasada() ? 'table-danger' : ($parcela->isPaga() ? 'table-success' : ($parcela->isQuitadaGarantia() ? 'table-info' : '')) }}">
                                                <td><strong>{{ $parcela->numero }}</strong></td>
                                                <td>R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                                                <td>R$ {{ number_format($parcela->valor_juros ?? 0, 2, ',', '.') }}</td>
                                                <td>R$ {{ number_format($parcela->valor_amortizacao ?? 0, 2, ',', '.') }}</td>
                                                <td>
                                                    <strong>R$ {{ number_format($parcela->saldo_devedor ?? 0, 2, ',', '.') }}</strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $parcela->status_cor }}">
                                                        {{ $parcela->status_nome }}
                                                    </span>
                                                    @if($parcela->hasPagamentoProdutoObjetoPendente())
                                                        <span class="badge bg-warning text-dark ms-1" title="Pagamento em produto/objeto aguardando aceite">Aguardando aceite</span>
                                                    @endif
                                                    @if(!$parcela->isQuitada() && $parcela->hasPagamentoProdutoObjetoRejeitado())
                                                        <span class="badge bg-danger ms-1" title="Pagamento em produto/objeto foi recusado">Recusado</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle"></i> 
                                    <strong>Sistema Price:</strong> Parcela fixa com juros decrescentes e amortização crescente. 
                                    O saldo devedor reduz progressivamente até zero na última parcela.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Garantias (Empenho) -->
                @if($emprestimo->isEmpenho())
                    <div class="card mt-3 border-warning">
                        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">
                                <i class="bx bx-shield-quarter"></i> 
                                Garantias do Empenho ({{ $emprestimo->garantias->count() }})
                            </h4>
                            @if(!$emprestimo->isFinalizado())
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarGarantia">
                                <i class="bx bx-plus"></i> Adicionar Garantia
                            </button>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($emprestimo->garantias->count() > 0)
                                <div class="row">
                                    @foreach($emprestimo->garantias as $garantia)
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <i class="{{ $garantia->categoria_icone }}"></i>
                                                        <strong>{{ $garantia->categoria_nome }}</strong>
                                                    </span>
                                                    @if(!$emprestimo->isFinalizado())
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                                            <i class="bx bx-dots-vertical-rounded"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <button type="button" class="dropdown-item" 
                                                                        onclick="editarGarantia({{ $garantia->id }}, '{{ $garantia->categoria }}', '{{ addslashes($garantia->descricao) }}', '{{ $garantia->valor_avaliado ?? '' }}', '{{ addslashes($garantia->localizacao ?? '') }}', '{{ addslashes($garantia->observacoes ?? '') }}')">
                                                                    <i class="bx bx-edit"></i> Editar
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <form action="{{ route('emprestimos.garantias.destroy', $garantia->id) }}" method="POST" class="d-inline form-excluir-garantia">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i class="bx bx-trash"></i> Excluir
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="card-body">
                                                    <p class="mb-2"><strong>{{ $garantia->descricao }}</strong></p>
                                                    
                                                    <!-- Status da Garantia -->
                                                    <p class="mb-2">
                                                        <span class="badge bg-{{ $garantia->status_cor }}">
                                                            <i class="bx bx-shield-quarter"></i> {{ $garantia->status_nome }}
                                                        </span>
                                                        @if($garantia->isLiberada() && $garantia->data_liberacao)
                                                            <br><small class="text-muted">Liberada em: {{ $garantia->data_liberacao->format('d/m/Y H:i') }}</small>
                                                        @endif
                                                        @if($garantia->isExecutada() && $garantia->data_execucao)
                                                            <br><small class="text-muted">Executada em: {{ $garantia->data_execucao->format('d/m/Y H:i') }}</small>
                                                        @endif
                                                    </p>
                                                    
                                                    @if($garantia->valor_avaliado)
                                                        <p class="mb-2 text-success">
                                                            <i class="bx bx-money"></i> {{ $garantia->valor_formatado }}
                                                        </p>
                                                    @endif
                                                    @if($garantia->localizacao)
                                                        <p class="mb-2 text-muted">
                                                            <i class="bx bx-map"></i> {{ $garantia->localizacao }}
                                                        </p>
                                                    @endif
                                                    @if($garantia->observacoes)
                                                        <p class="mb-2 small text-muted" style="white-space: pre-wrap;">{{ $garantia->observacoes }}</p>
                                                    @endif

                                                    <!-- Anexos -->
                                                    @if($garantia->anexos->count() > 0)
                                                        <hr>
                                                        <p class="mb-2"><strong>Anexos ({{ $garantia->anexos->count() }})</strong></p>
                                                        <div class="row g-2">
                                                            @foreach($garantia->anexos as $anexo)
                                                                <div class="col-4">
                                                                    <div class="position-relative">
                                                                        @if($anexo->isImagem())
                                                                            <a href="{{ $anexo->url }}" target="_blank">
                                                                                <img src="{{ $anexo->url }}" 
                                                                                     alt="{{ $anexo->nome_arquivo }}" 
                                                                                     class="img-thumbnail w-100" 
                                                                                     style="height: 80px; object-fit: cover;">
                                                                            </a>
                                                                        @else
                                                                            <a href="{{ $anexo->url }}" target="_blank" class="d-block text-center p-2 border rounded">
                                                                                <i class="{{ $anexo->icone }} font-size-24"></i>
                                                                                <small class="d-block text-truncate">{{ $anexo->nome_arquivo }}</small>
                                                                            </a>
                                                                        @endif
                                                                        @if(!$emprestimo->isFinalizado())
                                                                        <form action="{{ route('emprestimos.garantias.anexos.destroy', $anexo->id) }}" 
                                                                              method="POST" 
                                                                              class="position-absolute top-0 end-0 form-excluir-anexo">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 0 4px;" title="Excluir">
                                                                                <i class="bx bx-x"></i>
                                                                            </button>
                                                                        </form>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <!-- Upload de novos anexos -->
                                                    @if(!$emprestimo->isFinalizado())
                                                    <hr>
                                                    <form action="{{ route('emprestimos.garantias.anexos.upload', $garantia->id) }}" 
                                                          method="POST" 
                                                          enctype="multipart/form-data"
                                                          class="form-upload-anexo">
                                                        @csrf
                                                        <div class="input-group input-group-sm">
                                                            <input type="file" name="arquivo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                                            <button type="submit" class="btn btn-outline-primary">
                                                                <i class="bx bx-upload"></i>
                                                            </button>
                                                        </div>
                                                        <small class="text-muted">Máx. 5MB</small>
                                                    </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Resumo das garantias -->
                                <div class="alert alert-info mt-3 mb-0">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><i class="bx bx-shield-quarter"></i> Total de Garantias:</strong> 
                                            {{ $emprestimo->garantias->count() }}
                                        </div>
                                        <div class="col-md-6">
                                            <strong><i class="bx bx-money"></i> Valor Total Avaliado:</strong> 
                                            R$ {{ number_format($emprestimo->valor_total_garantias, 2, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-error"></i>
                                    <strong>Atenção:</strong> Este empréstimo do tipo Empenho ainda não possui garantias cadastradas.
                                    @if(!$emprestimo->isFinalizado())
                                    <button type="button" class="btn btn-warning btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalAdicionarGarantia">
                                        <i class="bx bx-plus"></i> Adicionar Garantia
                                    </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Cheques (Troca de Cheque) -->
                @if($emprestimo->isTrocaCheque())
                    <div class="card mt-3 border-info">
                        <div class="card-header bg-info-subtle d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">
                                <i class="bx bx-money"></i> 
                                Cheques para Depósito ({{ $emprestimo->cheques->count() }})
                            </h4>
                            @if(!$emprestimo->isFinalizado())
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarCheque">
                                <i class="bx bx-plus"></i> Adicionar Cheque
                            </button>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($emprestimo->cheques->count() > 0)
                                <!-- Resumo dos Cheques -->
                                <div class="alert alert-info mb-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong><i class="bx bx-money"></i> Valor Total dos Cheques:</strong> 
                                            <br>R$ {{ number_format($emprestimo->valor_total_cheques, 2, ',', '.') }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong><i class="bx bx-calculator"></i> Juros Descontados:</strong> 
                                            <br>R$ {{ number_format($emprestimo->valor_total_juros_cheques, 2, ',', '.') }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong><i class="bx bx-check-circle"></i> Valor Líquido a Pagar:</strong> 
                                            <br><span class="h5 text-success">R$ {{ number_format($emprestimo->valor_liquido_cheques, 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lista de Cheques -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-cheques">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Banco</th>
                                                <th>Agência</th>
                                                <th>Conta</th>
                                                <th>Nº Cheque</th>
                                                <th>Vencimento</th>
                                                <th>Valor</th>
                                                <th>Juros</th>
                                                <th>Valor Líquido</th>
                                                <th>Status</th>
                                                <th class="text-nowrap" style="min-width: 200px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($emprestimo->cheques->sortBy('data_vencimento') as $cheque)
                                                @php
                                                    $chequeData = [
                                                        'id' => $cheque->id,
                                                        'banco' => $cheque->banco,
                                                        'agencia' => $cheque->agencia,
                                                        'conta' => $cheque->conta,
                                                        'numero_cheque' => $cheque->numero_cheque,
                                                        'data_vencimento' => $cheque->data_vencimento->format('Y-m-d'),
                                                        'valor_cheque' => $cheque->valor_cheque,
                                                        'taxa_juros' => $cheque->taxa_juros ?? '',
                                                        'portador' => $cheque->portador ?? '',
                                                        'observacoes' => $cheque->observacoes ?? '',
                                                    ];
                                                @endphp
                                                <tr class="{{ $cheque->isVencido() ? 'table-warning' : ($cheque->isCompensado() ? 'table-success' : ($cheque->isDevolvido() ? 'table-danger' : '')) }}"
                                                    data-cheque='@json($chequeData)'>
                                                    <td>{{ $cheque->banco }}</td>
                                                    <td>{{ $cheque->agencia }}</td>
                                                    <td>{{ $cheque->conta }}</td>
                                                    <td><strong>{{ $cheque->numero_cheque }}</strong></td>
                                                    <td>{{ $cheque->data_vencimento->format('d/m/Y') }}</td>
                                                    <td>R$ {{ number_format($cheque->valor_cheque, 2, ',', '.') }}</td>
                                                    <td>R$ {{ number_format($cheque->valor_juros, 2, ',', '.') }}</td>
                                                    <td>R$ {{ number_format($cheque->valor_liquido, 2, ',', '.') }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $cheque->status_cor }}">
                                                            {{ $cheque->status_nome }}
                                                        </span>
                                                        @if($cheque->isVencido())
                                                            <br>
                                                            <small class="text-danger">
                                                                Vencido há {{ $cheque->calcularDiasEmAtraso() }} dia(s)
                                                            </small>
                                                        @endif
                                                    </td>
                                                    <td class="text-nowrap">
                                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                                            {{-- Ações por status (fora do dropdown para não cortar) --}}
                                                            @if($cheque->isAguardando() && auth()->user()->hasAnyRole(['administrador', 'gestor']))
                                                                <button type="button" class="btn btn-sm btn-primary" 
                                                                        onclick="depositarCheque({{ $cheque->id }})"
                                                                        title="Marcar como depositado">
                                                                    <i class="bx bx-upload"></i>
                                                                </button>
                                                            @endif
                                                            @if($cheque->isDepositado() && auth()->user()->hasAnyRole(['administrador', 'gestor']))
                                                                <button type="button" class="btn btn-sm btn-success" 
                                                                        onclick="compensarCheque({{ $cheque->id }})"
                                                                        title="Marcar como compensado">
                                                                    <i class="bx bx-check"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger" 
                                                                        onclick="devolverCheque({{ $cheque->id }})"
                                                                        title="Marcar como devolvido">
                                                                    <i class="bx bx-x"></i>
                                                                </button>
                                                            @endif
                                                            @if($cheque->isDevolvido())
                                                                @if(auth()->user()->hasAnyRole(['administrador', 'gestor']))
                                                                    <a href="{{ route('emprestimos.cheques.pagar', $cheque->id) }}" 
                                                                       class="btn btn-sm btn-success" 
                                                                       title="Registrar pagamento">
                                                                        <i class="bx bx-money"></i>
                                                                    </a>
                                                                @endif
                                                                @if(auth()->user()->hasAnyRole(['administrador', 'gestor']) || $emprestimo->consultor_id === auth()->id())
                                                                    <button type="button" class="btn btn-sm btn-info" 
                                                                            onclick="abrirModalSubstituirCheque({{ $cheque->id }})"
                                                                            title="Substituir por novo cheque">
                                                                        <i class="bx bx-transfer-alt"></i>
                                                                    </button>
                                                                @endif
                                                            @endif
                                                            {{-- Dropdown Editar/Excluir: boundary viewport para não ser cortado pelo scroll --}}
                                                            @if(!$emprestimo->isFinalizado())
                                                                <div class="dropdown dropdown-cheque-actions">
                                                                    <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                                            data-bs-toggle="dropdown" 
                                                                            data-bs-boundary="viewport"
                                                                            data-bs-reference="toggle"
                                                                            aria-expanded="false"
                                                                            title="Mais ações">
                                                                        <i class="bx bx-dots-vertical-rounded"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                                        <li>
                                                                            <button type="button" class="dropdown-item" onclick="editarCheque(this)">
                                                                                <i class="bx bx-edit"></i> Editar
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <form action="{{ route('emprestimos.cheques.destroy', $cheque->id) }}" method="POST" class="d-inline form-excluir-cheque">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button type="submit" class="dropdown-item text-danger">
                                                                                    <i class="bx bx-trash"></i> Excluir
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-error"></i>
                                    <strong>Atenção:</strong> Este empréstimo do tipo Troca de Cheque ainda não possui cheques cadastrados.
                                    @if(!$emprestimo->isFinalizado())
                                    <button type="button" class="btn btn-info btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalAdicionarCheque">
                                        <i class="bx bx-plus"></i> Adicionar Cheque
                                    </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Modal Adicionar Cheque -->
                    <div class="modal fade" id="modalAdicionarCheque" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form action="{{ route('emprestimos.cheques.store', $emprestimo->id) }}" method="POST" id="formAdicionarCheque">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bx bx-money"></i> Adicionar Cheque
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Banco <span class="text-danger">*</span></label>
                                                <input type="text" name="banco" class="form-control" required 
                                                       placeholder="Ex: Banco do Brasil, Caixa, Bradesco...">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Agência <span class="text-danger">*</span></label>
                                                <input type="text" name="agencia" class="form-control" required>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Conta <span class="text-danger">*</span></label>
                                                <input type="text" name="conta" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Número do Cheque <span class="text-danger">*</span></label>
                                                <input type="text" name="numero_cheque" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                                                <input type="date" name="data_vencimento" id="cheque-data-vencimento" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Valor do Cheque <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" name="valor_cheque" id="cheque-valor" class="form-control" inputmode="decimal"
                                                           data-mask-money="brl" placeholder="0,00" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Taxa de Juros (%)</label>
                                                <input type="number" name="taxa_juros" id="cheque-taxa-juros" class="form-control" 
                                                       step="0.01" min="0" max="100" 
                                                       value="{{ $emprestimo->taxa_juros ?? '' }}">
                                                <small class="text-muted">Deixe em branco para usar a taxa do empréstimo</small>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Portador</label>
                                            <input type="text" name="portador" class="form-control" 
                                                   placeholder="Nome do portador do cheque (opcional)">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="alert alert-info mb-0">
                                            <i class="bx bx-info-circle"></i>
                                            <strong>Juros serão calculados automaticamente</strong> baseado na data de vencimento e taxa de juros informada.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-info">
                                            <i class="bx bx-plus"></i> Adicionar Cheque
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Editar Cheque -->
                    <div class="modal fade" id="modalEditarCheque" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form id="formEditarCheque" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bx bx-edit"></i> Editar Cheque
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Banco <span class="text-danger">*</span></label>
                                                <input type="text" name="banco" id="edit-cheque-banco" class="form-control" required>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Agência <span class="text-danger">*</span></label>
                                                <input type="text" name="agencia" id="edit-cheque-agencia" class="form-control" required>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Conta <span class="text-danger">*</span></label>
                                                <input type="text" name="conta" id="edit-cheque-conta" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Número do Cheque <span class="text-danger">*</span></label>
                                                <input type="text" name="numero_cheque" id="edit-cheque-numero" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                                                <input type="date" name="data_vencimento" id="edit-cheque-data-vencimento" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Valor do Cheque <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" name="valor_cheque" id="edit-cheque-valor" class="form-control" inputmode="decimal"
                                                           data-mask-money="brl" placeholder="0,00" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Taxa de Juros (%)</label>
                                                <input type="number" name="taxa_juros" id="edit-cheque-taxa-juros" class="form-control" 
                                                       step="0.01" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Portador</label>
                                            <input type="text" name="portador" id="edit-cheque-portador" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" id="edit-cheque-observacoes" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-info">
                                            <i class="bx bx-check"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Substituir Cheque (cheque devolvido) -->
                    <div class="modal fade" id="modalSubstituirCheque" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form id="formSubstituirCheque" method="POST" action="">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bx bx-refresh"></i> Substituir por Novo Cheque
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-info mb-3">
                                            Cadastre os dados do novo cheque que substituirá o devolvido.
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Banco <span class="text-danger">*</span></label>
                                                <input type="text" name="banco" class="form-control" required placeholder="Ex: Banco do Brasil, Caixa...">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Agência <span class="text-danger">*</span></label>
                                                <input type="text" name="agencia" class="form-control" required>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Conta <span class="text-danger">*</span></label>
                                                <input type="text" name="conta" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Número do Cheque <span class="text-danger">*</span></label>
                                                <input type="text" name="numero_cheque" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Data de Vencimento <span class="text-danger">*</span></label>
                                                <input type="date" name="data_vencimento" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Valor do Cheque <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" name="valor_cheque" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Taxa de Juros (%)</label>
                                                <input type="number" name="taxa_juros" class="form-control" step="0.01" min="0" max="100" value="{{ $emprestimo->taxa_juros ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Portador</label>
                                            <input type="text" name="portador" class="form-control" placeholder="Opcional">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-info">
                                            <i class="bx bx-refresh"></i> Substituir Cheque
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                    <!-- Modal Adicionar Garantia -->
                    <div class="modal fade" id="modalAdicionarGarantia" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="{{ route('emprestimos.garantias.store', $emprestimo->id) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bx bx-shield-quarter"></i> Adicionar Garantia
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                                            <select name="categoria" class="form-select" required>
                                                <option value="imovel">🏠 Imóvel</option>
                                                <option value="veiculo">🚗 Veículo</option>
                                                <option value="outros" selected>📦 Outros</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Descrição do Bem <span class="text-danger">*</span></label>
                                            <input type="text" name="descricao" class="form-control" required 
                                                   placeholder="Ex: Moto Honda CG 160, Casa 3 quartos, Notebook Dell...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Valor Avaliado</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="valor_avaliado" class="form-control" inputmode="decimal" data-mask-money="brl"
                                                       placeholder="Valor estimado do bem">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Localização</label>
                                            <input type="text" name="localizacao" class="form-control" 
                                                   placeholder="Onde o bem está? Ex: Na casa do cliente, No depósito...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" class="form-control" rows="2" 
                                                      placeholder="Informações adicionais sobre o bem"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Anexos (Fotos/Documentos)</label>
                                            <input type="file" name="anexos[]" class="form-control" multiple
                                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                            <small class="text-muted">Máx. 5MB por arquivo. Pode selecionar múltiplos arquivos.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bx bx-plus"></i> Adicionar Garantia
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Editar Garantia -->
                    <div class="modal fade" id="modalEditarGarantia" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form id="formEditarGarantia" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="bx bx-edit"></i> Editar Garantia
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Categoria <span class="text-danger">*</span></label>
                                            <select name="categoria" id="edit-categoria" class="form-select" required>
                                                <option value="imovel">🏠 Imóvel</option>
                                                <option value="veiculo">🚗 Veículo</option>
                                                <option value="outros">📦 Outros</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Descrição do Bem <span class="text-danger">*</span></label>
                                            <input type="text" name="descricao" id="edit-descricao" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Valor Avaliado</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="text" name="valor_avaliado" id="edit-valor" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="0,00">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Localização</label>
                                            <input type="text" name="localizacao" id="edit-localizacao" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacoes" id="edit-observacoes" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Adicionar mais anexos</label>
                                            <input type="file" name="anexos[]" class="form-control" multiple
                                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                            <small class="text-muted">Máx. 5MB por arquivo</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-save"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <!-- Modais de Executar Garantia -->
                @if($emprestimo->isEmpenho() && $emprestimo->garantias->where('status', 'ativa')->count() > 0)
                    @foreach($emprestimo->garantias->where('status', 'ativa') as $garantia)
                        <div class="modal fade" id="executarGarantiaModal{{ $garantia->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('emprestimos.garantias.executar', ['id' => $emprestimo->id, 'garantiaId' => $garantia->id]) }}" method="POST">
                                        @csrf
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">
                                                <i class="bx bx-shield-x"></i> Executar Garantia
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-danger">
                                                <i class="bx bx-error-circle"></i>
                                                <strong>Atenção:</strong> Esta ação irá:
                                                <ul class="mb-0 mt-2">
                                                    <li>Executar a garantia (marcar como executada)</li>
                                                    <li>Finalizar o empréstimo automaticamente</li>
                                                    <li>Não poderá ser desfeita</li>
                                                </ul>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <strong>Empréstimo:</strong> #{{ $emprestimo->id }}<br>
                                                <strong>Cliente:</strong> {{ $emprestimo->cliente->nome }}<br>
                                                <strong>Garantia:</strong> {{ $garantia->categoria_nome }} - {{ $garantia->descricao }}<br>
                                                @if($garantia->valor_avaliado)
                                                    <strong>Valor:</strong> {{ $garantia->valor_formatado }}
                                                @endif
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Observações/Motivo <span class="text-danger">*</span></label>
                                                <textarea name="observacoes" class="form-control" rows="4" required 
                                                          placeholder="Descreva o motivo da execução da garantia (mínimo 10 caracteres)"></textarea>
                                                <small class="text-muted">Este campo é obrigatório e será registrado na auditoria.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bx bx-shield-x"></i> Confirmar Execução
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if(!$emprestimo->isTrocaCheque())
                <!-- Parcelas -->
                <div class="card mt-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="card-title mb-0">Parcelas ({{ $emprestimo->parcelas->count() }})</h4>
                            @php
                                $parcelasPendentesDiaria = $emprestimo->frequencia === 'diaria'
                                    ? $emprestimo->parcelas->reject(fn ($p) => $p->isQuitada())
                                    : collect();
                            @endphp
                            @php
                                $parcelasAbertasMulti = $emprestimo->parcelas->filter(function ($p) {
                                    if ($p->isQuitada()) return false;
                                    return ((float)$p->valor - (float)($p->valor_pago ?? 0)) > 0;
                                });
                            @endphp
                            @if($emprestimo->isAtivo() && $parcelasAbertasMulti->count() >= 2)
                                <a href="{{ route('pagamentos.multi-parcelas.create', $emprestimo->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-list-check"></i> Pagar mais de 1 parcela
                                </a>
                            @endif
                            @if($parcelasPendentesDiaria->isNotEmpty())
                                <a href="{{ route('pagamentos.quitar-diarias.create', $emprestimo->id) }}" class="btn btn-primary btn-sm">
                                    <i class="bx bx-check-double"></i> Quitar todas as parcelas diárias
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Valor</th>
                                        <th>Valor Pago</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Pagamentos</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emprestimo->parcelas->sortBy('numero') as $parcela)
                                        <tr class="{{ $parcela->isAtrasada() ? 'table-danger' : '' }}">
                                            <td>{{ $parcela->numero }}</td>
                                            <td>
                                                R$ {{ number_format($parcela->valor, 2, ',', '.') }}
                                                @if($emprestimo->isPrice())
                                                    <br><small class="text-muted">
                                                        Juros: R$ {{ number_format($parcela->valor_juros ?? 0, 2, ',', '.') }} | 
                                                        Amort.: R$ {{ number_format($parcela->valor_amortizacao ?? 0, 2, ',', '.') }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>R$ {{ number_format($parcela->valor_pago, 2, ',', '.') }}</td>
                                            <td>{{ $parcela->data_vencimento->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $parcela->status_cor }}">
                                                    {{ $parcela->status_nome }}
                                                </span>
                                                @if($parcela->hasPagamentoProdutoObjetoPendente())
                                                    <span class="badge bg-warning text-dark ms-1" title="Pagamento em produto/objeto aguardando aceite">Aguardando aceite</span>
                                                @endif
                                                @if($parcela->hasSolicitacaoJurosContratoReduzidoPendente())
                                                    <span class="badge bg-info ms-1" title="Pagamento com valor inferior aguardando aprovação do gestor">Aguardando aprovação (valor inferior)</span>
                                                @endif
                                                @if(!$parcela->isQuitada() && $parcela->hasPagamentoProdutoObjetoRejeitado())
                                                    <span class="badge bg-danger ms-1" title="Pagamento em produto/objeto foi recusado">Recusado</span>
                                                @endif
                                                @if($parcela->dias_atraso > 0 && !$parcela->isQuitada())
                                                    <small class="text-danger">({{ $parcela->dias_atraso }} dias)</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($parcela->pagamentos->count() > 0)
                                                    <div class="d-flex gap-1">
                                                        @foreach($parcela->pagamentos as $pagamento)
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-info" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#pagamentoModal{{ $pagamento->id }}"
                                                                    title="Ver detalhes do pagamento">
                                                                <i class="bx bx-receipt"></i>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$parcela->isQuitada())
                                                    <a href="{{ route('pagamentos.create', ['parcela_id' => $parcela->id]) }}" 
                                                       class="btn btn-sm btn-success">
                                                        <i class="bx bx-money"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                Nenhuma parcela gerada ainda.
                                                @if($emprestimo->status === 'pendente')
                                                    <br><small class="text-muted">Aguarde a aprovação do empréstimo.</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modais de Detalhes dos Pagamentos -->
        @foreach($emprestimo->parcelas as $parcela)
            @foreach($parcela->pagamentos as $pagamento)
                <div class="modal fade" id="pagamentoModal{{ $pagamento->id }}" tabindex="-1" aria-labelledby="pagamentoModalLabel{{ $pagamento->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="pagamentoModalLabel{{ $pagamento->id }}">
                                    Detalhes do Pagamento - Parcela #{{ $parcela->numero }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @if($pagamento->isProdutoObjeto() && $pagamento->isPendenteAceite())
                                    <div class="alert alert-warning mb-3">
                                        <i class="bx bx-time-five"></i>
                                        <strong>Pagamento em produto/objeto – aguardando aceite.</strong><br>
                                        Este pagamento será creditado na parcela após um gestor ou administrador aceitar em <strong>Liberações → Pagamentos produto/objeto</strong>.
                                    </div>
                                @elseif($pagamento->isProdutoObjeto() && $pagamento->isRejeitado())
                                    <div class="alert alert-danger mb-3">
                                        <i class="bx bx-x-circle"></i>
                                        <strong>Pagamento em produto/objeto recusado.</strong><br>
                                        Este pagamento não foi aceito. A parcela permanece pendente.
                                        @if($pagamento->rejeitadoPor)
                                            <br><small>Recusado por {{ $pagamento->rejeitadoPor->name }} em {{ $pagamento->rejeitado_em ? $pagamento->rejeitado_em->format('d/m/Y H:i') : '' }}</small>
                                        @endif
                                    </div>
                                @endif
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Valor Original da Parcela:</strong><br>
                                        <span class="h6">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Método de Pagamento:</strong><br>
                                        <span class="badge bg-info">{{ $pagamento->isProdutoObjeto() ? 'Produto/Objeto' : ucfirst($pagamento->metodo) }}</span>
                                        @if($pagamento->isProdutoObjeto() && $pagamento->isPendenteAceite())
                                            <span class="badge bg-warning text-dark ms-1">Pendente aceite</span>
                                        @elseif($pagamento->isProdutoObjeto() && $pagamento->isRejeitado())
                                            <span class="badge bg-danger ms-1">Recusado</span>
                                        @endif
                                    </div>
                                </div>

                                @if($pagamento->isProdutoObjeto() && ($pagamento->hasProdutoObjetoItens() || $pagamento->produto_nome || $pagamento->produto_descricao || $pagamento->produto_valor !== null || !empty($pagamento->produto_imagens)))
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <div class="card border-warning">
                                                <div class="card-header bg-warning-subtle py-2">
                                                    <strong><i class="bx bx-package"></i> Dados do produto/objeto</strong>
                                                </div>
                                                <div class="card-body">
                                                    @if($pagamento->hasProdutoObjetoItens())
                                                        @foreach($pagamento->produtoObjetoItens as $item)
                                                            <div class="border rounded p-2 mb-2 bg-light">
                                                                <strong>{{ $item->nome }}</strong>
                                                                @if($item->quantidade > 1) <span class="badge bg-secondary">{{ $item->quantidade }} un.</span> @endif
                                                                @if($item->valor_estimado !== null) — R$ {{ number_format($item->valor_estimado, 2, ',', '.') }} @endif
                                                                @if($item->descricao)<p class="mb-1 small text-muted">{{ $item->descricao }}</p>@endif
                                                                @if(!empty($item->imagens))
                                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                                        @foreach($item->imagens_urls as $url)
                                                                            <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="" class="img-thumbnail" style="max-height: 80px; max-width: 100px; object-fit: contain;"></a>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <dl class="row mb-0">
                                                            @if($pagamento->produto_nome)
                                                                <dt class="col-sm-3">Nome</dt>
                                                                <dd class="col-sm-9">{{ $pagamento->produto_nome }}</dd>
                                                            @endif
                                                            @if($pagamento->produto_descricao)
                                                                <dt class="col-sm-3">Descrição</dt>
                                                                <dd class="col-sm-9">{{ $pagamento->produto_descricao }}</dd>
                                                            @endif
                                                            @if($pagamento->produto_valor !== null)
                                                                <dt class="col-sm-3">Valor do produto</dt>
                                                                <dd class="col-sm-9">R$ {{ number_format($pagamento->produto_valor, 2, ',', '.') }}</dd>
                                                            @endif
                                                        </dl>
                                                        @if(!empty($pagamento->produto_imagens))
                                                            <p class="mb-2 mt-2"><strong>Imagens</strong></p>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                @foreach($pagamento->produto_imagens_urls as $url)
                                                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="d-inline-block">
                                                                        <img src="{{ $url }}" alt="Produto/objeto" class="img-thumbnail" style="max-height: 100px; max-width: 140px; object-fit: contain;">
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                
                                @if($pagamento->hasJuros())
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <div class="alert alert-warning mb-0">
                                                <strong>Juros/Multa Aplicados:</strong><br>
                                                <strong>Tipo:</strong> {{ $pagamento->descricao_tipo_juros }}<br>
                                                @if($pagamento->taxa_juros_aplicada)
                                                    <strong>Taxa Aplicada:</strong> {{ number_format($pagamento->taxa_juros_aplicada, 2, ',', '.') }}%<br>
                                                @endif
                                                <strong>Valor de Juros:</strong> R$ {{ number_format($pagamento->valor_juros, 2, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Valor Total Pago:</strong><br>
                                        <span class="h5 text-success">R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Data do Pagamento:</strong><br>
                                        {{ $pagamento->data_pagamento->format('d/m/Y') }}
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Registrado por:</strong><br>
                                        {{ $pagamento->consultor->name ?? '-' }}
                                    </div>
                                </div>
                                @if($pagamento->observacoes)
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <strong>Observações:</strong><br>
                                            <div class="alert alert-info mb-0">{{ $pagamento->observacoes }}</div>
                                        </div>
                                    </div>
                                @endif
                                @if($pagamento->hasComprovante())
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Comprovante:</strong><br>
                                            <div class="mt-2">
                                                @if($pagamento->isComprovanteImagem())
                                                    <div class="mb-2">
                                                        <img src="{{ $pagamento->comprovante_url }}" 
                                                             alt="Comprovante de Pagamento" 
                                                             class="img-thumbnail" 
                                                             style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                             onclick="window.open('{{ $pagamento->comprovante_url }}', '_blank')">
                                                    </div>
                                                    <a href="{{ $pagamento->comprovante_url }}" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bx bx-download"></i> Baixar Comprovante
                                                    </a>
                                                @else
                                                    <a href="{{ $pagamento->comprovante_url }}" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bx bx-download"></i> Baixar Comprovante
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Comprovante:</strong><br>
                                            <span class="text-muted">Não disponível</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
                @endif

    <!-- Modal Confirmar Pagamento ao Cliente -->
    @if($emprestimo->liberacao && 
        $emprestimo->liberacao->status === 'liberado' && 
        $emprestimo->status === 'aprovado' && 
        $emprestimo->liberacao->consultor_id == auth()->id())
    <div class="modal fade" id="confirmarPagamentoModal" tabindex="-1" aria-labelledby="confirmarPagamentoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('liberacoes.confirmar-pagamento', $emprestimo->liberacao->id) }}" 
                      method="POST" enctype="multipart/form-data"
                      class="form-confirmar-pagamento-cliente"
                      data-valor="{{ number_format($emprestimo->liberacao->valor_liberado, 2, ',', '.') }}"
                      data-cliente="{{ $emprestimo->cliente->nome }}"
                      data-emprestimo-id="{{ $emprestimo->id }}">
                    @csrf
                    <input type="hidden" name="redirect_to" value="emprestimos.show">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmarPagamentoModalLabel">Confirmar Pagamento ao Cliente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Valor:</strong> R$ {{ number_format($emprestimo->liberacao->valor_liberado, 2, ',', '.') }}<br>
                            <strong>Cliente:</strong> {{ $emprestimo->cliente->nome }}<br>
                            <strong>Empréstimo:</strong> #{{ $emprestimo->id }}
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

    <!-- Modal de Cancelamento de Empréstimo -->
    @if($podeCancelar)
    <div class="modal fade" id="cancelarEmprestimoModal" tabindex="-1" aria-labelledby="cancelarEmprestimoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('emprestimos.cancelar', $emprestimo->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="cancelarEmprestimoModalLabel">
                            <i class="bx bx-x-circle"></i> Cancelar Empréstimo
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bx bx-info-circle"></i>
                            <strong>Atenção!</strong> Esta ação não pode ser desfeita.
                        </div>
                        <div class="mb-3">
                            <strong>Empréstimo:</strong> #{{ $emprestimo->id }}<br>
                            <strong>Cliente:</strong> {{ $emprestimo->cliente->nome }}<br>
                            <strong>Valor:</strong> R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}
                        </div>
                        @if($emprestimo->liberacao && $emprestimo->liberacao->isLiberado())
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle"></i>
                                <strong>Importante:</strong> O dinheiro já foi liberado para o consultor. 
                                Ao cancelar, serão criadas movimentações de estorno para devolver o dinheiro ao gestor.
                            </div>
                        @endif
                        <div class="mb-3">
                            <label for="motivo_cancelamento" class="form-label">
                                Motivo do Cancelamento <span class="text-danger">*</span>
                            </label>
                            <textarea name="motivo_cancelamento" 
                                      id="motivo_cancelamento" 
                                      class="form-control @error('motivo_cancelamento') is-invalid @enderror" 
                                      rows="4" 
                                      required
                                      minlength="10"
                                      maxlength="1000"
                                      placeholder="Descreva o motivo do cancelamento (mínimo 10 caracteres)...">{{ old('motivo_cancelamento') }}</textarea>
                            @error('motivo_cancelamento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Mínimo 10 caracteres, máximo 1000 caracteres.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bx bx-x-circle"></i> Confirmar Cancelamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @endsection

    @section('scripts')
    <script>
        // Função para abrir modal de edição de garantia
        // Funções para Cheques
        function editarCheque(btn) {
            var row = btn.closest('tr');
            var data = JSON.parse(row.getAttribute('data-cheque'));
            document.getElementById('formEditarCheque').action = '{{ route("emprestimos.cheques.update", ":id") }}'.replace(':id', data.id);
            document.getElementById('edit-cheque-banco').value = data.banco || '';
            document.getElementById('edit-cheque-agencia').value = data.agencia || '';
            document.getElementById('edit-cheque-conta').value = data.conta || '';
            document.getElementById('edit-cheque-numero').value = data.numero_cheque || '';
            document.getElementById('edit-cheque-data-vencimento').value = data.data_vencimento || '';
            document.getElementById('edit-cheque-valor').value = data.valor_cheque || '';
            document.getElementById('edit-cheque-taxa-juros').value = data.taxa_juros || '';
            document.getElementById('edit-cheque-portador').value = data.portador || '';
            document.getElementById('edit-cheque-observacoes').value = data.observacoes || '';
            var modalEl = document.getElementById('modalEditarCheque');
            if (modalEl) {
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }

        function abrirModalSubstituirCheque(chequeId) {
            document.getElementById('formSubstituirCheque').action = '{{ route("emprestimos.cheques.substituir", ":id") }}'.replace(':id', chequeId);
            document.getElementById('formSubstituirCheque').reset();
            new bootstrap.Modal(document.getElementById('modalSubstituirCheque')).show();
        }

        function depositarCheque(chequeId) {
            Swal.fire({
                title: 'Depositar Cheque?',
                html: `
                    <form id="formDepositarCheque">
                        <div class="mb-3">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea id="observacoes-deposito" class="form-control" rows="3" placeholder="Ex: Depositado no banco X, agência Y..."></textarea>
                        </div>
                    </form>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#038edc',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, depositar!',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const observacoes = document.getElementById('observacoes-deposito').value;
                    return fetch('{{ route("emprestimos.cheques.depositar", ":id") }}'.replace(':id', chequeId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            observacoes: observacoes
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Erro ao depositar cheque');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Sucesso!', 'Cheque marcado como depositado.', 'success').then(() => {
                        location.reload();
                    });
                }
            }).catch(error => {
                Swal.fire('Erro!', error.message || 'Erro ao depositar cheque.', 'error');
            });
        }

        function compensarCheque(chequeId) {
            Swal.fire({
                title: 'Compensar Cheque?',
                html: `
                    <form id="formCompensarCheque">
                        <div class="mb-3">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea id="observacoes-compensacao" class="form-control" rows="3" placeholder="Ex: Cheque compensado com sucesso..."></textarea>
                        </div>
                    </form>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, compensar!',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const observacoes = document.getElementById('observacoes-compensacao').value;
                    return fetch('{{ route("emprestimos.cheques.compensar", ":id") }}'.replace(':id', chequeId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            observacoes: observacoes
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Erro ao compensar cheque');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Sucesso!', 'Cheque marcado como compensado.', 'success').then(() => {
                        location.reload();
                    });
                }
            }).catch(error => {
                Swal.fire('Erro!', error.message || 'Erro ao compensar cheque.', 'error');
            });
        }

        function devolverCheque(chequeId) {
            Swal.fire({
                title: 'Devolver Cheque?',
                html: `
                    <form id="formDevolverCheque">
                        <div class="mb-3">
                            <label class="form-label">Motivo da Devolução <span class="text-danger">*</span></label>
                            <textarea id="motivo-devolucao" class="form-control" rows="3" required placeholder="Ex: Sem fundos, conta encerrada..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea id="observacoes-devolucao" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, devolver!',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const motivo = document.getElementById('motivo-devolucao').value;
                    const observacoes = document.getElementById('observacoes-devolucao').value;
                    
                    if (!motivo || motivo.length < 10) {
                        Swal.showValidationMessage('O motivo da devolução deve ter pelo menos 10 caracteres.');
                        return false;
                    }
                    
                    return fetch('{{ route("emprestimos.cheques.devolver", ":id") }}'.replace(':id', chequeId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            motivo_devolucao: motivo,
                            observacoes: observacoes
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Erro ao devolver cheque');
                        }
                        return data;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Cheque Devolvido!', 'O cheque foi marcado como devolvido.', 'warning').then(() => {
                        location.reload();
                    });
                }
            }).catch(error => {
                Swal.fire('Erro!', error.message || 'Erro ao devolver cheque.', 'error');
            });
        }

        function editarGarantia(id, categoria, descricao, valor, localizacao, observacoes) {
            document.getElementById('formEditarGarantia').action = '/emprestimos/garantias/' + id;
            document.getElementById('edit-categoria').value = categoria;
            document.getElementById('edit-descricao').value = descricao;
            document.getElementById('edit-valor').value = valor || '';
            document.getElementById('edit-localizacao').value = localizacao || '';
            document.getElementById('edit-observacoes').value = observacoes || '';
            
            var modal = new bootstrap.Modal(document.getElementById('modalEditarGarantia'));
            modal.show();
        }

        // Confirmação para excluir garantia
        document.querySelectorAll('.form-excluir-garantia').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Excluir Garantia?',
                    text: 'Esta ação não pode ser desfeita. Todos os anexos também serão excluídos.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, excluir!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // Confirmação para excluir anexo
        document.querySelectorAll('.form-excluir-anexo').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Excluir Anexo?',
                    text: 'Esta ação não pode ser desfeita.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, excluir!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        // Confirmação para excluir cheque
        document.querySelectorAll('.form-excluir-cheque').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var formEl = this;
                Swal.fire({
                    title: 'Excluir Cheque?',
                    text: 'O cheque será removido do empréstimo. Esta ação não pode ser desfeita.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, excluir!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formEl.submit();
                    }
                });
            });
        });
    </script>
    @endsection