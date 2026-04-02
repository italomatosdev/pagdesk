@extends('layouts.master')
@section('title')
    Registrar Pagamento
@endsection
@section('page-title')
    Registrar Pagamento
@endsection
@section('body')

    <body>

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Registrar Pagamento</h4>
                    </div>
                    <div class="card-body">
                        @if($parcela)
                            @php
                                $diasAtraso = $parcela->calcularDiasAtraso();
                                $estaAtrasada = $parcela->isAtrasada();
                                $operacao = $parcela->emprestimo->operacao;
                                $temTaxaOperacao = $operacao->taxa_juros_atraso > 0;
                                $mostrarOpcoesJuros = $estaAtrasada || (isset($renovar) && $renovar);
                            @endphp
                            @php
                                $valorParcelaTotal = (float) $parcela->valor;
                                $valorPrincipalParcela = $parcela->valor_amortizacao !== null && (float) $parcela->valor_amortizacao > 0 ? (float) $parcela->valor_amortizacao : $valorParcelaTotal;
                                $valorJurosContratoParcela = $parcela->valor_juros !== null && (float) $parcela->valor_juros > 0 ? (float) $parcela->valor_juros : 0;
                                $mostrarDetalheJuros = $valorJurosContratoParcela > 0;
                                $faltaPagarParcela = max(0, round($valorParcelaTotal - (float) ($parcela->valor_pago ?? 0), 2));
                            @endphp
                            <div class="alert alert-{{ $estaAtrasada ? 'warning' : 'info' }} mb-0">
                                <strong>Parcela:</strong> #{{ $parcela->numero }} de {{ $parcela->emprestimo->numero_parcelas }}<br>
                                <strong>Cliente:</strong> {{ $nomeClienteExibicao ?? ($parcela->emprestimo->cliente->nome ?? 'Cliente') }}<br>
                                <strong>Vencimento:</strong> {{ $parcela->data_vencimento->format('d/m/Y') }}<br>
                                @if($estaAtrasada)
                                    <strong class="text-danger">Dias de atraso:</strong> {{ $diasAtraso }} dias<br>
                                    @if($temTaxaOperacao)
                                        <strong>Taxa da operação (juros de atraso):</strong> {{ number_format($operacao->taxa_juros_atraso, 2, ',', '.') }}% {{ $operacao->tipo_calculo_juros === 'por_dia' ? 'ao dia' : 'ao mês' }}<br>
                                    @endif
                                @elseif(isset($renovar) && $renovar)
                                    <span class="badge bg-info">Renovação de empréstimo</span><br>
                                @endif
                                @if($mostrarDetalheJuros)
                                    <strong>Valor principal:</strong> R$ {{ number_format($valorPrincipalParcela, 2, ',', '.') }}<br>
                                    <strong>Juros do contrato:</strong> R$ {{ number_format($valorJurosContratoParcela, 2, ',', '.') }}<br>
                                    <strong>Valor da parcela:</strong> R$ {{ number_format($valorParcelaTotal, 2, ',', '.') }}<br>
                                @else
                                    <strong>Valor da parcela:</strong> R$ {{ number_format($valorParcelaTotal, 2, ',', '.') }}<br>
                                @endif
                                <strong class="text-nowrap" title="Saldo nominal em aberto; juros de atraso entram no valor do pagamento conforme opções abaixo">Falta pagar (parcela):</strong>
                                @if($parcela->isQuitada() || $faltaPagarParcela <= 0)
                                    <span class="text-muted">—</span>
                                @else
                                    R$ {{ number_format($faltaPagarParcela, 2, ',', '.') }}
                                @endif
                            </div>
                        @endif

                        <form action="{{ route('pagamentos.store') }}" method="POST" enctype="multipart/form-data" id="formRegistrarPagamento" data-no-loading>
                            @csrf

                            @if($parcela)
                                <input type="hidden" name="parcela_id" value="{{ $parcela->id }}">
                                @if(isset($returnTo))
                                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                @endif
                            @else
                                <div class="mb-3">
                                    <label class="form-label">Parcela <span class="text-danger">*</span></label>
                                    <select name="parcela_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <!-- Aqui você pode adicionar um select de parcelas pendentes -->
                                    </select>
                                </div>
                            @endif

                            @php
                                $emprestimo = $parcela ? $parcela->emprestimo : null;
                                $ehMensal = $emprestimo && $emprestimo->frequencia === 'mensal';
                                // Mensal: pode renovar antes do vencimento. Demais frequências: só quando atrasada
                                $podeRenovar = $emprestimo
                                    && $emprestimo->status === 'ativo'
                                    && $emprestimo->numero_parcelas === 1
                                    && ($estaAtrasada || $ehMensal);
                                
                                if ($podeRenovar && !$emprestimo->relationLoaded('parcelas')) {
                                    $emprestimo->load('parcelas');
                                }
                                
                                $jurosJaPagos = $podeRenovar ? $emprestimo->jurosJaForamPagos() : false;
                                $valorJuros = $podeRenovar ? $emprestimo->calcularValorJuros() : 0;
                                
                                // Calcular juros automático para renovação (baseado no principal e dias de atraso)
                                $valorPrincipal = $podeRenovar ? $emprestimo->valor_total : 0;
                                $taxaJurosRenovacao = $podeRenovar && $operacao->taxa_juros_atraso > 0 
                                    ? $operacao->taxa_juros_atraso 
                                    : 0;
                                $tipoCalculoRenovacao = $podeRenovar 
                                    ? ($operacao->tipo_calculo_juros ?? 'por_dia') 
                                    : 'por_dia';
                                
                                // Calcular valor de juros automático para renovação
                                // Juros originais + Juros por atraso
                                $jurosOriginais = $podeRenovar ? $emprestimo->calcularValorJuros() : 0;
                                $jurosAtraso = 0;
                                if ($podeRenovar && $taxaJurosRenovacao > 0 && $diasAtraso > 0) {
                                    if ($tipoCalculoRenovacao === 'por_dia') {
                                        $jurosAtraso = $valorPrincipal * ($taxaJurosRenovacao / 100) * $diasAtraso;
                                    } else {
                                        $jurosAtraso = $valorPrincipal * ($taxaJurosRenovacao / 100) * ($diasAtraso / 30);
                                    }
                                    $jurosAtraso = round($jurosAtraso, 2);
                                }
                                $valorJurosRenovacaoAutomatico = round($jurosOriginais + $jurosAtraso, 2);
                            @endphp

                            @if($parcela && ($estaAtrasada || (isset($renovar) && $renovar && $podeRenovar && !$jurosJaPagos)))
                                <!-- Juros de atraso (para parcelas atrasadas ou quando renovação está disponível) -->
                                <div class="mb-3">
                                    <label class="form-label"><strong>Juros de atraso</strong></label>
                                    <div class="card border">
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <input type="radio" name="tipo_juros" id="tipo_juros_nenhum" value="nenhum" 
                                                       {{ (!isset($renovar) || !$renovar) ? 'checked' : '' }} class="form-check-input">
                                                <label for="tipo_juros_nenhum" class="form-check-label ms-2">
                                                    <strong>Sem juros de atraso</strong> - Pagar apenas o valor da parcela
                                                </label>
                                                <div class="ms-4 text-muted" id="resultado_nenhum">
                                                    Total: R$ {{ number_format($parcela->valor, 2, ',', '.') }}
                                                </div>
                                            </div>

                                            @if($mostrarDetalheJuros)
                                            <div class="mb-2">
                                                <input type="radio" name="tipo_juros" id="tipo_juros_valor_inferior" value="valor_inferior" class="form-check-input">
                                                <label for="tipo_juros_valor_inferior" class="form-check-label ms-2">
                                                    <strong>Pagar valor inferior</strong> - Juros do contrato reduzido (ex.: cliente paga menos que o total; exige aprovação)
                                                </label>
                                                <div class="ms-4 mt-2" id="campo_valor_inferior" style="display: none;"
                                                     data-valor-min="{{ number_format($valorPrincipalParcela, 2, '.', '') }}"
                                                     data-valor-max="{{ number_format($valorParcelaTotal, 2, '.', '') }}">
                                                    <div class="input-group" style="max-width: 300px;">
                                                        <span class="input-group-text">R$</span>
                                                        <input type="text" name="valor_inferior_input" id="valor_inferior_input" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Ex: 640,00">
                                                    </div>
                                                    <small class="text-muted">Mín. principal: R$ {{ number_format($valorPrincipalParcela, 2, ',', '.') }} · Máx. parcela: R$ {{ number_format($valorParcelaTotal, 2, ',', '.') }}</small>
                                                    <div class="text-muted mt-2" id="resultado_valor_inferior">
                                                        Valor a pagar: R$ <span id="total_valor_inferior">{{ number_format($valorParcelaTotal, 2, ',', '.') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                            
                                            @if($podeRenovar && !$jurosJaPagos)
                                            <div class="mb-2 border-top pt-2 mt-2">
                                                <input type="radio" name="tipo_juros" id="tipo_juros_renovacao" value="renovacao" 
                                                       {{ (isset($renovar) && $renovar) ? 'checked' : '' }} class="form-check-input">
                                                <label for="tipo_juros_renovacao" class="form-check-label ms-2">
                                                    <strong>Renovar Empréstimo</strong>
                                                </label>
                                                
                                                <!-- Sub-opções de Renovação (aparecem quando renovação está selecionada) -->
                                                <div class="ms-4 mt-2 ps-3 border-start border-2" id="sub_opcoes_renovacao" style="display: {{ (isset($renovar) && $renovar) ? 'block' : 'none' }};">
                                                    @php
                                                        $isRenovarNenhum = (isset($renovar) && $renovar && ($renovacaoTipo ?? '') === 'nenhum');
                                                        $isRenovarAbate = (isset($renovar) && $renovar && ($renovacaoTipo ?? '') === 'com_abate');
                                                        $isRenovarAutomatico = (isset($renovar) && $renovar && ($renovacaoTipo ?? '') !== 'nenhum' && !$isRenovarAbate);
                                                    @endphp
                                                    <div class="mb-2">
                                                        <input type="radio" name="tipo_juros_renovacao" id="renovacao_nenhum" value="nenhum" 
                                                               {{ $isRenovarNenhum || (!isset($renovar) || !$renovar) ? 'checked' : '' }} class="form-check-input">
                                                        <label for="renovacao_nenhum" class="form-check-label ms-2">
                                                            <strong>Renovar sem juros de atraso</strong>
                                                        </label>
                                                        <div class="ms-4 text-muted" id="resultado_renovacao_nenhum">
                                                            Juros originais: R$ <span id="valor_juros_originais_renovacao_nenhum">{{ number_format($jurosOriginais, 2, ',', '.') }}</span><br>
                                                            Juros de atraso: R$ 0,00<br>
                                                            <strong>Valor a pagar: R$ <span id="total_renovacao_nenhum">{{ number_format($jurosOriginais, 2, ',', '.') }}</span></strong>
                                                        </div>
                                                    </div>
                                                    
                                                    @if($taxaJurosRenovacao > 0)
                                                    <div class="mb-2">
                                                        <input type="radio" name="tipo_juros_renovacao" id="renovacao_automatico" value="automatico" 
                                                               {{ $isRenovarAutomatico ? 'checked' : '' }} class="form-check-input">
                                                        <label for="renovacao_automatico" class="form-check-label ms-2">
                                                            <strong>Renovar com juros de atraso (automático)</strong> 
                                                            ({{ number_format($taxaJurosRenovacao, 2, ',', '.') }}% {{ $tipoCalculoRenovacao === 'por_dia' ? 'ao dia' : 'ao mês' }})
                                                        </label>
                                                        <div class="ms-4 text-muted" id="resultado_renovacao_automatico" style="display: {{ (isset($renovar) && $renovar) ? 'block' : 'none' }};">
                                                            Juros originais: R$ <span id="valor_juros_originais_renovacao">{{ number_format($jurosOriginais, 2, ',', '.') }}</span><br>
                                                            Juros de atraso: R$ <span id="valor_juros_atraso_renovacao">{{ number_format($jurosAtraso, 2, ',', '.') }}</span><br>
                                                            <strong>Valor a pagar: R$ <span id="total_renovacao_automatico">{{ number_format($valorJurosRenovacaoAutomatico, 2, ',', '.') }}</span></strong>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    
                                                    <div class="mb-2">
                                                        <input type="radio" name="tipo_juros_renovacao" id="renovacao_manual" value="manual" class="form-check-input">
                                                        <label for="renovacao_manual" class="form-check-label ms-2">
                                                            <strong>Renovar com juros de atraso (manual)</strong> - Informar taxa % no momento
                                                        </label>
                                                    <div class="ms-4 mt-2" id="campo_taxa_renovacao_manual" style="display: none;">
                                                        <div class="input-group" style="max-width: 300px;">
                                                            <input type="number" name="taxa_juros_renovacao_manual" id="taxa_juros_renovacao_manual" 
                                                                   class="form-control" step="0.01" min="0" max="100" 
                                                                   placeholder="Ex: 2.5">
                                                            <span class="input-group-text">% {{ $tipoCalculoRenovacao === 'por_dia' ? 'ao dia' : 'ao mês' }}</span>
                                                        </div>
                                                        <div class="text-muted mt-2" id="resultado_renovacao_manual">
                                                            Juros originais: R$ <span id="valor_juros_originais_renovacao_manual">{{ number_format($jurosOriginais, 2, ',', '.') }}</span><br>
                                                            Juros de atraso: R$ <span id="valor_juros_atraso_renovacao_manual">0,00</span><br>
                                                            <strong>Valor a pagar: R$ <span id="total_renovacao_manual">0,00</span></strong>
                                                        </div>
                                                    </div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <input type="radio" name="tipo_juros_renovacao" id="renovacao_fixo" value="fixo" class="form-check-input">
                                                        <label for="renovacao_fixo" class="form-check-label ms-2">
                                                            <strong>Renovar com juros de atraso (valor fixo)</strong> - Informar valor em R$
                                                        </label>
                                                        <div class="ms-4 mt-2" id="campo_valor_renovacao_fixo" style="display: none;">
                                                            <div class="input-group" style="max-width: 300px;">
                                                                <span class="input-group-text">R$</span>
                                                                <input type="text" name="valor_juros_renovacao_fixo" id="valor_juros_renovacao_fixo" 
                                                                       class="form-control" inputmode="decimal" placeholder="Ex: 150,00">
                                                            </div>
                                                            <div class="text-muted mt-2" id="resultado_renovacao_fixo">
                                                                Juros originais: R$ <span id="valor_juros_originais_renovacao_fixo">{{ number_format($jurosOriginais, 2, ',', '.') }}</span><br>
                                                                Juros de atraso: R$ <span id="valor_juros_atraso_renovacao_fixo">0,00</span><br>
                                                                <strong>Valor a pagar: R$ <span id="total_renovacao_fixo">{{ number_format($jurosOriginais, 2, ',', '.') }}</span></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <input type="radio" name="tipo_juros_renovacao" id="renovacao_com_abate" value="com_abate" {{ ($isRenovarAbate ?? false) ? 'checked' : '' }} class="form-check-input">
                                                        <label for="renovacao_com_abate" class="form-check-label ms-2">
                                                            <strong>Renovar com abate no saldo</strong> - Informar valor total a pagar (o valor abate o saldo; novo empréstimo = saldo restante)
                                                        </label>
                                                        <div class="ms-4 mt-2" id="campo_renovacao_abate" style="display: none;"
                                                             data-valor-min="{{ number_format($valorPrincipal, 2, '.', '') }}"
                                                             data-valor-max="{{ number_format($parcela->valor, 2, '.', '') }}">
                                                            <div class="input-group" style="max-width: 300px;">
                                                                <span class="input-group-text">R$</span>
                                                                <input type="text" name="valor_renovacao_abate" id="valor_renovacao_abate" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Ex: 500,00">
                                                            </div>
                                                            <small class="text-muted">Valor abaixo do principal (R$ {{ number_format($valorPrincipal, 2, ',', '.') }}) exige aprovação. Máx. parcela: R$ {{ number_format($parcela->valor, 2, ',', '.') }}</small>
                                                            <div class="text-muted mt-2" id="resultado_renovacao_abate">
                                                                Valor a pagar: R$ <span id="total_renovacao_abate">{{ number_format($parcela->valor, 2, ',', '.') }}</span><br>
                                                                <strong>Novo empréstimo (saldo restante): R$ <span id="saldo_restante_renovacao_abate">0,00</span></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="alert alert-info mt-2 mb-0">
                                                        <small>
                                                            <i class="bx bx-info-circle"></i> 
                                                            Ao selecionar qualquer opção de renovação, o pagamento será registrado e um novo empréstimo será criado (com abate: novo valor = saldo restante; demais opções: mesmo principal).
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            @if($estaAtrasada)
                                                @if($temTaxaOperacao)
                                                <div class="mb-2">
                                                    <input type="radio" name="tipo_juros" id="tipo_juros_automatico" value="automatico" class="form-check-input">
                                                    <label for="tipo_juros_automatico" class="form-check-label ms-2">
                                                        <strong>Juros de atraso (automático)</strong> ({{ number_format($operacao->taxa_juros_atraso, 2, ',', '.') }}% {{ $operacao->tipo_calculo_juros === 'por_dia' ? 'ao dia' : 'ao mês' }})
                                                    </label>
                                                    <div class="ms-4 text-muted" id="resultado_automatico" style="display: none;">
                                                        Valor original: R$ <span id="valor_original_automatico">{{ number_format($parcela->valor, 2, ',', '.') }}</span><br>
                                                        Juros de atraso: R$ <span id="valor_juros_automatico">0,00</span><br>
                                                        <strong>Valor a pagar: R$ <span id="total_automatico">0,00</span></strong>
                                                    </div>
                                                </div>
                                                @endif

                                                <div class="mb-2">
                                                    <input type="radio" name="tipo_juros" id="tipo_juros_manual" value="manual" class="form-check-input">
                                                    <label for="tipo_juros_manual" class="form-check-label ms-2">
                                                        <strong>Juros de atraso (manual)</strong> - Informar taxa % no momento
                                                    </label>
                                                    <div class="ms-4 mt-2" id="campo_taxa_manual" style="display: none;">
                                                        <div class="input-group" style="max-width: 300px;">
                                                            <input type="number" name="taxa_juros_manual" id="taxa_juros_manual" 
                                                                   class="form-control" step="0.01" min="0" max="100" 
                                                                   placeholder="Ex: 2.5">
                                                            <span class="input-group-text">% ao dia</span>
                                                        </div>
                                                        <div class="text-muted mt-2" id="resultado_manual">
                                                            Valor original: R$ <span id="valor_original_manual">{{ number_format($parcela->valor, 2, ',', '.') }}</span><br>
                                                            Juros de atraso: R$ <span id="valor_juros_manual">0,00</span><br>
                                                            <strong>Valor a pagar: R$ <span id="total_manual">0,00</span></strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <input type="radio" name="tipo_juros" id="tipo_juros_fixo" value="fixo" class="form-check-input">
                                                    <label for="tipo_juros_fixo" class="form-check-label ms-2">
                                                        <strong>Juros de atraso (valor fixo)</strong> - Informar valor em R$
                                                    </label>
                                                    <div class="ms-4 mt-2" id="campo_valor_fixo" style="display: none;">
                                                        <div class="input-group" style="max-width: 300px;">
                                                            <span class="input-group-text">R$</span>
                                                            <input type="text" name="valor_juros_fixo" id="valor_juros_fixo" class="form-control" inputmode="decimal" data-mask-money="brl" placeholder="Ex: 25,00">
                                                        </div>
                                                        <div class="text-muted mt-2" id="resultado_fixo">
                                                            Valor original: R$ <span id="valor_original_fixo">{{ number_format($parcela->valor ?? 0, 2, ',', '.') }}</span><br>
                                                            Juros de atraso: R$ <span id="valor_juros_fixo_exibir">0,00</span><br>
                                                            <strong>Total: R$ <span id="total_fixo">0,00</span></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                @php
                                                    // Verificar se pode executar garantia (empréstimo tipo empenho com garantia ativa)
                                                    $podeExecutarGarantiaPagamento = $emprestimo && 
                                                        $emprestimo->isEmpenho() && 
                                                        $emprestimo->isAtivo() &&
                                                        auth()->user()->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) &&
                                                        $emprestimo->garantias->where('status', 'ativa')->count() > 0;
                                                    
                                                    if ($podeExecutarGarantiaPagamento && !$emprestimo->relationLoaded('garantias')) {
                                                        $emprestimo->load('garantias');
                                                    }
                                                @endphp
                                                
                                                @if($podeExecutarGarantiaPagamento)
                                                <div class="mb-2 border-top pt-2 mt-2">
                                                    <input type="radio" name="tipo_juros" id="tipo_juros_executar_garantia" value="executar_garantia" 
                                                           {{ (isset($executarGarantia) && $executarGarantia) ? 'checked' : '' }} class="form-check-input">
                                                    <label for="tipo_juros_executar_garantia" class="form-check-label ms-2">
                                                        <strong>Executar Garantia</strong> - Finalizar empréstimo sem cobrança
                                                    </label>
                                                    <div class="ms-4 mt-2" id="campo_executar_garantia" style="display: none;">
                                                        <div class="alert alert-danger mb-2">
                                                            <i class="bx bx-error-circle"></i>
                                                            <strong>Atenção:</strong> Esta opção irá:
                                                            <ul class="mb-0 mt-2">
                                                                <li>Executar a garantia (marcar como executada)</li>
                                                                <li>Finalizar o empréstimo automaticamente</li>
                                                                <li>Não cobrar juros de atraso nem valor da parcela</li>
                                                                <li>Não poderá ser desfeita</li>
                                                            </ul>
                                                        </div>
                                                        
                                                        <div class="mb-2">
                                                            <strong>Garantias disponíveis:</strong>
                                                            <ul class="mb-2">
                                                                @foreach($emprestimo->garantias->where('status', 'ativa') as $garantia)
                                                                    <li>{{ $garantia->categoria_nome }} - {{ $garantia->descricao }}
                                                                        @if($garantia->valor_avaliado)
                                                                            ({{ $garantia->valor_formatado }})
                                                                        @endif
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label">Observações/Motivo <span class="text-danger">*</span></label>
                                                            <textarea name="observacoes_executar_garantia" id="observacoes_executar_garantia" 
                                                                      class="form-control" rows="3" 
                                                                      placeholder="Descreva o motivo da execução da garantia (mínimo 10 caracteres)"></textarea>
                                                            <small class="text-muted">Este campo é obrigatório e será registrado na auditoria.</small>
                                                        </div>
                                                        
                                                        <div class="text-muted" id="resultado_executar_garantia">
                                                            <strong>Valor a pagar: R$ 0,00</strong><br>
                                                            <small>(Não há cobrança, apenas execução da garantia)</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3 campos-pagamento">
                                <label class="form-label">Valor do Pagamento <span class="text-danger">*</span></label>
                                <input type="text" name="valor" id="valor_pagamento" class="form-control" inputmode="decimal" data-mask-money="brl"
                                       placeholder="0,00"
                                       value="{{ $parcela ? number_format($parcela->valor, 2, ',', '.') : '' }}"
                                       @if(empty($diariaMultiplasParcelas)) required readonly @else required @endif
                                       @if(!empty($diariaMaxAntecipacao)) data-diaria-max="{{ number_format($diariaMaxAntecipacao, 2, '.', '') }}" @endif>
                                @if(!empty($diariaMultiplasParcelas) && isset($diariaValorDevido, $diariaMaxAntecipacao))
                                    <small class="text-muted d-block mt-1">
                                        <strong>Diária (várias parcelas):</strong> valor devido (referência) até R$ {{ number_format($diariaValorDevido, 2, ',', '.') }};
                                        máximo permitido (sem sobra) até R$ {{ number_format($diariaMaxAntecipacao, 2, ',', '.') }} — conforme juros de atraso selecionados abaixo, o sistema valida no envio.
                                        Abaixo do devido, o pagamento parcial vai para aprovação em Liberações e a parcela permanece em atraso até aprovar.
                                    </small>
                                @else
                                    <small class="text-muted">Este valor é calculado automaticamente conforme a opção de juros de atraso selecionada.</small>
                                @endif
                            </div>

                            <div class="mb-3 campos-pagamento">
                                <label class="form-label">Método de Pagamento <span class="text-danger">*</span></label>
                                <select name="metodo" class="form-select" required>
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="pix">PIX</option>
                                    <option value="transferencia">Transferência</option>
                                    <option value="outro">Outro</option>
                                    @if($parcela)
                                        <option value="produto_objeto">Produto/Objeto</option>
                                    @endif
                                </select>
                                @if($parcela)
                                    <small class="text-muted">Produto/Objeto: pagamento em espécie; não gera movimentação de caixa e precisa ser aceito por gestor ou administrador nas Liberações. Só está disponível se a operação permitir.</small>
                                @endif
                            </div>

                            @if($parcela)
                            <div id="campos-produto-objeto" class="border rounded p-3 mb-3 bg-light" style="display: none;">
                                <h6 class="mb-3">Itens (produto/objeto)</h6>
                                <p class="text-muted small mb-3">Adicione um ou mais itens recebidos (ex.: 1 TV + 1 celular).</p>
                                <div id="container-itens-produto-objeto"></div>
                                <button type="button" id="btn-adicionar-item-produto" class="btn btn-outline-secondary btn-sm mt-2">
                                    <i class="bx bx-plus"></i> Adicionar outro item
                                </button>
                            </div>
                            <script>
                            (function() {
                                var sel = document.querySelector('select[name="metodo"]');
                                var bloco = document.getElementById('campos-produto-objeto');
                                var container = document.getElementById('container-itens-produto-objeto');
                                var btnAdd = document.getElementById('btn-adicionar-item-produto');
                                if (!sel || !bloco || !container) return;

                                var contador = 0;

                                function novoId() { return contador++; }

                                function removerItem(btn) {
                                    var card = btn.closest('.item-produto-objeto');
                                    if (container.querySelectorAll('.item-produto-objeto').length <= 1) return;
                                    card.remove();
                                }

                                function criarItem(index) {
                                    var card = document.createElement('div');
                                    card.className = 'item-produto-objeto border rounded p-3 mb-3 bg-white';
                                    card.innerHTML =
                                        '<div class="d-flex justify-content-between align-items-center mb-2">' +
                                        '<strong class="text-secondary">Item ' + (index + 1) + '</strong>' +
                                        '<button type="button" class="btn btn-sm btn-outline-danger btn-remover-item" title="Remover item"><i class="bx bx-trash"></i></button>' +
                                        '</div>' +
                                        '<div class="row g-2">' +
                                        '<div class="col-md-6"><label class="form-label">Nome <span class="text-danger">*</span></label>' +
                                        '<input type="text" name="itens[' + index + '][nome]" class="form-control form-control-sm item-nome" maxlength="255" placeholder="Ex: TV 32\", Celular"></div>' +
                                        '<div class="col-md-3"><label class="form-label">Quantidade</label>' +
                                        '<input type="number" name="itens[' + index + '][quantidade]" class="form-control form-control-sm" value="1" min="1"></div>' +
                                        '<div class="col-md-3"><label class="form-label">Valor est. (R$) <span class="text-danger">*</span></label>' +
                                        '<input type="text" name="itens[' + index + '][valor_estimado]" class="form-control form-control-sm item-valor" inputmode="decimal" data-mask-money="brl" placeholder="0,00"></div>' +
                                        '</div>' +
                                        '<div class="mt-2"><label class="form-label">Descrição</label>' +
                                        '<textarea name="itens[' + index + '][descricao]" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Estado, marca..."></textarea></div>' +
                                        '<div class="mt-2"><label class="form-label">Imagens <span class="text-danger">*</span></label>' +
                                        '<input type="file" name="itens[' + index + '][imagens][]" class="form-control form-control-sm item-imagens" accept=".jpg,.jpeg,.png,.gif,.webp" multiple></div>';
                                    var removeBtn = card.querySelector('.btn-remover-item');
                                    removeBtn.addEventListener('click', function() { removerItem(removeBtn); });
                                    return card;
                                }

                                function adicionarItem() {
                                    var idx = novoId();
                                    container.appendChild(criarItem(idx));
                                }

                                if (btnAdd) btnAdd.addEventListener('click', adicionarItem);

                                function mostrar() {
                                    var isProduto = sel.value === 'produto_objeto';
                                    bloco.style.display = isProduto ? 'block' : 'none';
                                    if (isProduto && container.children.length === 0) {
                                        contador = 0;
                                        adicionarItem();
                                    }
                                    container.querySelectorAll('.item-nome, .item-valor, .item-imagens').forEach(function(el) {
                                        el.required = isProduto;
                                    });
                                }
                                sel.addEventListener('change', function() {
                                    mostrar();
                                });
                                mostrar();
                            })();
                            </script>
                            @endif

                            <div class="mb-3 campos-pagamento">
                                <label class="form-label">Data do Pagamento <span class="text-danger">*</span></label>
                                <input type="date" name="data_pagamento" class="form-control" 
                                       value="{{ date('Y-m-d') }}" required>
                            </div>

                            <div class="mb-3 campos-pagamento">
                                <label class="form-label">Comprovante</label>
                                <input type="file" name="comprovante" class="form-control" 
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Formatos aceitos: PDF, JPG, PNG (máx. 2MB)</small>
                            </div>

                            <div class="mb-3 campos-pagamento">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3"></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                @if($parcela)
                                    <a href="{{ route('emprestimos.show', $parcela->emprestimo_id) }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Cancelar
                                    </a>
                                @else
                                    <a href="{{ route('cobrancas.index') }}" class="btn btn-secondary">
                                        <i class="bx bx-x"></i> Cancelar
                                    </a>
                                @endif
                                <button type="submit" class="btn btn-primary" id="btnSubmit">
                                    <i class="bx bx-check" id="btnSubmitIcon"></i> 
                                    <span id="btnSubmitText">Registrar Pagamento</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            // Prevenir duplo submit e adicionar confirmação
            const formPagamento = document.getElementById('formRegistrarPagamento');
            formPagamento.addEventListener('submit', function(e) {
                e.preventDefault();
                const btnSubmit = document.getElementById('btnSubmit');
                
                if (btnSubmit.disabled) {
                    return false;
                }

                const valorEl = document.getElementById('valor_pagamento');
                let valor = 0;
                if (valorEl && valorEl.value) {
                    var s = String(valorEl.value).replace(/\s/g, '').replace(/R\$\s?/g, '');
                    if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
                    valor = parseFloat(s) || 0;
                }
                const metodo = document.querySelector('select[name="metodo"]').value;
                const tipoJuros = document.querySelector('input[name="tipo_juros"]:checked')?.value || 'nenhum';
                const valorOriginal = {{ $parcela ? $parcela->valor : 0 }};
                const temJuros = tipoJuros !== 'nenhum' && tipoJuros !== 'renovacao' && tipoJuros !== 'executar_garantia' && valor > valorOriginal;
                const isRenovacao = tipoJuros === 'renovacao';
                const isExecutarGarantia = tipoJuros === 'executar_garantia';
                const isValorInferior = tipoJuros === 'valor_inferior';
                
                let mensagemJuros = '';
                if (temJuros) {
                    const valorJuros = valor - valorOriginal;
                    mensagemJuros = `<br><strong class="text-warning">Juros de atraso:</strong> R$ ${valorJuros.toFixed(2).replace('.', ',')}`;
                }
                
                let mensagemRenovacao = '';
                if (isRenovacao) {
                    const tipoRenovacao = document.querySelector('input[name="tipo_juros_renovacao"]:checked')?.value || 'nenhum';
                    if (tipoRenovacao === 'com_abate') {
                        mensagemRenovacao = `<br><br><div class="alert alert-info mb-0">
                            <i class="bx bx-info-circle"></i> 
                            <strong>Renovação com abate:</strong> O valor pago será registrado e abaterá o saldo devedor. Um novo empréstimo será criado com o saldo restante. Valor abaixo do principal exige aprovação do gestor/administrador.
                        </div>`;
                    } else {
                        mensagemRenovacao = `<br><br><div class="alert alert-warning mb-0">
                            <i class="bx bx-info-circle"></i> 
                            <strong>Atenção:</strong> Esta ação irá registrar o pagamento dos juros e renovar o empréstimo automaticamente (novo empréstimo com o mesmo principal).
                        </div>`;
                    }
                }
                
                let mensagemExecutarGarantia = '';
                if (isExecutarGarantia) {
                    const observacoes = document.getElementById('observacoes_executar_garantia')?.value || '';
                    if (!observacoes || observacoes.length < 10) {
                        Swal.fire({
                            title: 'Observações Obrigatórias',
                            text: 'Por favor, preencha o campo de observações/motivo com pelo menos 10 caracteres.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                    
                    // Listar garantias disponíveis
                    let listaGarantias = '';
                    @if(isset($emprestimo) && $emprestimo && $emprestimo->garantias && $emprestimo->garantias->where('status', 'ativa')->count() > 0)
                    const garantiasDisponiveis = [
                        @foreach($emprestimo->garantias->where('status', 'ativa') as $garantia)
                        {
                            categoria: '{{ addslashes($garantia->categoria_nome) }}',
                            descricao: '{{ addslashes($garantia->descricao) }}',
                            valor: @if($garantia->valor_avaliado)'{{ addslashes($garantia->valor_formatado) }}'@else null @endif
                        }@if(!$loop->last),@endif
                        @endforeach
                    ];
                    
                    if (garantiasDisponiveis.length > 0) {
                        listaGarantias = '<br><strong>Garantias que serão executadas:</strong><ul style="text-align: left; margin-top: 10px; margin-bottom: 10px;">';
                        garantiasDisponiveis.forEach(function(garantia) {
                            listaGarantias += '<li>' + garantia.categoria + ' - ' + garantia.descricao;
                            if (garantia.valor) {
                                listaGarantias += ' (' + garantia.valor + ')';
                            }
                            listaGarantias += '</li>';
                        });
                        listaGarantias += '</ul>';
                    }
                    @endif
                    
                    mensagemExecutarGarantia = `<br><br><div class="alert alert-danger mb-0">
                        <i class="bx bx-error-circle"></i> 
                        <strong>Atenção:</strong> Esta ação irá executar a garantia e finalizar o empréstimo automaticamente. Não poderá ser desfeita.
                    </div>${listaGarantias}`;
                }

                let mensagemValorInferior = '';
                if (isValorInferior) {
                    mensagemValorInferior = `<br><br><div class="alert alert-info mb-0">
                        <i class="bx bx-info-circle"></i> 
                        <strong>Valor inferior (juros reduzido):</strong> Esta solicitação será enviada para aprovação do gestor ou administrador. A parcela só será quitada após a aprovação.
                    </div>`;
                }

                // Montar HTML do alerta baseado no tipo
                let htmlAlerta = '';
                if (isExecutarGarantia) {
                    // Para execução de garantia, não mostrar valor nem método
                    htmlAlerta = `<strong>Parcela:</strong> #{{ $parcela ? $parcela->numero : 'N/A' }} de {{ $parcela ? $parcela->emprestimo->numero_parcelas : 'N/A' }}${mensagemExecutarGarantia}`;
                } else if (isRenovacao) {
                    // Para renovação, mostrar valor e parcela
                    htmlAlerta = `<strong>Valor Total:</strong> R$ ${valor.toFixed(2).replace('.', ',')}${mensagemJuros}<br>
                           <strong>Parcela:</strong> #{{ $parcela ? $parcela->numero : 'N/A' }} de {{ $parcela ? $parcela->emprestimo->numero_parcelas : 'N/A' }}${mensagemRenovacao}`;
                } else {
                    // Para pagamento normal, mostrar tudo
                    htmlAlerta = `<strong>Valor Total:</strong> R$ ${valor.toFixed(2).replace('.', ',')}${mensagemJuros}<br>
                           <strong>Método:</strong> ${metodo.charAt(0).toUpperCase() + metodo.slice(1)}<br>
                           <strong>Parcela:</strong> #{{ $parcela ? $parcela->numero : 'N/A' }} de {{ $parcela ? $parcela->emprestimo->numero_parcelas : 'N/A' }}${mensagemValorInferior}`;
                }

                Swal.fire({
                    title: isExecutarGarantia ? 'Executar Garantia?' : (isRenovacao ? 'Renovar Empréstimo?' : (isValorInferior ? 'Enviar solicitação (valor inferior)?' : 'Registrar Pagamento?')),
                    html: htmlAlerta,
                    icon: isExecutarGarantia ? 'warning' : (isRenovacao ? 'question' : 'question'),
                    showCancelButton: true,
                    confirmButtonColor: isExecutarGarantia ? '#dc3545' : (isRenovacao ? '#ffc107' : (isValorInferior ? '#0dcaf0' : '#038edc')),
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: isExecutarGarantia ? 'Sim, executar garantia!' : (isRenovacao ? 'Sim, renovar!' : (isValorInferior ? 'Sim, enviar para aprovação!' : 'Sim, registrar!')),
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar loading ao submeter
                        const loadingText = isExecutarGarantia ? 'Executando garantia...' : (isValorInferior ? 'Enviando solicitação...' : 'Registrando pagamento...');
                        FormLoading.show(formPagamento, loadingText);
                        formPagamento.submit();
                    }
                });
            });

            @if($parcela && ($estaAtrasada || (isset($renovar) && $renovar)))
            // Cálculo de juros em tempo real
            (function() {
                const valorOriginal = {{ $parcela->valor }};
                const diasAtraso = {{ $diasAtraso ?? 0 }};
                const taxaOperacao = {{ $operacao->taxa_juros_atraso ?? 0 }};
                const tipoCalculo = '{{ $operacao->tipo_calculo_juros ?? 'por_dia' }}';
                const campoValor = document.getElementById('valor_pagamento');
                
                // Valores para renovação (baseado no principal, não na parcela)
                @if($podeRenovar && !$jurosJaPagos)
                const valorPrincipal = {{ number_format($valorPrincipal, 2, '.', '') }};
                const taxaJurosRenovacao = {{ $taxaJurosRenovacao }};
                const tipoCalculoRenovacao = '{{ $tipoCalculoRenovacao }}';
                const jurosOriginais = {{ number_format($jurosOriginais, 2, '.', '') }};
                @else
                const valorPrincipal = 0;
                const taxaJurosRenovacao = 0;
                const tipoCalculoRenovacao = 'por_dia';
                const jurosOriginais = 0;
                @endif
                @if($mostrarDetalheJuros ?? false)
                const valorPrincipalParcela = {{ number_format($valorPrincipalParcela, 2, '.', '') }};
                @else
                const valorPrincipalParcela = valorOriginal;
                @endif

                function formatarMoeda(valor) {
                    return valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }
                function parseBrDecimal(input) {
                    if (!input) return 0;
                    var s = String(input).replace(/\s/g, '').replace(/R\$\s?/g, '');
                    if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
                    return parseFloat(s) || 0;
                }

                function calcularJurosAutomatico() {
                    let juros = 0;
                    if (tipoCalculo === 'por_dia') {
                        juros = valorOriginal * (taxaOperacao / 100) * diasAtraso;
                    } else {
                        juros = valorOriginal * (taxaOperacao / 100) * (diasAtraso / 30);
                    }
                    return Math.round(juros * 100) / 100;
                }
                
                function calcularJurosRenovacaoAutomatico() {
                    // Juros originais (sempre incluídos)
                    let jurosOrig = jurosOriginais || 0;
                    
                    // Juros por atraso (se houver taxa e dias de atraso)
                    let jurosAtraso = 0;
                    if (taxaJurosRenovacao > 0 && diasAtraso > 0) {
                        if (tipoCalculoRenovacao === 'por_dia') {
                            jurosAtraso = valorPrincipal * (taxaJurosRenovacao / 100) * diasAtraso;
                        } else {
                            jurosAtraso = valorPrincipal * (taxaJurosRenovacao / 100) * (diasAtraso / 30);
                        }
                        jurosAtraso = Math.round(jurosAtraso * 100) / 100;
                    }
                    
                    // Total = Juros originais + Juros por atraso
                    return Math.round((jurosOrig + jurosAtraso) * 100) / 100;
                }

                function atualizarValorPagamento() {
                    const tipoJuros = document.querySelector('input[name="tipo_juros"]:checked')?.value || 'nenhum';
                    let valorJuros = 0;
                    let total = valorOriginal;

                    switch(tipoJuros) {
                        case 'nenhum':
                            total = valorOriginal;
                            break;
                        case 'valor_inferior':
                            const valorInferiorInput = document.getElementById('valor_inferior_input');
                            let valorInferior = valorOriginal;
                            if (valorInferiorInput) {
                                if (typeof jQuery !== 'undefined' && jQuery(valorInferiorInput).data('maskMoney')) {
                                    const unmasked = jQuery(valorInferiorInput).maskMoney('unmasked');
                                    valorInferior = (unmasked && unmasked[0] != null && !isNaN(unmasked[0])) ? unmasked[0] : parseBrDecimal(valorInferiorInput.value);
                                } else {
                                    valorInferior = parseBrDecimal(valorInferiorInput.value) || valorOriginal;
                                }
                            }
                            valorInferior = Math.round(valorInferior * 100) / 100;
                            if (valorInferior < valorPrincipalParcela) valorInferior = valorPrincipalParcela;
                            if (valorInferior > valorOriginal) valorInferior = valorOriginal;
                            total = valorInferior;
                            const totalValorInferiorEl = document.getElementById('total_valor_inferior');
                            if (totalValorInferiorEl) totalValorInferiorEl.textContent = formatarMoeda(total);
                            break;
                        case 'renovacao':
                            @if($podeRenovar && !$jurosJaPagos)
                            // Verificar qual sub-opção de renovação está selecionada
                            const tipoRenovacao = document.querySelector('input[name="tipo_juros_renovacao"]:checked')?.value || 'nenhum';
                            
                            switch(tipoRenovacao) {
                                case 'nenhum':
                                    total = jurosOriginais || 0;
                                    const totalNenhumEl = document.getElementById('total_renovacao_nenhum');
                                    if (totalNenhumEl) totalNenhumEl.textContent = formatarMoeda(total);
                                    document.getElementById('resultado_renovacao_nenhum').style.display = 'block';
                                    break;
                                case 'automatico':
                                    // Calcular juros originais e por atraso separadamente
                                    const jurosOrig = jurosOriginais || 0;
                                    let jurosAtrasoCalc = 0;
                                    if (taxaJurosRenovacao > 0 && diasAtraso > 0) {
                                        if (tipoCalculoRenovacao === 'por_dia') {
                                            jurosAtrasoCalc = valorPrincipal * (taxaJurosRenovacao / 100) * diasAtraso;
                                        } else {
                                            jurosAtrasoCalc = valorPrincipal * (taxaJurosRenovacao / 100) * (diasAtraso / 30);
                                        }
                                        jurosAtrasoCalc = Math.round(jurosAtrasoCalc * 100) / 100;
                                    }
                                    valorJuros = Math.round((jurosOrig + jurosAtrasoCalc) * 100) / 100;
                                    total = valorJuros;
                                    
                                    // Atualizar exibição
                                    const valorJurosOriginaisEl = document.getElementById('valor_juros_originais_renovacao');
                                    const valorJurosAtrasoEl = document.getElementById('valor_juros_atraso_renovacao');
                                    if (valorJurosOriginaisEl) valorJurosOriginaisEl.textContent = formatarMoeda(jurosOrig);
                                    if (valorJurosAtrasoEl) valorJurosAtrasoEl.textContent = formatarMoeda(jurosAtrasoCalc);
                                    document.getElementById('total_renovacao_automatico').textContent = formatarMoeda(total);
                                    document.getElementById('resultado_renovacao_automatico').style.display = 'block';
                                    break;
                                case 'manual':
                                    // Juros originais (sempre incluídos)
                                    const jurosOrigManual = jurosOriginais || 0;
                                    
                                    // Juros por atraso (taxa informada manualmente × dias)
                                    const taxaRenovacaoManual = parseFloat(document.getElementById('taxa_juros_renovacao_manual').value) || 0;
                                    let jurosAtrasoManual = 0;
                                    if (taxaRenovacaoManual > 0 && valorPrincipal > 0 && diasAtraso > 0) {
                                        if (tipoCalculoRenovacao === 'por_dia') {
                                            jurosAtrasoManual = valorPrincipal * (taxaRenovacaoManual / 100) * diasAtraso;
                                        } else {
                                            jurosAtrasoManual = valorPrincipal * (taxaRenovacaoManual / 100) * (diasAtraso / 30);
                                        }
                                        jurosAtrasoManual = Math.round(jurosAtrasoManual * 100) / 100;
                                    }
                                    
                                    // Total = Juros originais + Juros por atraso
                                    valorJuros = Math.round((jurosOrigManual + jurosAtrasoManual) * 100) / 100;
                                    total = valorJuros;
                                    
                                    // Atualizar exibição detalhada
                                    const valorJurosOriginaisManualEl = document.getElementById('valor_juros_originais_renovacao_manual');
                                    const valorJurosAtrasoManualEl = document.getElementById('valor_juros_atraso_renovacao_manual');
                                    if (valorJurosOriginaisManualEl) valorJurosOriginaisManualEl.textContent = formatarMoeda(jurosOrigManual);
                                    if (valorJurosAtrasoManualEl) valorJurosAtrasoManualEl.textContent = formatarMoeda(jurosAtrasoManual);
                                    document.getElementById('total_renovacao_manual').textContent = formatarMoeda(total);
                                    break;
                                case 'fixo':
                                    const jurosOrigFixo = jurosOriginais || 0;
                                    const valorRenovacaoFixo = parseFloat(document.getElementById('valor_juros_renovacao_fixo').value) || 0;
                                    const jurosAtrasoFixo = Math.round(valorRenovacaoFixo * 100) / 100;
                                    valorJuros = Math.round((jurosOrigFixo + jurosAtrasoFixo) * 100) / 100;
                                    total = valorJuros;
                                    const valorJurosOrigFixoEl = document.getElementById('valor_juros_originais_renovacao_fixo');
                                    const valorJurosAtrasoFixoEl = document.getElementById('valor_juros_atraso_renovacao_fixo');
                                    if (valorJurosOrigFixoEl) valorJurosOrigFixoEl.textContent = formatarMoeda(jurosOrigFixo);
                                    if (valorJurosAtrasoFixoEl) valorJurosAtrasoFixoEl.textContent = formatarMoeda(jurosAtrasoFixo);
                                    document.getElementById('total_renovacao_fixo').textContent = formatarMoeda(total);
                                    break;
                                case 'com_abate':
                                    const campoAbate = document.getElementById('campo_renovacao_abate');
                                    const inputAbate = document.getElementById('valor_renovacao_abate');
                                    let valorAbate = 0;
                                    if (campoAbate && inputAbate) {
                                        const maxAbate = parseFloat(campoAbate.getAttribute('data-valor-max')) || valorOriginal;
                                        if (typeof jQuery !== 'undefined' && jQuery(inputAbate).data('maskMoney')) {
                                            const u = jQuery(inputAbate).maskMoney('unmasked');
                                            valorAbate = (u && u[0] != null && !isNaN(u[0])) ? u[0] : parseBrDecimal(inputAbate.value);
                                        } else {
                                            valorAbate = parseBrDecimal(inputAbate.value) || 0;
                                        }
                                        valorAbate = Math.round(valorAbate * 100) / 100;
                                        if (valorAbate > maxAbate) valorAbate = maxAbate;
                                        if (valorAbate < 0) valorAbate = 0;
                                        total = valorAbate;
                                        const totalAbateEl = document.getElementById('total_renovacao_abate');
                                        const saldoRestanteEl = document.getElementById('saldo_restante_renovacao_abate');
                                        if (totalAbateEl) totalAbateEl.textContent = formatarMoeda(total);
                                        if (saldoRestanteEl) saldoRestanteEl.textContent = formatarMoeda(Math.round((valorOriginal - total) * 100) / 100);
                                    }
                                    break;
                            }
                            
                            // Ocultar resultados de outras sub-opções
                            if (tipoRenovacao !== 'nenhum') {
                                document.getElementById('resultado_renovacao_nenhum').style.display = 'none';
                            }
                            if (tipoRenovacao !== 'automatico') {
                                const resultadoAuto = document.getElementById('resultado_renovacao_automatico');
                                if (resultadoAuto) resultadoAuto.style.display = 'none';
                            }
                            @endif
                            break;
                        case 'automatico':
                            valorJuros = calcularJurosAutomatico();
                            total = valorOriginal + valorJuros;
                            
                            // Atualizar exibição detalhada
                            const valorOriginalAutoEl = document.getElementById('valor_original_automatico');
                            if (valorOriginalAutoEl) valorOriginalAutoEl.textContent = formatarMoeda(valorOriginal);
                            document.getElementById('valor_juros_automatico').textContent = formatarMoeda(valorJuros);
                            document.getElementById('total_automatico').textContent = formatarMoeda(total);
                            break;
                        case 'manual':
                            const taxaManual = parseFloat(document.getElementById('taxa_juros_manual').value) || 0;
                            if (taxaManual > 0) {
                                valorJuros = valorOriginal * (taxaManual / 100) * diasAtraso;
                                valorJuros = Math.round(valorJuros * 100) / 100;
                                total = valorOriginal + valorJuros;
                                
                                // Atualizar exibição detalhada
                                const valorOriginalManualEl = document.getElementById('valor_original_manual');
                                if (valorOriginalManualEl) valorOriginalManualEl.textContent = formatarMoeda(valorOriginal);
                                document.getElementById('valor_juros_manual').textContent = formatarMoeda(valorJuros);
                                document.getElementById('total_manual').textContent = formatarMoeda(total);
                            } else {
                                // Se não tem taxa, mostrar apenas valor original
                                const valorOriginalManualEl = document.getElementById('valor_original_manual');
                                if (valorOriginalManualEl) valorOriginalManualEl.textContent = formatarMoeda(valorOriginal);
                                document.getElementById('valor_juros_manual').textContent = formatarMoeda(0);
                                document.getElementById('total_manual').textContent = formatarMoeda(valorOriginal);
                            }
                            break;
                        case 'fixo':
                            const valorFixoInput = document.getElementById('valor_juros_fixo');
                            const valorFixo = valorFixoInput ? parseBrDecimal(valorFixoInput.value) : 0;
                            valorJuros = Math.round(valorFixo * 100) / 100;
                            total = Math.round((valorOriginal + valorJuros) * 100) / 100;
                            const valorOriginalFixoEl = document.getElementById('valor_original_fixo');
                            if (valorOriginalFixoEl) valorOriginalFixoEl.textContent = formatarMoeda(valorOriginal);
                            document.getElementById('valor_juros_fixo_exibir').textContent = formatarMoeda(valorJuros);
                            document.getElementById('total_fixo').textContent = formatarMoeda(total);
                            break;
                        case 'executar_garantia':
                            // Executar garantia não cobra nada
                            total = 0;
                            valorJuros = 0;
                            break;
                    }

                    // Ocultar outros resultados quando não selecionados
                    if (tipoJuros !== 'automatico') {
                        const resultadoAutomatico = document.getElementById('resultado_automatico');
                        if (resultadoAutomatico) resultadoAutomatico.style.display = 'none';
                    }
                    if (tipoJuros !== 'renovacao') {
                        const subOpcoesRenovacao = document.getElementById('sub_opcoes_renovacao');
                        if (subOpcoesRenovacao) subOpcoesRenovacao.style.display = 'none';
                    }
                    if (tipoJuros !== 'executar_garantia') {
                        const campoExecutarGarantia = document.getElementById('campo_executar_garantia');
                        if (campoExecutarGarantia) campoExecutarGarantia.style.display = 'none';
                    }

                    if (campoValor) {
                        campoValor.value = total.toFixed(2);
                        if (typeof jQuery !== 'undefined' && jQuery(campoValor).data('maskMoney')) {
                            jQuery(campoValor).maskMoney('mask', total);
                        }
                    }
                }

                // Event listeners para tipo_juros principal
                document.querySelectorAll('input[name="tipo_juros"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        // Mostrar/ocultar campos
                        const campoTaxaManual = document.getElementById('campo_taxa_manual');
                        const campoValorFixo = document.getElementById('campo_valor_fixo');
                        const campoExecutarGarantia = document.getElementById('campo_executar_garantia');
                        const resultadoAutomatico = document.getElementById('resultado_automatico');
                        const subOpcoesRenovacao = document.getElementById('sub_opcoes_renovacao');
                        const camposPagamento = document.querySelectorAll('.campos-pagamento');
                        const btnSubmit = document.getElementById('btnSubmit');
                        const btnSubmitIcon = document.getElementById('btnSubmitIcon');
                        const btnSubmitText = document.getElementById('btnSubmitText');
                        
                        const isExecutarGarantia = this.value === 'executar_garantia';
                        
                        if (campoTaxaManual) {
                            campoTaxaManual.style.display = this.value === 'manual' ? 'block' : 'none';
                        }
                        if (campoValorFixo) {
                            campoValorFixo.style.display = this.value === 'fixo' ? 'block' : 'none';
                        }
                        const campoValorInferior = document.getElementById('campo_valor_inferior');
                        if (campoValorInferior) {
                            campoValorInferior.style.display = this.value === 'valor_inferior' ? 'block' : 'none';
                        }
                        if (campoExecutarGarantia) {
                            campoExecutarGarantia.style.display = isExecutarGarantia ? 'block' : 'none';
                        }
                        if (resultadoAutomatico) {
                            resultadoAutomatico.style.display = this.value === 'automatico' ? 'block' : 'none';
                        }
                        if (subOpcoesRenovacao) {
                            subOpcoesRenovacao.style.display = this.value === 'renovacao' ? 'block' : 'none';
                        }
                        
                        // Ocultar/mostrar campos de pagamento e ajustar required
                        camposPagamento.forEach(campo => {
                            campo.style.display = isExecutarGarantia ? 'none' : 'block';
                            
                            // Remover/adicionar required dos campos quando executar garantia
                            const inputs = campo.querySelectorAll('input, select, textarea');
                            inputs.forEach(input => {
                                if (isExecutarGarantia) {
                                    input.removeAttribute('required');
                                } else {
                                    // Restaurar required apenas se o campo originalmente tinha
                                    if (input.hasAttribute('data-original-required')) {
                                        input.setAttribute('required', 'required');
                                    }
                                }
                            });
                        });
                        
                        // Ajustar required dos campos principais
                        const valorPagamento = document.getElementById('valor_pagamento');
                        const metodoPagamento = document.querySelector('select[name="metodo"]');
                        const dataPagamento = document.querySelector('input[name="data_pagamento"]');
                        
                        if (isExecutarGarantia) {
                            if (valorPagamento) valorPagamento.removeAttribute('required');
                            if (metodoPagamento) metodoPagamento.removeAttribute('required');
                            if (dataPagamento) dataPagamento.removeAttribute('required');
                        } else {
                            if (valorPagamento && !valorPagamento.hasAttribute('readonly')) {
                                valorPagamento.setAttribute('required', 'required');
                            }
                            if (metodoPagamento) metodoPagamento.setAttribute('required', 'required');
                            if (dataPagamento) dataPagamento.setAttribute('required', 'required');
                        }
                        
                        // Alterar botão quando for executar garantia
                        if (btnSubmit && btnSubmitIcon && btnSubmitText) {
                            if (isExecutarGarantia) {
                                btnSubmit.classList.remove('btn-primary');
                                btnSubmit.classList.add('btn-danger');
                                btnSubmitIcon.className = 'bx bx-shield-x';
                                btnSubmitText.textContent = 'Executar Garantia';
                            } else {
                                btnSubmit.classList.remove('btn-danger');
                                btnSubmit.classList.add('btn-primary');
                                btnSubmitIcon.className = 'bx bx-check';
                                btnSubmitText.textContent = 'Registrar Pagamento';
                            }
                        }
                        
                        atualizarValorPagamento();
                    });
                });

                // Event listeners para sub-opções de renovação
                @if($podeRenovar && !$jurosJaPagos)
                document.querySelectorAll('input[name="tipo_juros_renovacao"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const campoTaxaRenovacaoManual = document.getElementById('campo_taxa_renovacao_manual');
                        const campoValorRenovacaoFixo = document.getElementById('campo_valor_renovacao_fixo');
                        const campoRenovacaoAbate = document.getElementById('campo_renovacao_abate');
                        const resultadoRenovacaoAutomatico = document.getElementById('resultado_renovacao_automatico');
                        const resultadoRenovacaoNenhum = document.getElementById('resultado_renovacao_nenhum');
                        
                        if (campoTaxaRenovacaoManual) {
                            campoTaxaRenovacaoManual.style.display = this.value === 'manual' ? 'block' : 'none';
                        }
                        if (campoValorRenovacaoFixo) {
                            campoValorRenovacaoFixo.style.display = this.value === 'fixo' ? 'block' : 'none';
                        }
                        if (campoRenovacaoAbate) {
                            campoRenovacaoAbate.style.display = this.value === 'com_abate' ? 'block' : 'none';
                        }
                        if (resultadoRenovacaoAutomatico) {
                            resultadoRenovacaoAutomatico.style.display = this.value === 'automatico' ? 'block' : 'none';
                        }
                        if (resultadoRenovacaoNenhum) {
                            resultadoRenovacaoNenhum.style.display = this.value === 'nenhum' ? 'block' : 'none';
                        }
                        
                        atualizarValorPagamento();
                    });
                });
                
                document.getElementById('taxa_juros_renovacao_manual')?.addEventListener('input', atualizarValorPagamento);
                document.getElementById('valor_juros_renovacao_fixo')?.addEventListener('input', atualizarValorPagamento);
                const valorRenovacaoAbateEl = document.getElementById('valor_renovacao_abate');
                if (valorRenovacaoAbateEl) {
                    valorRenovacaoAbateEl.addEventListener('input', function() { atualizarValorPagamento(); setTimeout(atualizarValorPagamento, 0); });
                    valorRenovacaoAbateEl.addEventListener('keyup', function() { atualizarValorPagamento(); setTimeout(atualizarValorPagamento, 0); });
                    valorRenovacaoAbateEl.addEventListener('blur', atualizarValorPagamento);
                }
                @endif

                document.getElementById('taxa_juros_manual')?.addEventListener('input', atualizarValorPagamento);
                const valorInferiorInputEl = document.getElementById('valor_inferior_input');
                if (valorInferiorInputEl) {
                    function atualizarAoDigitarValorInferior() {
                        atualizarValorPagamento();
                        setTimeout(atualizarValorPagamento, 0);
                    }
                    valorInferiorInputEl.addEventListener('input', atualizarAoDigitarValorInferior);
                    valorInferiorInputEl.addEventListener('keyup', atualizarAoDigitarValorInferior);
                    valorInferiorInputEl.addEventListener('blur', function() { atualizarValorPagamento(); });
                }
                const valorJurosFixoEl = document.getElementById('valor_juros_fixo');
                if (valorJurosFixoEl) {
                    valorJurosFixoEl.addEventListener('input', atualizarValorPagamento);
                    valorJurosFixoEl.addEventListener('keyup', atualizarValorPagamento);
                    valorJurosFixoEl.addEventListener('blur', atualizarValorPagamento);
                }

                // Verificar se "Executar Garantia" já está selecionado ao carregar (vindo do parâmetro)
                @if(isset($executarGarantia) && $executarGarantia)
                const executarGarantiaRadio = document.getElementById('tipo_juros_executar_garantia');
                if (executarGarantiaRadio) {
                    executarGarantiaRadio.checked = true;
                    // Disparar evento change para aplicar as mudanças visuais
                    executarGarantiaRadio.dispatchEvent(new Event('change'));
                }
                @else
                // Verificar se "Executar Garantia" já está selecionado ao carregar
                const executarGarantiaRadio = document.querySelector('input[name="tipo_juros"][value="executar_garantia"]');
                if (executarGarantiaRadio && executarGarantiaRadio.checked) {
                    // Disparar evento change para aplicar as mudanças visuais
                    executarGarantiaRadio.dispatchEvent(new Event('change'));
                }
                @endif

                // Calcular inicialmente
                atualizarValorPagamento();
                
                // Se veio com parâmetro renovar, garantir que está selecionado e sub-opção conforme renovacao_tipo
                @if(isset($renovar) && $renovar && $podeRenovar && !$jurosJaPagos)
                const radioRenovacao = document.getElementById('tipo_juros_renovacao');
                const renovacaoTipoParam = @json($renovacaoTipo ?? '');
                if (radioRenovacao) {
                    radioRenovacao.checked = true;
                    radioRenovacao.dispatchEvent(new Event('change'));
                    setTimeout(() => {
                        const alvo = (renovacaoTipoParam === 'com_abate') ? document.getElementById('renovacao_com_abate')
                                    : (renovacaoTipoParam === 'nenhum') ? document.getElementById('renovacao_nenhum')
                                    : document.getElementById('renovacao_automatico') || document.getElementById('renovacao_nenhum');
                        if (alvo) {
                            alvo.checked = true;
                            alvo.dispatchEvent(new Event('change'));
                        }
                    }, 100);
                }
                @endif
            })();
            @endif

            // Script dedicado "Valor inferior": atualiza em tempo real sem depender do IIFE acima
            (function() {
                function parseBrDecimal(input) {
                    if (!input) return NaN;
                    var s = String(input).replace(/\s/g, '').replace(/R\$\s?/g, '');
                    if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
                    return parseFloat(s) || NaN;
                }
                function formatarMoeda(valor) {
                    if (isNaN(valor)) return '0,00';
                    return valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }
                function atualizarValorInferior() {
                    var input = document.getElementById('valor_inferior_input');
                    var campo = document.getElementById('campo_valor_inferior');
                    var spanTotal = document.getElementById('total_valor_inferior');
                    var campoValor = document.getElementById('valor_pagamento');
                    if (!input || !campo || !spanTotal || !campoValor) return;
                    var min = parseFloat(campo.getAttribute('data-valor-min')) || 0;
                    var max = parseFloat(campo.getAttribute('data-valor-max')) || 0;
                    var valor = NaN;
                    if (typeof jQuery !== 'undefined' && jQuery(input).data('maskMoney')) {
                        var u = jQuery(input).maskMoney('unmasked');
                        if (u && u[0] != null && !isNaN(u[0])) valor = u[0];
                    }
                    if (isNaN(valor)) valor = parseBrDecimal(input.value);
                    if (isNaN(valor) || valor < min) valor = min;
                    if (valor > max) valor = max;
                    valor = Math.round(valor * 100) / 100;
                    spanTotal.textContent = formatarMoeda(valor);
                    campoValor.value = valor.toFixed(2);
                    if (typeof jQuery !== 'undefined' && jQuery(campoValor).data('maskMoney')) {
                        jQuery(campoValor).maskMoney('mask', valor);
                    }
                }
                function initValorInferior() {
                    var input = document.getElementById('valor_inferior_input');
                    if (!input) return;
                    input.addEventListener('input', atualizarValorInferior);
                    input.addEventListener('keyup', atualizarValorInferior);
                    input.addEventListener('change', atualizarValorInferior);
                    input.addEventListener('blur', atualizarValorInferior);
                    // Observar quando o radio "valor_inferior" for selecionado para dar foco e atualizar
                    var radios = document.querySelectorAll('input[name="tipo_juros"]');
                    radios.forEach(function(r) {
                        r.addEventListener('change', function() {
                            if (this.value === 'valor_inferior') {
                                setTimeout(atualizarValorInferior, 50);
                            }
                        });
                    });
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(initValorInferior, 200);
                        setTimeout(initRenovacaoAbate, 200);
                    });
                } else {
                    setTimeout(initValorInferior, 200);
                    setTimeout(initRenovacaoAbate, 200);
                }
                function atualizarRenovacaoAbate() {
                    var input = document.getElementById('valor_renovacao_abate');
                    var campo = document.getElementById('campo_renovacao_abate');
                    var spanTotal = document.getElementById('total_renovacao_abate');
                    var spanSaldo = document.getElementById('saldo_restante_renovacao_abate');
                    var campoValor = document.getElementById('valor_pagamento');
                    if (!input || !campo || !spanTotal || !spanSaldo) return;
                    var maxVal = parseFloat(campo.getAttribute('data-valor-max')) || 0;
                    var valor = NaN;
                    if (typeof jQuery !== 'undefined' && jQuery(input).data('maskMoney')) {
                        var u = jQuery(input).maskMoney('unmasked');
                        if (u && u[0] != null && !isNaN(u[0])) valor = u[0];
                    }
                    if (isNaN(valor)) valor = parseBrDecimal(input.value);
                    if (isNaN(valor) || valor < 0) valor = 0;
                    if (valor > maxVal) valor = maxVal;
                    valor = Math.round(valor * 100) / 100;
                    spanTotal.textContent = formatarMoeda(valor);
                    spanSaldo.textContent = formatarMoeda(Math.round((maxVal - valor) * 100) / 100);
                    if (campoValor) {
                        campoValor.value = valor.toFixed(2);
                        if (typeof jQuery !== 'undefined' && jQuery(campoValor).data('maskMoney')) {
                            jQuery(campoValor).maskMoney('mask', valor);
                        }
                    }
                }
                function initRenovacaoAbate() {
                    var input = document.getElementById('valor_renovacao_abate');
                    if (!input) return;
                    input.addEventListener('input', function() { atualizarRenovacaoAbate(); setTimeout(atualizarRenovacaoAbate, 0); });
                    input.addEventListener('keyup', function() { atualizarRenovacaoAbate(); setTimeout(atualizarRenovacaoAbate, 0); });
                    input.addEventListener('change', atualizarRenovacaoAbate);
                    input.addEventListener('blur', atualizarRenovacaoAbate);
                    var radios = document.querySelectorAll('input[name="tipo_juros_renovacao"]');
                    radios.forEach(function(r) {
                        r.addEventListener('change', function() {
                            if (this.value === 'com_abate') setTimeout(atualizarRenovacaoAbate, 50);
                        });
                    });
                }
            })();
        </script>
    @endsection