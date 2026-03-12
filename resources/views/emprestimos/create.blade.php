@extends('layouts.master')
@section('title')
    {{ ($negociacao ?? false) ? 'Negociar Empréstimo' : 'Novo Empréstimo' }}
@endsection
@section('page-title')
    {{ ($negociacao ?? false) ? 'Negociar Empréstimo' : 'Novo Empréstimo' }}
@endsection
@section('body')

    <body>

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8 mx-auto">
                @if($negociacao ?? false)
                <div class="alert alert-info mb-3">
                    <div class="d-flex align-items-start">
                        <i class="bx bx-info-circle font-size-24 me-2"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Negociação do Empréstimo #{{ $emprestimoOrigem->id }}</h5>
                            <p class="mb-1">
                                <strong>Cliente:</strong> {{ $emprestimoOrigem->cliente->nome }}<br>
                                <strong>Saldo Devedor:</strong> <span class="text-primary fw-bold">R$ {{ number_format($saldoDevedor, 2, ',', '.') }}</span>
                            </p>
                            <small class="text-muted">
                                O empréstimo original será finalizado e um novo será criado com o saldo devedor.
                                @if(!auth()->user()->hasAnyRole(['gestor', 'administrador']))
                                    <br><strong>Nota:</strong> A negociação será enviada para aprovação do gestor/administrador.
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            {{ ($negociacao ?? false) ? 'Definir Novas Condições' : 'Criar Novo Empréstimo' }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('emprestimos.store') }}" method="POST" class="form-criar-emprestimo" data-no-loading>
                            @csrf
                            
                            @if($negociacao ?? false)
                                <input type="hidden" name="negociacao_emprestimo_id" value="{{ $emprestimoOrigem->id }}">
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Operação <span class="text-danger">*</span></label>
                                <select name="operacao_id" class="form-select" required>
                                    <option value="">Selecione uma operação...</option>
                                    @if(isset($operacoes) && $operacoes->count() > 0)
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ old('operacao_id', $operacaoSelecionadaId ?? '') == $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    @else
                                        <option value="" disabled>Nenhuma operação disponível</option>
                                    @endif
                                </select>
                                @error('operacao_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Empréstimo <span class="text-danger">*</span></label>
                                <input type="hidden" name="tipo" id="tipo-emprestimo" value="{{ old('tipo', 'dinheiro') }}" required>
                                <div class="row g-3" id="tipo-emprestimo-cards">
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100 card-tipo-emprestimo border-2 {{ old('tipo', 'dinheiro') == 'dinheiro' ? 'border-primary bg-primary bg-opacity-10' : 'border-light' }}"
                                             style="cursor: pointer; transition: border-color .15s ease, background-color .15s ease;" 
                                             data-tipo="dinheiro" role="button" tabindex="0">
                                            <div class="card-body text-center">
                                                <div class="mb-2"><i class="bx bx-money display-6 text-primary"></i></div>
                                                <h6 class="card-title mb-1">Dinheiro</h6>
                                                <small class="text-muted">Juros simples sobre o valor total</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100 card-tipo-emprestimo border-2 {{ old('tipo') == 'price' ? 'border-primary bg-primary bg-opacity-10' : 'border-light' }}"
                                             style="cursor: pointer; transition: border-color .15s ease, background-color .15s ease;" 
                                             data-tipo="price" role="button" tabindex="0">
                                            <div class="card-body text-center">
                                                <div class="mb-2"><i class="bx bx-table display-6 text-info"></i></div>
                                                <h6 class="card-title mb-1">Sistema Price</h6>
                                                <small class="text-muted">Parcela fixa, juros decrescentes</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100 card-tipo-emprestimo border-2 {{ old('tipo') == 'empenho' ? 'border-primary bg-primary bg-opacity-10' : 'border-light' }}"
                                             style="cursor: pointer; transition: border-color .15s ease, background-color .15s ease;" 
                                             data-tipo="empenho" role="button" tabindex="0">
                                            <div class="card-body text-center">
                                                <div class="mb-2"><i class="bx bx-shield-quarter display-6 text-warning"></i></div>
                                                <h6 class="card-title mb-1">Empenho</h6>
                                                <small class="text-muted">Com garantia de bem (imóvel, veículo)</small>
                                            </div>
                                        </div>
                                    </div>
                                    @if(!($negociacao ?? false))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card h-100 card-tipo-emprestimo border-2 {{ old('tipo') == 'troca_cheque' ? 'border-primary bg-primary bg-opacity-10' : 'border-light' }}"
                                             style="cursor: pointer; transition: border-color .15s ease, background-color .15s ease;" 
                                             data-tipo="troca_cheque" role="button" tabindex="0">
                                            <div class="card-body text-center">
                                                <div class="mb-2"><i class="bx bx-receipt display-6 text-success"></i></div>
                                                <h6 class="card-title mb-1">Troca de Cheque</h6>
                                                <small class="text-muted">Cheques pré-datados, valor descontado</small>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @error('tipo')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Seção de Garantias (Empenho) -->
                            <div id="secao-garantias" class="mb-3" style="display: none;">
                                @if($negociacao ?? false)
                                <div class="alert alert-success mb-0">
                                    <i class="bx bx-shield-quarter me-1"></i>
                                    <strong>Garantias serão transferidas:</strong> As garantias do empréstimo original #{{ $emprestimoOrigem->id }} serão automaticamente transferidas para o novo empréstimo.
                                </div>
                                @else
                                <div class="alert alert-info mb-0">
                                    <i class="bx bx-shield-quarter me-1"></i>
                                    <strong>Empréstimo com Garantia:</strong> Após criar o empréstimo, você poderá adicionar as garantias (bens como imóveis, veículos, etc.) na página de detalhes.
                                    <br><small class="text-muted">Obs: O empréstimo só poderá ser aprovado após cadastrar pelo menos uma garantia.</small>
                                </div>
                                @endif
                            </div>

                            <!-- Seção de Cheques (Troca de Cheque) -->
                            <div id="secao-cheques" class="mb-3" style="display: none;">
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-money me-1"></i>
                                    <strong>Troca de Cheque:</strong> Após criar o empréstimo, você poderá cadastrar os cheques pré-datados na página de detalhes.
                                    <br><small class="text-muted">Obs: O empréstimo só poderá ser aprovado após cadastrar pelo menos um cheque. O valor líquido a ser pago ao cliente será calculado automaticamente (soma dos cheques - juros).</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cliente <span class="text-danger">*</span></label>
                                @if($negociacao ?? false)
                                    <input type="text" class="form-control" value="{{ $clientePreSelecionado->nome }} - {{ $clientePreSelecionado->documento_formatado }}" readonly style="background-color: #e9ecef;">
                                    <input type="hidden" name="cliente_id" value="{{ $clientePreSelecionado->id }}">
                                @else
                                    <select name="cliente_id" id="cliente-select" class="form-select" required>
                                        @php
                                            $clienteSelecionado = null;
                                            // Prioridade: old() > clientePreSelecionado (query string)
                                            if (old('cliente_id')) {
                                                $clienteSelecionado = \App\Modules\Core\Models\Cliente::find(old('cliente_id'));
                                            } elseif (isset($clientePreSelecionado) && $clientePreSelecionado) {
                                                $clienteSelecionado = $clientePreSelecionado;
                                            }
                                        @endphp
                                        @if($clienteSelecionado)
                                            <option value="{{ $clienteSelecionado->id }}" selected>
                                                {{ $clienteSelecionado->nome }} - {{ $clienteSelecionado->documento_formatado }}
                                            </option>
                                        @endif
                                    </select>
                                    <small class="text-muted">Digite o nome ou CPF do cliente para buscar</small>
                                @endif
                                @error('cliente_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Campo Valor Total (oculto para troca de cheque) -->
                            <div class="mb-3" id="campo-valor-total">
                                <label class="form-label">
                                    {{ ($negociacao ?? false) ? 'Saldo Devedor' : 'Valor Total' }} 
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    @if($negociacao ?? false)
                                        <input type="text" class="form-control" value="{{ number_format($saldoDevedor, 2, ',', '.') }}" readonly style="background-color: #e9ecef; font-weight: bold;">
                                        <input type="hidden" name="valor_total" id="valor-total-input" value="{{ $saldoDevedor }}">
                                    @else
                                        <input type="text" name="valor_total" id="valor-total-input" class="form-control" inputmode="decimal"
                                               data-mask-money="brl" placeholder="0,00" value="{{ old('valor_total') }}" required>
                                    @endif
                                </div>
                                @if($negociacao ?? false)
                                    <small class="text-muted">Valor fixo baseado no saldo devedor do empréstimo original</small>
                                @endif
                                @error('valor_total')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Valor Total Calculado (apenas para troca de cheque) -->
                            <div class="mb-3" id="campo-valor-total-calculado" style="display: none;">
                                <label class="form-label">Valor Total dos Cheques</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" id="valor-total-calculado-display" class="form-control" 
                                           readonly style="background-color: #f8f9fa; font-weight: bold;">
                                </div>
                                <small class="text-muted">Calculado automaticamente pela soma dos valores dos cheques</small>
                                <input type="hidden" name="valor_total" id="valor-total-calculado-hidden">
                            </div>

                            <!-- Campos para tipos normais (dinheiro, price, empenho) -->
                            <div id="campos-parcelas" class="campos-tipo-emprestimo">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Número de Parcelas <span class="text-danger">*</span></label>
                                        <input type="number" name="numero_parcelas" id="numero-parcelas" class="form-control" 
                                               min="1" 
                                               value="{{ old('numero_parcelas') }}" required>
                                        @error('numero_parcelas')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Frequência <span class="text-danger">*</span></label>
                                        <select name="frequencia" id="frequencia" class="form-select" required>
                                            <option value="diaria" {{ old('frequencia') == 'diaria' ? 'selected' : '' }}>Diária</option>
                                            <option value="semanal" {{ old('frequencia') == 'semanal' ? 'selected' : '' }}>Semanal</option>
                                            <option value="mensal" {{ old('frequencia') == 'mensal' ? 'selected' : '' }}>Mensal</option>
                                        </select>
                                        @error('frequencia')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                                        <input type="date" name="data_inicio" id="data-inicio" class="form-control" 
                                               value="{{ old('data_inicio', date('Y-m-d')) }}" required>
                                        <small class="text-muted">1ª parcela vence 1 período após esta data</small>
                                        @error('data_inicio')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Taxa de Juros (%) <span id="taxa-juros-required" class="text-danger" style="display: none;">*</span></label>
                                        <input type="number" name="taxa_juros" id="taxa-juros" class="form-control" 
                                               step="0.01" min="0" max="100" 
                                               value="{{ old('taxa_juros', 0) }}">
                                        <small class="text-muted" id="taxa-juros-help">
                                            Para Sistema Price, a taxa de juros é obrigatória
                                        </small>
                                        @error('taxa_juros')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Campos para troca de cheque -->
                            <div id="campos-troca-cheque" class="campos-tipo-emprestimo" style="display: none;">
                                <div class="alert alert-info mb-3">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Configure os cheques:</strong> Adicione os cheques um a um ou informe a quantidade para criar múltiplos cheques. Os valores serão calculados automaticamente.
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Taxa de Juros (%) <span class="text-danger">*</span></label>
                                        <input type="number" name="taxa_juros" id="taxa-juros-troca" class="form-control" 
                                               step="0.01" min="0" max="100" 
                                               value="{{ old('taxa_juros', 0) }}" required>
                                        <small class="text-muted">Taxa de juros ao mês para cálculo dos descontos</small>
                                        @error('taxa_juros')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Quantidade de Cheques</label>
                                        <div class="input-group">
                                            <input type="number" id="qtd-cheques-input" class="form-control" 
                                                   min="1" value="1">
                                            <button type="button" id="btn-criar-cheques" class="btn btn-primary">
                                                <i class="bx bx-plus"></i> Criar Cheques
                                            </button>
                                        </div>
                                        <small class="text-muted">Ou adicione cheques individualmente usando o botão abaixo</small>
                                    </div>
                                </div>
                                
                                <!-- Resumo de Valores (atualizado automaticamente) -->
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <strong>Valor Total dos Cheques:</strong><br>
                                                <span class="h5 text-primary" id="resumo-valor-total">R$ 0,00</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Total de Juros:</strong><br>
                                                <span class="h5 text-warning" id="resumo-total-juros">R$ 0,00</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Valor Líquido a Pagar:</strong><br>
                                                <span class="h5 text-success" id="resumo-valor-liquido">R$ 0,00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tabela de Cheques -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Cheques Cadastrados</h6>
                                        <div class="btn-group">
                                            <button type="button" id="btn-preencher-automatico-teste" class="btn btn-sm btn-warning" title="Preencher com dados de teste">
                                                <i class="bx bx-test-tube"></i> Preencher Automático (Teste)
                                            </button>
                                            <button type="button" id="btn-adicionar-cheque" class="btn btn-sm btn-success">
                                                <i class="bx bx-plus"></i> Adicionar Cheque
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm" id="tabela-cheques">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 3%;">#</th>
                                                    <th style="width: 12%;">Banco <small class="text-muted fw-normal">(opcional)</small></th>
                                                    <th style="width: 8%;">Agência <small class="text-muted fw-normal">(opcional)</small></th>
                                                    <th style="width: 8%;">Conta <small class="text-muted fw-normal">(opcional)</small></th>
                                                    <th style="width: 10%;">Nº Cheque <small class="text-muted fw-normal">(opcional)</small></th>
                                                    <th style="width: 12%;">Valor <span class="text-danger">*</span></th>
                                                    <th style="width: 12%;">Vencimento <span class="text-danger">*</span></th>
                                                    <th style="width: 8%;">Dias</th>
                                                    <th style="width: 10%;">Juros</th>
                                                    <th style="width: 7%;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tabela-cheques-body">
                                                <!-- Preenchido dinamicamente via JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Campos ocultos obrigatórios do sistema -->
                                <input type="hidden" name="numero_parcelas" id="numero-parcelas-troca" value="1">
                                <input type="hidden" id="frequencia-troca" value="mensal">
                                <input type="hidden" id="data-inicio-troca" value="{{ date('Y-m-d') }}">
                            </div>

                            <!-- Botão Simular (apenas para tipos normais) -->
                            <div class="mb-3" id="btn-simular-container">
                                <button type="button" id="btn-simular" class="btn btn-info">
                                    <i class="bx bx-calculator"></i> Simular Empréstimo
                                </button>
                                <small class="text-muted ms-2">Clique para ver o preview do empréstimo</small>
                            </div>

                            <!-- Preview do Empréstimo -->
                            <div id="preview-emprestimo" class="mb-3" style="display: none;">
                                <!-- Preview Troca de Cheque -->
                                <div id="preview-troca-cheque" style="display: none;">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0 text-white"><i class="bx bx-money text-white"></i> Preview - Troca de Cheque</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <strong>Valor Total dos Cheques:</strong><br>
                                                    <span class="h5 text-primary">R$ <span id="preview-valor-total-troca">0,00</span></span>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Taxa de Juros:</strong><br>
                                                    <span class="h6"><span id="preview-taxa-troca">0</span>% ao mês</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Valor Líquido a Pagar:</strong><br>
                                                    <span class="h5 text-success">R$ <span id="preview-liquido-troca">0,00</span></span>
                                                </div>
                                            </div>
                                            

                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Cheque</th>
                                                            <th>Data Vencimento</th>
                                                            <th>Dias</th>
                                                            <th>Valor Cheque</th>
                                                            <th>Juros</th>
                                                            <th>Valor Líquido</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tabela-cheques-troca-body">
                                                        <!-- Preenchido via JavaScript -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="bx bx-info-circle"></i> 
                                                    <strong>Total de Juros:</strong> R$ <span id="preview-juros-total-troca">0,00</span> | 
                                                    <strong>Valor Líquido Total:</strong> R$ <span id="preview-liquido-total-troca">0,00</span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Preview Sistema Price -->
                                <div id="preview-price" style="display: none;">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0 text-white"><i class="bx bx-table text-white"></i> Preview - Sistema Price (Parcela Fixa)</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <strong>Valor do Empréstimo:</strong><br>
                                                    <span class="h5 text-primary">R$ <span id="preview-valor-total-price">0,00</span></span>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Taxa de Juros:</strong><br>
                                                    <span class="h6"><span id="preview-taxa-price">0</span>%</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Valor da Parcela (Fixa):</strong><br>
                                                    <span class="h5 text-success">R$ <span id="preview-parcela-price">0,00</span></span>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Parcela</th>
                                                            <th>Vencimento</th>
                                                            <th>Valor Parcela</th>
                                                            <th>Juros</th>
                                                            <th>Amortização</th>
                                                            <th>Saldo Devedor</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tabela-amortizacao-body">
                                                        <!-- Preenchido via JavaScript -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="bx bx-info-circle"></i> 
                                                    <strong>Total a Pagar:</strong> R$ <span id="preview-total-price">0,00</span> | 
                                                    <strong>Total de Juros:</strong> R$ <span id="preview-juros-total-price">0,00</span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Empréstimo Dinheiro (Juros Simples) -->
                                <div id="preview-dinheiro" style="display: none;">
                                    <div class="card border-success">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="mb-0 text-white"><i class="bx bx-money text-white"></i> Preview - Empréstimo em Dinheiro (Juros Simples)</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <strong>Valor do Empréstimo:</strong><br>
                                                    <span class="h5 text-primary">R$ <span id="preview-valor-total-dinheiro">0,00</span></span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Taxa de Juros:</strong><br>
                                                    <span class="h6"><span id="preview-taxa-dinheiro">0</span>%</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Valor dos Juros:</strong><br>
                                                    <span class="h6 text-warning">R$ <span id="preview-juros-dinheiro">0,00</span></span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Valor Total a Pagar:</strong><br>
                                                    <span class="h5 text-success">R$ <span id="preview-total-dinheiro">0,00</span></span>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Número de Parcelas:</strong> <span id="preview-parcelas-dinheiro">0</span>x
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Valor da Parcela:</strong> 
                                                    <span class="h6">R$ <span id="preview-parcela-dinheiro">0,00</span></span>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Parcela</th>
                                                            <th>Vencimento</th>
                                                            <th>Valor da Parcela</th>
                                                            <th>Valor Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tabela-parcelas-dinheiro-body">
                                                        <!-- Preenchido via JavaScript -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="bx bx-info-circle"></i> 
                                                    Juros calculados sobre o valor total do empréstimo e divididos igualmente entre as parcelas.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes') }}</textarea>
                            </div>

                            @if($negociacao ?? false)
                            <div class="mb-3">
                                <label class="form-label">Motivo da Negociação <span class="text-danger">*</span></label>
                                <textarea name="motivo_negociacao" class="form-control" rows="3" required placeholder="Informe o motivo da negociação...">{{ old('motivo_negociacao') }}</textarea>
                                @error('motivo_negociacao')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif

                            @if($negociacao ?? false)
                            <div class="alert alert-warning">
                                <i class="bx bx-info-circle"></i> 
                                <strong>Atenção:</strong> Ao confirmar, o empréstimo #{{ $emprestimoOrigem->id }} será finalizado e um novo empréstimo será criado com as condições acima.
                                @if(!auth()->user()->hasAnyRole(['gestor', 'administrador']))
                                    A negociação ficará pendente de aprovação.
                                @endif
                            </div>
                            @else
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle"></i> 
                                <strong>Atenção:</strong> O empréstimo será aprovado automaticamente se o cliente não tiver dívida ativa e estiver dentro do limite de crédito. Caso contrário, ficará pendente de aprovação.
                            </div>
                            @endif

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('emprestimos.index') }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    @if($negociacao ?? false)
                                        <i class="bx bx-refresh"></i> 
                                        {{ auth()->user()->hasAnyRole(['gestor', 'administrador']) ? 'Confirmar Negociação' : 'Solicitar Negociação' }}
                                    @else
                                        <i class="bx bx-check"></i> Criar Empréstimo
                                    @endif
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
            document.addEventListener('DOMContentLoaded', function() {
                // Flag para indicar se é negociação
                const isNegociacao = {{ ($negociacao ?? false) ? 'true' : 'false' }};
                
                // Verificar se já há um cliente selecionado (vindo da query string ou old())
                const clienteSelect = document.getElementById('cliente-select');
                
                // Só inicializa Select2 se não for negociação (em negociação, cliente é readonly)
                if (clienteSelect && !isNegociacao) {
                    const clienteJaSelecionado = clienteSelect.options.length > 0 && clienteSelect.options[0].value;
                    
                    // Configuração do Select2
                    const select2Config = {
                        theme: 'bootstrap-5',
                        placeholder: 'Digite o nome ou CPF do cliente...',
                        allowClear: true,
                        minimumInputLength: 2,
                        language: {
                            searching: function() {
                                return 'Buscando...';
                            },
                            loadingMore: function() {
                                return 'Carregando mais resultados...';
                            },
                            noResults: function() {
                                return 'Nenhum cliente encontrado';
                            },
                            inputTooShort: function() {
                                return 'Digite pelo menos 2 caracteres para buscar';
                            }
                        },
                        ajax: {
                            url: '{{ route("clientes.api.buscar") }}',
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term, // termo de busca
                                    page: params.page || 1
                                };
                            },
                            processResults: function (data, params) {
                                params.page = params.page || 1;
                                return {
                                    results: data.results,
                                    pagination: {
                                        more: (params.page * 20) < data.total_count
                                    }
                                };
                            },
                            cache: true
                        }
                    };
                    
                    // Se já há um cliente selecionado, não precisa de minimumInputLength
                    if (clienteJaSelecionado) {
                        select2Config.minimumInputLength = 0;
                    }
                    
                    // Inicializar Select2 para busca de clientes
                    $('#cliente-select').select2(select2Config);
                    
                    // Ao selecionar um cliente, verificar histórico global (Radar)
                    $('#cliente-select').on('select2:select', async function (e) {
                        const clienteId = e.params.data.id;
                        const clienteText = e.params.data.text;
                        
                        // Extrair documento do texto (formato: "Nome - CPF/CNPJ")
                        const partes = clienteText.split(' - ');
                        if (partes.length < 2) return;
                        
                        const documento = partes[partes.length - 1].replace(/\D/g, '');
                        if (!documento || documento.length < 11) return;
                        
                        try {
                            const url = `{{ route('clientes.buscar.cpf') }}?cpf=${documento}`;
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            
                            if (!res.ok) return;
                            
                            const data = await res.json();
                            
                            if (!data.existe || !data.ficha) return;
                            
                            const ficha = data.ficha;
                            const cliente = data.cliente;
                            
                            // Verificar se tem algo relevante para mostrar
                            const totalAtivos = ficha.emprestimos_ativos_total || 0;
                            const totalPendencias = Number(ficha.pendencias_total_em_aberto || 0);
                            
                            // Se não tem empréstimos ativos nem pendências, não mostrar alerta
                            if (totalAtivos === 0 && totalPendencias === 0) return;
                            
                            const fmtMoeda = (v) => {
                                const n = Number(v || 0);
                                return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                            };
                            
                            // Empréstimos ativos por operação
                            const ativosPorOperacaoHtml = (ficha.ativos_por_operacao || [])
                                .filter(item => Number(item.total_ativo || 0) > 0)
                                .map(item => {
                                    const empresaBadge = item.empresa && item.empresa !== item.operacao 
                                        ? `<span class="badge bg-secondary ms-1" style="font-size: 10px;">${item.empresa}</span>`
                                        : '';
                                    return `
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div class="flex-grow-1 text-start">
                                                <div class="fw-semibold small mb-1">${item.operacao || '-'} ${empresaBadge}</div>
                                                <div class="text-muted small">
                                                    ${item.qtd || 0} empréstimo(s)
                                                </div>
                                            </div>
                                            <div class="fw-bold text-primary text-start">${fmtMoeda(item.total_ativo || 0)}</div>
                                        </div>
                                    `;
                                })
                                .join('') || `<div class="text-center text-muted py-3 small">Nenhum empréstimo ativo</div>`;
                            
                            // Pendências por operação
                            const pendenciasPorOperacaoHtml = (ficha.pendencias_por_operacao || [])
                                .filter(item => Number(item.total_em_aberto || 0) > 0)
                                .map(item => {
                                    const empresaBadge = item.empresa && item.empresa !== item.operacao 
                                        ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size: 10px;">${item.empresa}</span>`
                                        : '';
                                    const atrasadasInfo = (item.atrasadas_qtd || 0) > 0 
                                        ? `<span class="text-danger small">${item.atrasadas_qtd} atrasada(s): ${fmtMoeda(item.atrasadas_total || 0)}</span>`
                                        : '';
                                    return `
                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div class="flex-grow-1 text-start">
                                                <div class="fw-semibold small mb-1 d-flex align-items-center text-start">
                                                    ${item.operacao || '-'}
                                                    ${empresaBadge}
                                                </div>
                                                <div class="text-muted small text-start">
                                                    ${atrasadasInfo}
                                                </div>
                                            </div>
                                            <div class="fw-bold text-danger text-start">${fmtMoeda(item.total_em_aberto || 0)}</div>
                                        </div>
                                    `;
                                })
                                .join('') || `<div class="text-center text-muted py-3 small">Nenhuma pendência atrasada</div>`;
                            
                            const valorTotalAtivos = (ficha.ativos_por_operacao || []).reduce((sum, item) => sum + (Number(item.total_ativo || 0)), 0);
                            
                            // Alertas de status
                            let alertaStatus = '';
                            if (totalPendencias > 0) {
                                alertaStatus = `
                                    <div class="alert alert-danger mb-3">
                                        <h6 class="alert-heading mb-1">
                                            <i class="bx bx-error-circle me-2"></i>Atenção: Pendências Atrasadas
                                        </h6>
                                        Este cliente possui <strong>${fmtMoeda(totalPendencias)}</strong> em parcelas vencidas.
                                    </div>
                                `;
                            } else if (totalAtivos > 0) {
                                alertaStatus = `
                                    <div class="alert alert-warning mb-3">
                                        <h6 class="alert-heading mb-1">
                                            <i class="bx bx-info-circle me-2"></i>Informação
                                        </h6>
                                        Este cliente possui <strong>${totalAtivos} empréstimo(s) ativo(s)</strong> 
                                        no valor total de <strong>${fmtMoeda(valorTotalAtivos)}</strong>.
                                    </div>
                                `;
                            }
                            
                            Swal.fire({
                                icon: totalPendencias > 0 ? 'warning' : 'info',
                                title: 'Histórico do Cliente (Radar)',
                                width: 800,
                                customClass: {
                                    popup: 'text-start',
                                    htmlContainer: 'p-0'
                                },
                                html: `
                                    <div class="p-3">
                                        <!-- Info do Cliente -->
                                        <div class="text-center mb-3">
                                            <h5 class="mb-1">${cliente.nome}</h5>
                                            <small class="text-muted">
                                                <i class="bx bx-id-card me-1"></i>${partes[partes.length - 1]}
                                            </small>
                                        </div>
                                        
                                        <!-- Cards de Métricas -->
                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <div class="card h-100 mb-0">
                                                    <div class="card-body py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="text-start">
                                                                <div class="text-muted small">Empréstimos Ativos</div>
                                                                <h5 class="mb-0">${totalAtivos}</h5>
                                                                <small class="text-muted">${fmtMoeda(valorTotalAtivos)}</small>
                                                            </div>
                                                            <div class="avatar-sm">
                                                                <div class="avatar-title rounded bg-primary-subtle">
                                                                    <i class="bx bx-wallet text-primary"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="card h-100 mb-0">
                                                    <div class="card-body py-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="text-start">
                                                                <div class="text-muted small">Pendências</div>
                                                                <h5 class="mb-0 ${totalPendencias > 0 ? 'text-danger' : 'text-success'}">${fmtMoeda(totalPendencias)}</h5>
                                                                <small class="text-muted">${totalPendencias > 0 ? 'Parcelas vencidas' : 'Nenhuma'}</small>
                                                            </div>
                                                            <div class="avatar-sm">
                                                                <div class="avatar-title rounded bg-${totalPendencias > 0 ? 'danger' : 'success'}-subtle">
                                                                    <i class="bx bx-${totalPendencias > 0 ? 'error-circle' : 'check-circle'} text-${totalPendencias > 0 ? 'danger' : 'success'}"></i>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Alerta -->
                                        ${alertaStatus}
                                        
                                        <!-- Detalhes -->
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="card mb-0">
                                                    <div class="card-header py-2">
                                                        <h6 class="card-title mb-0 small">Empréstimos por Operação</h6>
                                                    </div>
                                                    <div class="card-body py-2" style="max-height: 150px; overflow-y: auto;">
                                                        ${ativosPorOperacaoHtml}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card mb-0">
                                                    <div class="card-header py-2">
                                                        <h6 class="card-title mb-0 small">Pendências por Operação</h6>
                                                    </div>
                                                    <div class="card-body py-2" style="max-height: 150px; overflow-y: auto;">
                                                        ${pendenciasPorOperacaoHtml}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-secondary mt-3 mb-0 text-center small">
                                            <i class="bx bx-info-circle me-1"></i>
                                            Esta é apenas uma consulta informativa. Você pode continuar com o empréstimo.
                                        </div>
                                    </div>
                                `,
                                confirmButtonText: 'Entendi, continuar',
                                confirmButtonColor: '#038edc'
                            });
                            
                        } catch (e) {
                            console.error('Erro ao verificar histórico do cliente:', e);
                        }
                    });
                }

                // Calcular data de início automaticamente (data base do empréstimo)
                // A primeira parcela vencerá 1 período após esta data (calculado na simulação e no backend)
                const frequenciaSelect = document.getElementById('frequencia');
                const dataInicioInput = document.getElementById('data-inicio');

                function preencherDataHoje() {
                    const hoje = new Date();
                    const ano = hoje.getFullYear();
                    const mes = String(hoje.getMonth() + 1).padStart(2, '0');
                    const dia = String(hoje.getDate()).padStart(2, '0');
                    dataInicioInput.value = ano + '-' + mes + '-' + dia;
                }

                // Preencher com data de hoje ao carregar, se estiver vazio
                if (!dataInicioInput.value) {
                    preencherDataHoje();
                }

                // Seleção do tipo de empréstimo por cards
                const tipoEmprestimo = document.getElementById('tipo-emprestimo');
                document.querySelectorAll('.card-tipo-emprestimo').forEach(function(card) {
                    function selecionarTipo() {
                        var tipo = card.getAttribute('data-tipo');
                        tipoEmprestimo.value = tipo;
                        document.querySelectorAll('.card-tipo-emprestimo').forEach(function(c) {
                            c.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
                            c.classList.add('border-light');
                        });
                        card.classList.remove('border-light');
                        card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
                        tipoEmprestimo.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    card.addEventListener('click', selecionarTipo);
                    card.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            selecionarTipo();
                        }
                    });
                });

                // Lógica para Simulação de Empréstimo
                const taxaJurosInput = document.getElementById('taxa-juros');
                const taxaJurosRequired = document.getElementById('taxa-juros-required');
                const btnSimular = document.getElementById('btn-simular');
                const previewEmprestimo = document.getElementById('preview-emprestimo');
                const previewPrice = document.getElementById('preview-price');
                const previewDinheiro = document.getElementById('preview-dinheiro');
                const tabelaAmortizacaoBody = document.getElementById('tabela-amortizacao-body');
                const tabelaParcelasDinheiroBody = document.getElementById('tabela-parcelas-dinheiro-body');

                // Atualizar obrigatoriedade da taxa de juros
                function atualizarValidacaoTaxa() {
                    const tipo = tipoEmprestimo.value;
                    if (tipo === 'price' || tipo === 'troca_cheque') {
                        if (tipo === 'price') {
                            taxaJurosRequired.style.display = 'inline';
                            taxaJurosInput.setAttribute('required', 'required');
                        } else {
                            // Para troca_cheque, usar o campo específico
                            const taxaJurosTroca = document.getElementById('taxa-juros-troca');
                            if (taxaJurosTroca) {
                                taxaJurosTroca.setAttribute('required', 'required');
                            }
                        }
                    } else {
                        taxaJurosRequired.style.display = 'none';
                        taxaJurosInput.removeAttribute('required');
                    }
                }

                // Função auxiliar para formatar data
                function formatarData(data) {
                    const dia = String(data.getDate()).padStart(2, '0');
                    const mes = String(data.getMonth() + 1).padStart(2, '0');
                    const ano = data.getFullYear();
                    return dia + '/' + mes + '/' + ano;
                }

                // Função auxiliar para parsear data do input (evita problema de timezone)
                function parseDateInput(dataStr) {
                    if (!dataStr) return new Date();
                    const parts = dataStr.split('-');
                    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                }

                // Função auxiliar para calcular data de vencimento
                // Mesma lógica do backend: primeira parcela vence 1 período após data_inicio
                function calcularDataVencimento(dataInicio, frequencia, numeroParcela) {
                    const data = typeof dataInicio === 'string' ? parseDateInput(dataInicio) : new Date(dataInicio);
                    
                    // Primeira parcela: adiciona 1 período à data de início
                    // Parcelas seguintes: adiciona mais períodos
                    switch(frequencia) {
                        case 'diaria':
                            data.setDate(data.getDate() + numeroParcela);
                            break;
                        case 'semanal':
                            data.setDate(data.getDate() + (numeroParcela * 7));
                            break;
                        case 'mensal':
                            data.setMonth(data.getMonth() + numeroParcela);
                            break;
                    }
                    
                    return data;
                }

                // Simular empréstimo
                function simularEmprestimo() {
                    const tipo = tipoEmprestimo.value;
                    var valorTotalEl = document.querySelector('input[name="valor_total"]');
                    var _v = (valorTotalEl && valorTotalEl.value) || '';
                    _v = String(_v).replace(/\s/g,'').replace(/R\$\s?/g,'');
                    if (_v.indexOf(',') !== -1) _v = _v.replace(/\./g,'').replace(',','.');
                    const valorTotal = parseFloat(_v) || 0;
                    let numeroParcelas, taxaJuros, dataInicio, frequencia;
                    
                    if (tipo === 'troca_cheque') {
                        numeroParcelas = 1; // Não usado, mas necessário
                        taxaJuros = parseFloat(document.getElementById('taxa-juros-troca').value) || 0;
                        dataInicio = document.getElementById('data-inicio-troca').value;
                        frequencia = 'mensal'; // Não usado
                    } else {
                        numeroParcelas = parseInt(document.querySelector('input[name="numero_parcelas"]').value) || 0;
                        taxaJuros = parseFloat(taxaJurosInput.value) || 0;
                        dataInicio = document.getElementById('data-inicio').value;
                        frequencia = document.getElementById('frequencia').value;
                    }

                    // Validações básicas
                    if (valorTotal <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atenção!',
                            text: 'Informe o valor total do empréstimo.',
                            confirmButtonColor: '#038edc'
                        });
                        return;
                    }

                    if (numeroParcelas <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atenção!',
                            text: 'Informe o número de parcelas.',
                            confirmButtonColor: '#038edc'
                        });
                        return;
                    }

                    if (tipo === 'price' && taxaJuros <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atenção!',
                            text: 'Para Sistema Price, a taxa de juros é obrigatória.',
                            confirmButtonColor: '#038edc'
                        });
                        return;
                    }

                    if (tipo === 'troca_cheque' && taxaJuros <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atenção!',
                            text: 'Para Troca de Cheque, a taxa de juros é obrigatória.',
                            confirmButtonColor: '#038edc'
                        });
                        return;
                    }

                    // Simular baseado no tipo
                    const previewTrocaChequeEl = document.getElementById('preview-troca-cheque');
                    
                    if (tipo === 'price') {
                        simularPrice(valorTotal, numeroParcelas, taxaJuros, dataInicio, frequencia);
                        previewPrice.style.display = 'block';
                        previewDinheiro.style.display = 'none';
                        if (previewTrocaChequeEl) previewTrocaChequeEl.style.display = 'none';
                    } else if (tipo === 'troca_cheque') {
                        // Para troca de cheque, o cálculo já é feito em tempo real na tabela
                        // Não precisa de preview separado
                        previewPrice.style.display = 'none';
                        previewDinheiro.style.display = 'none';
                        if (previewTrocaChequeEl) previewTrocaChequeEl.style.display = 'none';
                    } else {
                        simularDinheiro(valorTotal, numeroParcelas, taxaJuros, dataInicio, frequencia);
                        previewPrice.style.display = 'none';
                        previewDinheiro.style.display = 'block';
                        if (previewTrocaChequeEl) previewTrocaChequeEl.style.display = 'none';
                    }

                    previewEmprestimo.style.display = 'block';
                }

                // Simular Sistema Price
                function simularPrice(valorTotal, numeroParcelas, taxaJuros, dataInicio, frequencia) {
                    const taxaDecimal = taxaJuros / 100;
                    const numerador = taxaDecimal * Math.pow(1 + taxaDecimal, numeroParcelas);
                    const denominador = Math.pow(1 + taxaDecimal, numeroParcelas) - 1;
                    const parcelaFixa = valorTotal * (numerador / denominador);
                    
                    let saldoDevedor = valorTotal;
                    let totalJuros = 0;
                    let html = '';

                    for (let i = 1; i <= numeroParcelas; i++) {
                        const juros = saldoDevedor * taxaDecimal;
                        const amortizacao = parcelaFixa - juros;
                        saldoDevedor = saldoDevedor - amortizacao;
                        totalJuros += juros;

                        if (i === numeroParcelas) {
                            saldoDevedor = 0;
                        }

                        // Calcular data de vencimento
                        const dataVencimento = calcularDataVencimento(dataInicio, frequencia, i);
                        const dataVencimentoFormatada = formatarData(dataVencimento);

                        html += 
                            '<tr>' +
                                '<td><strong>' + i + '</strong></td>' +
                                '<td>' + dataVencimentoFormatada + '</td>' +
                                '<td>R$ ' + parcelaFixa.toFixed(2).replace('.', ',') + '</td>' +
                                '<td>R$ ' + juros.toFixed(2).replace('.', ',') + '</td>' +
                                '<td>R$ ' + amortizacao.toFixed(2).replace('.', ',') + '</td>' +
                                '<td>R$ ' + saldoDevedor.toFixed(2).replace('.', ',') + '</td>' +
                            '</tr>';
                    }

                    const totalPagar = parcelaFixa * numeroParcelas;

                    // Atualizar preview Price
                    document.getElementById('preview-valor-total-price').textContent = valorTotal.toFixed(2).replace('.', ',');
                    document.getElementById('preview-taxa-price').textContent = taxaJuros.toFixed(2).replace('.', ',');
                    document.getElementById('preview-parcela-price').textContent = parcelaFixa.toFixed(2).replace('.', ',');
                    document.getElementById('preview-total-price').textContent = totalPagar.toFixed(2).replace('.', ',');
                    document.getElementById('preview-juros-total-price').textContent = totalJuros.toFixed(2).replace('.', ',');
                    tabelaAmortizacaoBody.innerHTML = html;

                    previewPrice.style.display = 'block';
                    previewDinheiro.style.display = 'none';
                }

                // Simular Empréstimo Dinheiro (Juros Simples)
                function simularDinheiro(valorTotal, numeroParcelas, taxaJuros, dataInicio, frequencia) {
                    const valorJuros = valorTotal * (taxaJuros / 100);
                    const valorTotalComJuros = valorTotal + valorJuros;
                    const valorParcela = valorTotalComJuros / numeroParcelas;

                    let html = '';
                    for (let i = 1; i <= numeroParcelas; i++) {
                        // Calcular data de vencimento
                        const dataVencimento = calcularDataVencimento(dataInicio, frequencia, i);
                        const dataVencimentoFormatada = formatarData(dataVencimento);

                        html += 
                            '<tr>' +
                                '<td><strong>' + i + '</strong></td>' +
                                '<td>' + dataVencimentoFormatada + '</td>' +
                                '<td>R$ ' + valorParcela.toFixed(2).replace('.', ',') + '</td>' +
                                '<td>R$ ' + valorTotalComJuros.toFixed(2).replace('.', ',') + '</td>' +
                            '</tr>';
                    }

                    // Atualizar preview Dinheiro
                    document.getElementById('preview-valor-total-dinheiro').textContent = valorTotal.toFixed(2).replace('.', ',');
                    document.getElementById('preview-taxa-dinheiro').textContent = taxaJuros.toFixed(2).replace('.', ',');
                    document.getElementById('preview-juros-dinheiro').textContent = valorJuros.toFixed(2).replace('.', ',');
                    document.getElementById('preview-total-dinheiro').textContent = valorTotalComJuros.toFixed(2).replace('.', ',');
                    document.getElementById('preview-parcelas-dinheiro').textContent = numeroParcelas;
                    document.getElementById('preview-parcela-dinheiro').textContent = valorParcela.toFixed(2).replace('.', ',');
                    tabelaParcelasDinheiroBody.innerHTML = html;

                    previewPrice.style.display = 'none';
                    previewDinheiro.style.display = 'block';
                }

                // Seção de garantias (empenho)
                const secaoGarantias = document.getElementById('secao-garantias');
                // Seção de cheques (troca de cheque)
                const secaoCheques = document.getElementById('secao-cheques');
                // Campos de parcelas vs campos de troca de cheque
                const camposParcelas = document.getElementById('campos-parcelas');
                const camposTrocaCheque = document.getElementById('campos-troca-cheque');

                function atualizarSecaoGarantias() {
                    const tipo = tipoEmprestimo.value;
                    if (tipo === 'empenho') {
                        secaoGarantias.style.display = 'block';
                    } else {
                        secaoGarantias.style.display = 'none';
                    }
                }

                function atualizarSecaoCheques() {
                    const tipo = tipoEmprestimo.value;
                    if (tipo === 'troca_cheque') {
                        secaoCheques.style.display = 'block';
                    } else {
                        secaoCheques.style.display = 'none';
                    }
                }

                // ========== GERENCIAMENTO DE CHEQUES (TROCA DE CHEQUE) ==========
                let contadorCheques = 0;
                
                // Função para adicionar uma linha de cheque na tabela
                function adicionarLinhaCheque() {
                    const tbody = document.getElementById('tabela-cheques-body');
                    if (!tbody) return;
                    
                    contadorCheques++;
                    const hoje = new Date().toISOString().split('T')[0];
                    const dataPadrao = new Date();
                    dataPadrao.setDate(dataPadrao.getDate() + (30 * contadorCheques));
                    const dataPadraoStr = dataPadrao.toISOString().split('T')[0];
                    
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-cheque-id', contadorCheques);
                    tr.innerHTML = 
                        '<td>' + contadorCheques + '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control form-control-sm cheque-banco" ' +
                                   'placeholder="Ex: BB, Caixa..." ' +
                                   'data-cheque-id="' + contadorCheques + '">' +
                        '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control form-control-sm cheque-agencia" ' +
                                   'placeholder="Agência" ' +
                                   'data-cheque-id="' + contadorCheques + '">' +
                        '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control form-control-sm cheque-conta" ' +
                                   'placeholder="Conta" ' +
                                   'data-cheque-id="' + contadorCheques + '">' +
                        '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control form-control-sm cheque-numero" ' +
                                   'placeholder="Nº Cheque" ' +
                                   'data-cheque-id="' + contadorCheques + '">' +
                        '</td>' +
                        '<td>' +
                            '<div class="input-group input-group-sm">' +
                                '<span class="input-group-text">R$</span>' +
                                '<input type="text" class="form-control cheque-valor" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="" ' +
                                       'data-cheque-id="' + contadorCheques + '" required>' +
                            '</div>' +
                        '</td>' +
                        '<td>' +
                            '<input type="date" class="form-control form-control-sm cheque-data" ' +
                                   'value="' + dataPadraoStr + '" min="' + hoje + '" ' +
                                   'data-cheque-id="' + contadorCheques + '" required>' +
                        '</td>' +
                        '<td class="cheque-dias" data-cheque-id="' + contadorCheques + '">-</td>' +
                        '<td class="cheque-juros" data-cheque-id="' + contadorCheques + '">R$ 0,00</td>' +
                        '<td>' +
                            '<button type="button" class="btn btn-sm btn-danger btn-remover-cheque" ' +
                                    'data-cheque-id="' + contadorCheques + '">' +
                                '<i class="bx bx-trash"></i>' +
                            '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                    
                    // Adicionar listeners
                    const inputValor = tr.querySelector('.cheque-valor');
                    const inputData = tr.querySelector('.cheque-data');
                    const btnRemover = tr.querySelector('.btn-remover-cheque');
                    
                    inputValor.addEventListener('input', calcularResumoCheques);
                    inputValor.addEventListener('change', calcularResumoCheques);
                    inputValor.addEventListener('blur', calcularResumoCheques);
                    inputData.addEventListener('input', calcularResumoCheques);
                    inputData.addEventListener('change', calcularResumoCheques);
                    inputData.addEventListener('blur', calcularResumoCheques);
                    btnRemover.addEventListener('click', function() {
                        tr.remove();
                        recalcularNumeracao();
                        calcularResumoCheques();
                    });
                    
                    calcularResumoCheques();
                    if (window.applyMaskBRL) window.applyMaskBRL();
                }
                
                // Função para criar múltiplos cheques de uma vez
                function criarMultiplosCheques(qtd) {
                    const tbody = document.getElementById('tabela-cheques-body');
                    if (!tbody) return;
                    
                    // Limpar cheques existentes
                    tbody.innerHTML = '';
                    contadorCheques = 0;
                    
                    // Criar novos cheques
                    for (let i = 0; i < qtd; i++) {
                        adicionarLinhaCheque();
                    }
                }
                
                // Função para recalcular numeração após remover cheques
                function recalcularNumeracao() {
                    const tbody = document.getElementById('tabela-cheques-body');
                    if (!tbody) return;
                    
                    const linhas = tbody.querySelectorAll('tr');
                    linhas.forEach((linha, index) => {
                        const numero = index + 1;
                        linha.querySelector('td:first-child').textContent = numero;
                    });
                }
                
                // Função para calcular resumo (valor total, juros, líquido)
                function calcularResumoCheques() {
                    const tbody = document.getElementById('tabela-cheques-body');
                    const taxaJurosInput = document.getElementById('taxa-juros-troca');
                    const taxaJuros = parseFloat(taxaJurosInput ? taxaJurosInput.value : 0) || 0;
                    
                    if (!tbody) return;
                    
                    const linhas = tbody.querySelectorAll('tr');
                    let valorTotal = 0;
                    let totalJuros = 0;
                    
                    // Usar a data de hoje do servidor (mesma que o backend usa)
                    // Formato: YYYY-MM-DD (vindo do servidor Laravel)
                    const hojeServidor = '{{ \Carbon\Carbon::today()->format('Y-m-d') }}';
                    const [anoHoje, mesHoje, diaHoje] = hojeServidor.split('-').map(Number);
                    const hoje = new Date(anoHoje, mesHoje - 1, diaHoje); // mês é 0-indexed no JS
                    hoje.setHours(0, 0, 0, 0);
                    
                    linhas.forEach((linha) => {
                        const inputValor = linha.querySelector('.cheque-valor');
                        const inputData = linha.querySelector('.cheque-data');
                        const tdDias = linha.querySelector('.cheque-dias');
                        const tdJuros = linha.querySelector('.cheque-juros');
                        
                        var _sv = (inputValor ? inputValor.value : '') || '';
                        _sv = String(_sv).replace(/\s/g,'').replace(/R\$\s?/g,'');
                        if (_sv.indexOf(',') !== -1) {
                            _sv = _sv.replace(/\./g,'').replace(',','.');
                        } else {
                            _sv = _sv.replace(/\./g,''); // ponto como milhar (ex: 1.000)
                        }
                        const valor = parseFloat(_sv) || 0;
                        const dataStr = inputData ? inputData.value : null;
                        
                        if (valor > 0 && dataStr) {
                            valorTotal += valor;
                            
                            // Calcular dias até vencimento (mesma lógica do Carbon::diffInDays)
                            // Parse da data no formato YYYY-MM-DD (mesmo formato do input date)
                            const [anoVenc, mesVenc, diaVenc] = dataStr.split('-').map(Number);
                            const dataVencimento = new Date(anoVenc, mesVenc - 1, diaVenc); // mês é 0-indexed no JS
                            dataVencimento.setHours(0, 0, 0, 0);
                            
                            // Calcular diferença em dias (replicando Carbon::diffInDays exatamente)
                            // Carbon::diffInDays retorna um float, mas arredonda para o inteiro mais próximo
                            // Para garantir precisão, calculamos a diferença em milissegundos e convertemos
                            const diffTime = dataVencimento.getTime() - hoje.getTime();
                            const diasFloat = diffTime / (1000 * 60 * 60 * 24);
                            // Carbon::diffInDays retorna o número de dias completos (floor do float)
                            const dias = Math.max(0, Math.floor(Math.abs(diasFloat)));
                            
                            // Calcular juros usando a mesma fórmula do backend: (Valor × Taxa × Dias) / (100 × 30)
                            const juros = (valor * taxaJuros * dias) / (100 * 30);
                            totalJuros += juros;
                            
                            // Atualizar células da linha
                            if (tdDias) tdDias.textContent = dias >= 0 ? dias : 0;
                            if (tdJuros) tdJuros.textContent = 'R$ ' + juros.toFixed(2).replace('.', ',');
                        } else {
                            if (tdDias) tdDias.textContent = '-';
                            if (tdJuros) tdJuros.textContent = 'R$ 0,00';
                        }
                    });
                    
                    const valorLiquido = valorTotal - totalJuros;
                    
                    // Atualizar resumo
                    document.getElementById('resumo-valor-total').textContent = 'R$ ' + valorTotal.toFixed(2).replace('.', ',');
                    document.getElementById('resumo-total-juros').textContent = 'R$ ' + totalJuros.toFixed(2).replace('.', ',');
                    document.getElementById('resumo-valor-liquido').textContent = 'R$ ' + valorLiquido.toFixed(2).replace('.', ',');
                    
                    // Atualizar campo hidden do valor total para o formulário
                    const hiddenValorTotal = document.getElementById('valor-total-calculado-hidden');
                    if (hiddenValorTotal) {
                        hiddenValorTotal.value = valorTotal.toFixed(2);
                        // Também atualizar o campo oculto se existir
                        const valorTotalInput = document.getElementById('valor-total-input');
                        if (valorTotalInput && valorTotalInput.style.display === 'none') {
                            valorTotalInput.value = valorTotal.toFixed(2);
                            if (window.applyMaskBRL) window.applyMaskBRL();
                        }
                    }
                    
                    // Atualizar display do valor total
                    const displayValorTotal = document.getElementById('valor-total-calculado-display');
                    if (displayValorTotal) {
                        displayValorTotal.value = valorTotal.toFixed(2).replace('.', ',');
                    }
                }
                
                function atualizarCamposPorTipo() {
                    const tipo = tipoEmprestimo.value;
                    const campoValorTotal = document.getElementById('campo-valor-total');
                    const campoValorTotalCalculado = document.getElementById('campo-valor-total-calculado');
                    const btnSimularContainer = document.getElementById('btn-simular-container');
                    const valorTotalInput = document.getElementById('valor-total-input');
                    const numeroParcelasInput = document.getElementById('numero-parcelas');
                    const numeroParcelasTroca = document.getElementById('numero-parcelas-troca');
                    
                    const hiddenValorTotal = document.getElementById('valor-total-calculado-hidden');
                    if (tipo === 'troca_cheque') {
                        camposParcelas.style.display = 'none';
                        camposTrocaCheque.style.display = 'block';
                        // Ocultar campo valor total manual e mostrar o calculado
                        if (campoValorTotal) campoValorTotal.style.display = 'none';
                        if (campoValorTotalCalculado) campoValorTotalCalculado.style.display = 'block';
                        // Ocultar botão simular padrão
                        if (btnSimularContainer) btnSimularContainer.style.display = 'none';
                        // Só um campo deve ter name="valor_total": o hidden (valor calculado)
                        if (valorTotalInput) {
                            valorTotalInput.removeAttribute('required');
                            valorTotalInput.removeAttribute('name'); // não enviar no POST
                            valorTotalInput.value = '';
                        }
                        if (hiddenValorTotal) {
                            hiddenValorTotal.setAttribute('name', 'valor_total');
                        }
                        if (numeroParcelasInput) {
                            numeroParcelasInput.removeAttribute('required');
                            numeroParcelasInput.removeAttribute('name'); // não enviar no POST
                        }
                        if (numeroParcelasTroca) {
                            numeroParcelasTroca.setAttribute('name', 'numero_parcelas');
                        }
                        // Só um campo data_inicio deve ir no POST: o da troca de cheque
                        const dataInicioInput = document.getElementById('data-inicio');
                        const dataInicioTroca = document.getElementById('data-inicio-troca');
                        if (dataInicioInput) dataInicioInput.removeAttribute('name');
                        if (dataInicioTroca) dataInicioTroca.setAttribute('name', 'data_inicio');
                        // Só um campo taxa_juros deve ir no POST: o da troca de cheque
                        var taxaJurosParcelas = document.getElementById('taxa-juros');
                        var taxaJurosTrocaEl = document.getElementById('taxa-juros-troca');
                        if (taxaJurosParcelas) taxaJurosParcelas.removeAttribute('name');
                        if (taxaJurosTrocaEl) taxaJurosTrocaEl.setAttribute('name', 'taxa_juros');
                        // Preencher campos obrigatórios com valores padrão nos campos hidden
                        if (numeroParcelasTroca) {
                            numeroParcelasTroca.value = 1;
                        }
                        // Frequência: usar hidden para troca_cheque, remover name do select
                        var frequenciaSelect = document.getElementById('frequencia');
                        var frequenciaTroca = document.getElementById('frequencia-troca');
                        if (frequenciaSelect) frequenciaSelect.removeAttribute('name');
                        if (frequenciaTroca) {
                            frequenciaTroca.setAttribute('name', 'frequencia');
                            frequenciaTroca.value = 'mensal';
                        }
                        document.getElementById('data-inicio-troca').value = new Date().toISOString().split('T')[0];
                        // Inicializar tabela de cheques (criar 1 cheque inicial)
                        setTimeout(() => {
                            const tbody = document.getElementById('tabela-cheques-body');
                            if (tbody && tbody.children.length === 0) {
                                adicionarLinhaCheque();
                            }
                        }, 100);
                    } else {
                        camposParcelas.style.display = 'block';
                        camposTrocaCheque.style.display = 'none';
                        // Mostrar campo valor total manual e ocultar o calculado
                        if (campoValorTotal) campoValorTotal.style.display = 'block';
                        if (campoValorTotalCalculado) campoValorTotalCalculado.style.display = 'none';
                        // Mostrar botão simular padrão
                        if (btnSimularContainer) btnSimularContainer.style.display = 'block';
                        // Só um campo deve ter name="valor_total": o input manual
                        if (valorTotalInput) {
                            valorTotalInput.setAttribute('required', 'required');
                            valorTotalInput.setAttribute('name', 'valor_total');
                        }
                        if (hiddenValorTotal) hiddenValorTotal.removeAttribute('name'); // não enviar no POST
                        if (numeroParcelasInput) {
                            numeroParcelasInput.setAttribute('required', 'required');
                            numeroParcelasInput.setAttribute('name', 'numero_parcelas');
                        }
                        if (numeroParcelasTroca) {
                            numeroParcelasTroca.removeAttribute('name'); // não enviar no POST
                        }
                        // Só um campo data_inicio deve ir no POST: o dos campos parcelas
                        const dataInicioInput = document.getElementById('data-inicio');
                        const dataInicioTroca = document.getElementById('data-inicio-troca');
                        if (dataInicioInput) dataInicioInput.setAttribute('name', 'data_inicio');
                        if (dataInicioTroca) dataInicioTroca.removeAttribute('name');
                        // Só um campo taxa_juros deve ir no POST: o dos campos parcelas (dinheiro, price, empenho)
                        var taxaJurosParcelas = document.getElementById('taxa-juros');
                        var taxaJurosTrocaEl = document.getElementById('taxa-juros-troca');
                        if (taxaJurosParcelas) taxaJurosParcelas.setAttribute('name', 'taxa_juros');
                        if (taxaJurosTrocaEl) taxaJurosTrocaEl.removeAttribute('name');
                        // Frequência: usar select para tipos normais, remover name do hidden
                        var frequenciaSelect = document.getElementById('frequencia');
                        var frequenciaTroca = document.getElementById('frequencia-troca');
                        if (frequenciaSelect) frequenciaSelect.setAttribute('name', 'frequencia');
                        if (frequenciaTroca) frequenciaTroca.removeAttribute('name');
                    }
                }

                // Event listeners
                tipoEmprestimo.addEventListener('change', function() {
                    atualizarValidacaoTaxa();
                    atualizarSecaoGarantias();
                    atualizarSecaoCheques();
                    atualizarCamposPorTipo();
                    previewEmprestimo.style.display = 'none';
                });

                // Inicializar seções
                atualizarSecaoGarantias();
                atualizarSecaoCheques();
                atualizarCamposPorTipo();

                // Função para preencher automaticamente com dados de teste
                function preencherDadosTeste() {
                    // Dados de teste da planilha (15 cheques)
                    const dadosTeste = [
                        { valor: 3772.00, vencimento: '2026-02-24' },
                        { valor: 3771.50, vencimento: '2026-03-24' },
                        { valor: 3771.50, vencimento: '2026-04-24' },
                        { valor: 2000.00, vencimento: '2026-03-15' },
                        { valor: 2000.00, vencimento: '2026-02-20' },
                        { valor: 8470.00, vencimento: '2026-02-20' },
                        { valor: 8470.00, vencimento: '2026-03-05' },
                        { valor: 8470.00, vencimento: '2026-03-20' },
                        { valor: 8470.00, vencimento: '2026-04-05' },
                        { valor: 8470.00, vencimento: '2026-04-20' },
                        { valor: 8470.00, vencimento: '2026-05-05' },
                        { valor: 8470.00, vencimento: '2026-05-20' },
                        { valor: 8470.00, vencimento: '2026-06-05' },
                        { valor: 8470.00, vencimento: '2026-06-20' },
                        { valor: 8470.00, vencimento: '2026-07-05' }
                    ];
                    
                    // Limpar cheques existentes
                    const tbody = document.getElementById('tabela-cheques-body');
                    if (!tbody) return;
                    
                    tbody.innerHTML = '';
                    contadorCheques = 0;
                    
                    // Criar cheques com dados de teste
                    dadosTeste.forEach(function(dado) {
                        const tr = document.createElement('tr');
                        contadorCheques++;
                        tr.setAttribute('data-cheque-id', contadorCheques);
                        tr.innerHTML = 
                            '<td>' + contadorCheques + '</td>' +
                            '<td>' +
                                '<input type="text" class="form-control form-control-sm cheque-banco" ' +
                                       'placeholder="Ex: BB, Caixa..." ' +
                                       'data-cheque-id="' + contadorCheques + '">' +
                            '</td>' +
                            '<td>' +
                                '<input type="text" class="form-control form-control-sm cheque-agencia" ' +
                                       'placeholder="Agência" ' +
                                       'data-cheque-id="' + contadorCheques + '">' +
                            '</td>' +
                            '<td>' +
                                '<input type="text" class="form-control form-control-sm cheque-conta" ' +
                                       'placeholder="Conta" ' +
                                       'data-cheque-id="' + contadorCheques + '">' +
                            '</td>' +
                            '<td>' +
                                '<input type="text" class="form-control form-control-sm cheque-numero" ' +
                                       'placeholder="Nº Cheque" ' +
                                       'data-cheque-id="' + contadorCheques + '">' +
                            '</td>' +
                            '<td>' +
                                '<div class="input-group input-group-sm">' +
                                    '<span class="input-group-text">R$</span>' +
                                    '<input type="text" class="form-control cheque-valor" inputmode="decimal" data-mask-money="brl" placeholder="0,00" value="' + dado.valor.toFixed(2).replace('.', ',') + '" ' +
                                           'data-cheque-id="' + contadorCheques + '" required>' +
                                '</div>' +
                            '</td>' +
                            '<td>' +
                                '<input type="date" class="form-control form-control-sm cheque-data" ' +
                                       'value="' + dado.vencimento + '" ' +
                                       'data-cheque-id="' + contadorCheques + '" required>' +
                            '</td>' +
                            '<td class="cheque-dias" data-cheque-id="' + contadorCheques + '">-</td>' +
                            '<td class="cheque-juros" data-cheque-id="' + contadorCheques + '">R$ 0,00</td>' +
                            '<td>' +
                                '<button type="button" class="btn btn-sm btn-danger btn-remover-cheque" ' +
                                        'data-cheque-id="' + contadorCheques + '">' +
                                    '<i class="bx bx-trash"></i>' +
                                '</button>' +
                            '</td>';
                        tbody.appendChild(tr);
                        
                        // Adicionar listeners
                        const inputValor = tr.querySelector('.cheque-valor');
                        const inputData = tr.querySelector('.cheque-data');
                        const btnRemover = tr.querySelector('.btn-remover-cheque');
                        
                        inputValor.addEventListener('input', calcularResumoCheques);
                        inputValor.addEventListener('change', calcularResumoCheques);
                        inputValor.addEventListener('blur', calcularResumoCheques);
                        inputData.addEventListener('input', calcularResumoCheques);
                        inputData.addEventListener('change', calcularResumoCheques);
                        inputData.addEventListener('blur', calcularResumoCheques);
                        btnRemover.addEventListener('click', function() {
                            tr.remove();
                            recalcularNumeracao();
                            calcularResumoCheques();
                        });
                        if (window.applyMaskBRL) window.applyMaskBRL();
                    });
                    
                    // Calcular resumo após preencher
                    calcularResumoCheques();
                    
                    // Mostrar mensagem de sucesso
                    Swal.fire({
                        icon: 'success',
                        title: 'Dados Preenchidos!',
                        text: '15 cheques foram preenchidos automaticamente com dados de teste.',
                        confirmButtonColor: '#038edc',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
                
                // Listeners para gerenciamento de cheques
                const btnAdicionarCheque = document.getElementById('btn-adicionar-cheque');
                const btnCriarCheques = document.getElementById('btn-criar-cheques');
                const btnPreencherTeste = document.getElementById('btn-preencher-automatico-teste');
                const qtdChequesInput = document.getElementById('qtd-cheques-input');
                const taxaJurosTrocaInput = document.getElementById('taxa-juros-troca');
                
                if (btnPreencherTeste) {
                    btnPreencherTeste.addEventListener('click', function() {
                        preencherDadosTeste();
                    });
                }
                
                if (btnAdicionarCheque) {
                    btnAdicionarCheque.addEventListener('click', function() {
                        adicionarLinhaCheque();
                    });
                }
                
                if (btnCriarCheques && qtdChequesInput) {
                    btnCriarCheques.addEventListener('click', function() {
                        const qtd = parseInt(qtdChequesInput.value) || 1;
                        if (qtd < 1) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'A quantidade de cheques deve ser pelo menos 1.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        criarMultiplosCheques(qtd);
                    });
                }
                
                if (taxaJurosTrocaInput) {
                    taxaJurosTrocaInput.addEventListener('input', calcularResumoCheques);
                    taxaJurosTrocaInput.addEventListener('change', calcularResumoCheques);
                    taxaJurosTrocaInput.addEventListener('blur', calcularResumoCheques);
                }
                


                btnSimular.addEventListener('click', simularEmprestimo);

                // Inicializar validação
                atualizarValidacaoTaxa();

                // Validação e confirmação do formulário
                document.querySelectorAll('.form-criar-emprestimo').forEach(form => {
                    const submitHandler = function(e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        
                        const tipo = tipoEmprestimo.value;
                        let valor = 0;
                        let parcelas = 0;
                        
                        // Para troca_cheque, usar campos hidden
                        if (tipo === 'troca_cheque') {
                            const hiddenValorTotal = document.getElementById('valor-total-calculado-hidden');
                            const numeroParcelasTroca = document.getElementById('numero-parcelas-troca');
                            
                            if (hiddenValorTotal) {
                                valor = parseFloat(hiddenValorTotal.value) || 0;
                            }
                            if (numeroParcelasTroca) {
                                parcelas = parseInt(numeroParcelasTroca.value) || 0;
                            }
                            
                            // Garantir que o valor total está atualizado antes de submeter
                            calcularResumoCheques();
                            const hiddenValorTotalAtualizado = document.getElementById('valor-total-calculado-hidden');
                            if (hiddenValorTotalAtualizado) {
                                valor = parseFloat(hiddenValorTotalAtualizado.value) || 0;
                            }
                            
                            // Validar se há cheques cadastrados
                            const tbody = document.getElementById('tabela-cheques-body');
                            if (!tbody || tbody.children.length === 0) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Atenção!',
                                    text: 'Adicione pelo menos um cheque antes de criar o empréstimo.',
                                    confirmButtonColor: '#038edc'
                                });
                                return;
                            }
                            
                            // Validar se todos os cheques têm valor e data de vencimento (banco, agência, conta e nº cheque podem ser preenchidos depois)
                            let chequesInvalidos = false;
                            const linhas = tbody.querySelectorAll('tr');
                            linhas.forEach(function(linha) {
                                const inputValor = linha.querySelector('.cheque-valor');
                                const inputData = linha.querySelector('.cheque-data');
                                var _cv = (inputValor ? inputValor.value : '') || '';
                                _cv = String(_cv).replace(/\s/g,'').replace(/R\$\s?/g,'');
                                if (_cv.indexOf(',') !== -1) _cv = _cv.replace(/\./g,'').replace(',','.');
                                var valCheque = parseFloat(_cv) || '';
                                if (!inputValor || !inputValor.value || parseFloat(valCheque) <= 0) {
                                    chequesInvalidos = true;
                                }
                                if (!inputData || !inputData.value) {
                                    chequesInvalidos = true;
                                }
                            });
                            
                            if (chequesInvalidos) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Atenção!',
                                    text: 'Preencha o valor e a data de vencimento de todos os cheques.',
                                    confirmButtonColor: '#038edc'
                                });
                                return;
                            }
                        } else {
                            // Para outros tipos, usar campos normais
                            var vtEl = this.querySelector('input[name="valor_total"]');
                            var _vt = (vtEl && vtEl.value) || '';
                            _vt = String(_vt).replace(/\s/g,'').replace(/R\$\s?/g,'');
                            if (_vt.indexOf(',') !== -1) _vt = _vt.replace(/\./g,'').replace(',','.');
                            valor = parseFloat(_vt) || 0;
                            parcelas = parseInt(this.querySelector('input[name="numero_parcelas"]').value) || 0;
                        }
                        
                        const clienteSelect = document.getElementById('cliente-select');
                        const clienteHidden = document.querySelector('input[type="hidden"][name="cliente_id"]');
                        const clienteTexto = clienteSelect && clienteSelect.selectedIndex >= 0 
                            ? clienteSelect.options[clienteSelect.selectedIndex].text 
                            : (isNegociacao ? 'Cliente da negociação' : 'Não selecionado');
                        const clienteValido = clienteSelect ? clienteSelect.value : (clienteHidden ? clienteHidden.value : null);
                        
                        if (valor <= 0 || parcelas <= 0 || !clienteValido) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atenção!',
                                text: 'Por favor, preencha todos os campos obrigatórios.',
                                confirmButtonColor: '#038edc'
                            });
                            return;
                        }
                        
                        // Para troca_cheque, atualizar campos hidden antes de submeter
                        if (tipo === 'troca_cheque') {
                            // Garantir que o valor total está atualizado
                            calcularResumoCheques();
                            const hiddenValorTotal = document.getElementById('valor-total-calculado-hidden');
                            if (hiddenValorTotal) {
                                valor = parseFloat(hiddenValorTotal.value) || 0;
                            }
                            
                            // Atualizar também o campo oculto valor_total se existir
                            const valorTotalInput = document.getElementById('valor-total-input');
                            if (valorTotalInput) {
                                valorTotalInput.value = valor.toFixed(2);
                                if (window.applyMaskBRL) window.applyMaskBRL();
                            }
                            
                            // Garantir que numero_parcelas está preenchido
                            const numeroParcelasTroca = document.getElementById('numero-parcelas-troca');
                            if (numeroParcelasTroca) {
                                parcelas = parseInt(numeroParcelasTroca.value) || 1;
                            }
                        }
                        
                        const tituloConfirmacao = isNegociacao ? 'Confirmar Negociação?' : 'Criar Empréstimo?';
                        const textoConfirmacao = isNegociacao 
                            ? 'Sim, negociar!' 
                            : 'Sim, criar!';
                        
                        Swal.fire({
                            title: tituloConfirmacao,
                            html: '<strong>Cliente:</strong> ' + clienteTexto + '<br>' +
                                   '<strong>Valor:</strong> R$ ' + valor.toFixed(2).replace('.', ',') + '<br>' +
                                   (tipo === 'troca_cheque' ? '<strong>Cheques:</strong> ' + (document.getElementById('tabela-cheques-body') ? document.getElementById('tabela-cheques-body').children.length : 0) + '<br>' : '<strong>Parcelas:</strong> ' + parcelas + 'x'),
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: textoConfirmacao,
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Para troca_cheque, garantir que campos estão preenchidos antes de submeter
                                if (tipo === 'troca_cheque') {
                                    calcularResumoCheques();
                                    const hiddenValorTotal = document.getElementById('valor-total-calculado-hidden');
                                    const valorTotalInput = document.getElementById('valor-total-input');
                                    if (hiddenValorTotal && hiddenValorTotal.value) {
                                        if (valorTotalInput) {
                                            valorTotalInput.value = hiddenValorTotal.value;
                                            if (window.applyMaskBRL) window.applyMaskBRL();
                                        }
                                    }
                                    
                                    // Remover campos hidden de cheques antigos se existirem
                                    document.querySelectorAll('input[name^="cheques["]').forEach(input => input.remove());
                                    
                                    // Adicionar campos hidden com dados dos cheques
                                    const tbody = document.getElementById('tabela-cheques-body');
                                    if (tbody && tbody.children.length > 0) {
                                        const linhas = tbody.querySelectorAll('tr');
                                        linhas.forEach(function(linha, index) {
                                            const inputValor = linha.querySelector('.cheque-valor');
                                            const inputData = linha.querySelector('.cheque-data');
                                            const inputBanco = linha.querySelector('.cheque-banco');
                                            const inputAgencia = linha.querySelector('.cheque-agencia');
                                            const inputConta = linha.querySelector('.cheque-conta');
                                            const inputNumero = linha.querySelector('.cheque-numero');
                                            
                                            if (inputValor && inputValor.value && inputData && inputData.value) {
                                                var _cn = String((inputValor && inputValor.value) || '').replace(/\s/g,'').replace(/R\$\s?/g,'');
                                                if (_cn.indexOf(',') !== -1) _cn = _cn.replace(/\./g,'').replace(',','.');
                                                else _cn = _cn.replace(/\./g,'');
                                                var valorChequeNum = (parseFloat(_cn) || 0).toFixed(2);
                                                const campos = {
                                                    'banco': inputBanco ? (inputBanco.value || '').trim() : '',
                                                    'agencia': inputAgencia ? (inputAgencia.value || '').trim() : '',
                                                    'conta': inputConta ? (inputConta.value || '').trim() : '',
                                                    'numero_cheque': inputNumero ? (inputNumero.value || '').trim() : '',
                                                    'data_vencimento': inputData.value,
                                                    'valor_cheque': valorChequeNum
                                                };
                                                
                                                // Adicionar taxa de juros se houver
                                                const taxaJurosTrocaInput = document.getElementById('taxa-juros-troca');
                                                if (taxaJurosTrocaInput && taxaJurosTrocaInput.value) {
                                                    campos['taxa_juros'] = taxaJurosTrocaInput.value;
                                                }
                                                
                                                // Criar inputs hidden para cada campo
                                                Object.keys(campos).forEach(function(key) {
                                                    const input = document.createElement('input');
                                                    input.type = 'hidden';
                                                    input.name = 'cheques[' + index + '][' + key + ']';
                                                    input.value = campos[key] || '';
                                                    form.appendChild(input);
                                                });
                                            }
                                        });
                                    }
                                }
                                
                                // Remover o handler antes de submeter para evitar loop
                                form.removeEventListener('submit', submitHandler, true);
                                form.removeAttribute('data-no-loading');
                                form.submit();
                            }
                        });
                    };
                    
                    form.addEventListener('submit', submitHandler, true);
                });
            });
        </script>
    @endsection