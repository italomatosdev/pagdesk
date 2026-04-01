@extends('layouts.master')
@section('title')
    Cliente #{{ $cliente->id }}
@endsection
@section('page-title')
    Cliente: {{ data_get($operacaoContextoShow, 'ficha')?->nome ?? $cliente->nome }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Totalizadores do cliente -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card border-primary h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-money font-size-24 text-primary"></i>
                        <h5 class="mt-2 mb-0">Total Emprestado</h5>
                        <h4 class="mt-1 mb-0 text-primary">R$ {{ number_format($statsCliente['total_emprestado'] ?? 0, 2, ',', '.') }}</h4>
                        <small class="text-muted">Empréstimos ativos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-wallet font-size-24 text-warning"></i>
                        <h5 class="mt-2 mb-0">Total a Receber</h5>
                        <h4 class="mt-1 mb-0 text-warning">R$ {{ number_format($statsCliente['total_a_receber'] ?? 0, 2, ',', '.') }}</h4>
                        <small class="text-muted">Parcelas em aberto</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-check-circle font-size-24 text-success"></i>
                        <h5 class="mt-2 mb-0">Total Pago</h5>
                        <h4 class="mt-1 mb-0 text-success">R$ {{ number_format($statsCliente['total_pago'] ?? 0, 2, ',', '.') }}</h4>
                        <small class="text-muted">Valor já recebido</small>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($operacaoContextoShow))
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-secondary mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span>
                            <i class="bx bx-layer me-1"></i>
                            Ficha da operação <strong>{{ $operacaoContextoShow['nome'] }}</strong>
                            — nome, contato e endereço abaixo são os dados cadastrados para esta operação.
                        </span>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            @if(!empty($podeDesvincularClienteOperacao))
                                <form method="POST" action="{{ route('clientes.desvincular-operacao', $cliente->id) }}" class="d-inline"
                                    onsubmit="return confirm('Remover o vínculo deste cliente com a operação {{ addslashes($operacaoContextoShow['nome']) }}? A ficha e documentos desta operação serão excluídos. Esta ação só é permitida quando não há empréstimo nesta operação.');">
                                    @csrf
                                    <input type="hidden" name="operacao_id" value="{{ $operacaoContextoShow['id'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bx bx-unlink"></i> Remover vínculo com esta operação
                                    </button>
                                </form>
                            @elseif(!empty($mostrarAvisoEmprestimoBloqueioDesvinculo))
                                <span class="text-muted small">
                                    <i class="bx bx-info-circle"></i> Não é possível remover o vínculo: existe empréstimo nesta operação.
                                </span>
                            @endif
                            <a href="{{ route('clientes.show', ['id' => $cliente->id, 'geral' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                                Ver cadastro geral (sem filtro)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="card-title mb-0">Informações do Cliente</h4>
                                @if(($isSuperAdmin ?? false) && $cliente->empresa)
                                    <small class="text-muted">
                                        <i class="bx bx-building"></i> Empresa: <strong>{{ $cliente->empresa->nome }}</strong>
                                    </small>
                                @endif
                            </div>
                            <div class="d-flex gap-2">
                                @if(!($isSuperAdmin ?? false))
                                    <a href="{{ route('emprestimos.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-primary">
                                        <i class="bx bx-money"></i> Criar Empréstimo
                                    </a>
                                @endif
                                <a href="{{ !empty($operacaoContextoShow) ? route('clientes.edit', ['id' => $cliente->id, 'operacao_id' => $operacaoContextoShow['id']]) : route('clientes.edit', $cliente->id) }}" class="btn btn-warning">
                                    <i class="bx bx-edit"></i> Editar
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            $fichaShow = data_get($operacaoContextoShow, 'ficha');
                        @endphp
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>{{ $cliente->isPessoaFisica() ? 'CPF' : 'CNPJ' }}:</strong> {{ $cliente->documento_formatado }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Nome:</strong> {{ $fichaShow?->nome ?? $cliente->nome }}
                            </div>
                            @if(($isSuperAdmin ?? false) && $cliente->empresa)
                                <div class="col-md-6 mb-3">
                                    <strong>Empresa:</strong> 
                                    <span class="badge bg-primary">{{ $cliente->empresa->nome }}</span>
                                </div>
                            @endif
                            @php
                                $telExibir = $fichaShow?->telefone ?? $cliente->telefone;
                                $digitsWa = preg_replace('/\D/', '', (string) ($telExibir ?? ''));
                                if (strlen($digitsWa) >= 10 && !str_starts_with($digitsWa, '55')) {
                                    $digitsWa = '55'.$digitsWa;
                                }
                                $waUrl = strlen($digitsWa) >= 12 ? 'https://wa.me/'.$digitsWa : null;
                            @endphp
                            <div class="col-md-6 mb-3">
                                <strong>Telefone:</strong>
                                {{ $telExibir ?: '-' }}
                                @if($waUrl)
                                    <a href="{{ $waUrl }}" target="_blank" class="btn btn-sm btn-success ms-2" rel="noopener" title="WhatsApp com o número exibido">
                                        <i class="bx bxl-whatsapp"></i> WhatsApp
                                    </a>
                                @elseif($cliente->temWhatsapp() && !$fichaShow)
                                    <a href="{{ $cliente->whatsapp_link }}" target="_blank" class="btn btn-sm btn-success ms-2" title="Falar no WhatsApp">
                                        <i class="bx bxl-whatsapp"></i> WhatsApp
                                    </a>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email:</strong> {{ $fichaShow?->email ?? $cliente->email ?? '-' }}
                            </div>
                            @if($cliente->isPessoaFisica() && ($fichaShow?->data_nascimento ?? $cliente->data_nascimento))
                                <div class="col-md-6 mb-3">
                                    <strong>Data de Nascimento:</strong>
                                    {{ ($fichaShow?->data_nascimento ?? $cliente->data_nascimento)?->format('d/m/Y') ?? '-' }}
                                </div>
                            @endif
                            
                            @if($cliente->isPessoaJuridica() && ($fichaShow?->responsavel_nome ?? $cliente->responsavel_nome))
                                <div class="col-12 mb-3">
                                    <hr>
                                    <h6 class="mb-3">Responsável Legal</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong>Nome:</strong> {{ $fichaShow?->responsavel_nome ?? $cliente->responsavel_nome }}
                                        </div>
                                        @if($fichaShow?->responsavel_cpf ?? $cliente->responsavel_cpf)
                                            <div class="col-md-6 mb-2">
                                                <strong>CPF:</strong>
                                                @if($fichaShow?->responsavel_cpf)
                                                    {{ \App\Helpers\ValidacaoDocumento::formatarCpf(preg_replace('/\D/', '', $fichaShow->responsavel_cpf)) }}
                                                @else
                                                    {{ $cliente->responsavel_cpf_formatado }}
                                                @endif
                                            </div>
                                        @endif
                                        @if($fichaShow?->responsavel_rg ?? $cliente->responsavel_rg)
                                            <div class="col-md-6 mb-2">
                                                <strong>RG:</strong> {{ $fichaShow?->responsavel_rg ?? $cliente->responsavel_rg }}
                                            </div>
                                        @endif
                                        @if($fichaShow?->responsavel_cnh ?? $cliente->responsavel_cnh)
                                            <div class="col-md-6 mb-2">
                                                <strong>CNH:</strong> {{ $fichaShow?->responsavel_cnh ?? $cliente->responsavel_cnh }}
                                            </div>
                                        @endif
                                        @if($fichaShow?->responsavel_cargo ?? $cliente->responsavel_cargo)
                                            <div class="col-md-6 mb-2">
                                                <strong>Cargo/Função:</strong> {{ $fichaShow?->responsavel_cargo ?? $cliente->responsavel_cargo }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                            @if(($fichaShow?->endereco ?? $cliente->endereco) || ($fichaShow?->cidade ?? $cliente->cidade) || ($fichaShow?->estado ?? $cliente->estado) || ($fichaShow?->cep ?? $cliente->cep))
                                <div class="col-12 mb-3">
                                    <strong>Endereço:</strong>
                                    @if($fichaShow?->endereco ?? $cliente->endereco)
                                        {{ $fichaShow?->endereco ?? $cliente->endereco }}
                                    @endif
                                    @if($fichaShow?->numero ?? $cliente->numero)
                                        , {{ $fichaShow?->numero ?? $cliente->numero }}
                                    @endif
                                    @if($cliente->bairro ?? null)
                                        - {{ $cliente->bairro }}
                                    @endif
                                    @if($fichaShow?->cidade ?? $cliente->cidade)
                                        - {{ $fichaShow?->cidade ?? $cliente->cidade }}/{{ $fichaShow?->estado ?? $cliente->estado }}
                                    @endif
                                    @if($fichaShow?->cep ?? $cliente->cep)
                                        - CEP: {{ $fichaShow?->cep ?? $cliente->cep }}
                                    @endif
                                </div>
                            @endif
                            @if($fichaShow?->observacoes ?? $cliente->observacoes)
                                <div class="col-12 mb-3">
                                    <strong>Observações:</strong><br>
                                    {{ $fichaShow?->observacoes ?? $cliente->observacoes }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Vínculos com Operações -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Vínculos com Operações</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        @if($isSuperAdmin ?? false)
                                            <th>Empresa</th>
                                        @endif
                                        <th>Operação</th>
                                        <th class="text-nowrap">Ficha</th>
                                        <th>Limite de Crédito</th>
                                        <th>Status</th>
                                        <th>Consultor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->operationClients as $vinculo)
                                        <tr>
                                            @if($isSuperAdmin ?? false)
                                                <td>
                                                    @if($vinculo->operacao && $vinculo->operacao->empresa)
                                                        <span class="badge bg-primary">{{ $vinculo->operacao->empresa->nome }}</span>
                                                    @else
                                                        <span class="badge bg-secondary">Sem empresa</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>{{ $vinculo->operacao->nome ?? '-' }}</td>
                                            <td>
                                                @if($vinculo->operacao_id)
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <a href="{{ route('clientes.show', ['id' => $cliente->id, 'operacao_id' => $vinculo->operacao_id]) }}" class="btn btn-sm btn-outline-info" title="Ver ficha desta operação">
                                                            <i class="bx bx-show"></i> Ver
                                                        </a>
                                                        <a href="{{ route('clientes.edit', ['id' => $cliente->id, 'operacao_id' => $vinculo->operacao_id]) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bx bx-edit-alt"></i> Editar
                                                        </a>
                                                    </div>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>R$ {{ number_format($vinculo->limite_credito, 2, ',', '.') }}</td>
                                            <td>
                                                <span class="badge bg-{{ $vinculo->status === 'ativo' ? 'success' : 'danger' }}">
                                                    {{ ucfirst($vinculo->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $vinculo->consultor->name ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ ($isSuperAdmin ?? false) ? 6 : 5 }}" class="text-center">Nenhum vínculo com operações.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Documentos -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Documentos do Cliente</h4>
                    </div>
                    <div class="card-body">
                        @php
                            $documento = $documentoShow ?? null;
                            $selfie = $selfieShow ?? null;
                            $anexos = $anexosShow ?? collect();
                        @endphp

                        <div class="mb-3">
                            <strong>Documento do Cliente:</strong>
                            @if($documento)
                                <div class="mt-2">
                                    @if($documento->isImagem())
                                        <div class="mb-2">
                                            <img src="{{ $documento->url }}" 
                                                 alt="{{ $documento->nome_arquivo ?? 'Documento' }}" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                 onclick="window.open('{{ $documento->url }}', '_blank')">
                                        </div>
                                        <a href="{{ $documento->url }}" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bx bx-download"></i> {{ $documento->nome_arquivo ?? 'Ver Documento' }}
                                        </a>
                                    @else
                                        <a href="{{ $documento->url }}" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bx bx-download"></i> {{ $documento->nome_arquivo ?? 'Ver Documento' }}
                                        </a>
                                    @endif
                                    <small class="text-muted d-block mt-1">
                                        Enviado em: {{ $documento->created_at->format('d/m/Y H:i') }}
                                        @if($documento->isDocumentoEmpresa() && $documento->empresa)
                                            <span class="badge bg-info ms-2">Documento específico desta empresa</span>
                                        @elseif($documento->isDocumentoOriginal())
                                            <span class="badge bg-secondary ms-2">Documento original</span>
                                        @endif
                                    </small>
                                </div>
                            @else
                                <span class="text-danger">Não anexado</span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <strong>Selfie com Documento:</strong>
                            @if($selfie)
                                <div class="mt-2">
                                    @if($selfie->isImagem())
                                        <div class="mb-2">
                                            <img src="{{ $selfie->url }}" 
                                                 alt="{{ $selfie->nome_arquivo ?? 'Selfie' }}" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                 onclick="window.open('{{ $selfie->url }}', '_blank')">
                                        </div>
                                        <a href="{{ $selfie->url }}" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bx bx-download"></i> {{ $selfie->nome_arquivo ?? 'Ver Selfie' }}
                                        </a>
                                    @else
                                        <a href="{{ $selfie->url }}" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bx bx-download"></i> {{ $selfie->nome_arquivo ?? 'Ver Selfie' }}
                                        </a>
                                    @endif
                                    <small class="text-muted d-block mt-1">
                                        Enviado em: {{ $selfie->created_at->format('d/m/Y H:i') }}
                                        @if($selfie->isDocumentoEmpresa() && $selfie->empresa)
                                            <span class="badge bg-info ms-2">Documento específico desta empresa</span>
                                        @elseif($selfie->isDocumentoOriginal())
                                            <span class="badge bg-secondary ms-2">Documento original</span>
                                        @endif
                                    </small>
                                </div>
                            @else
                                <span class="text-danger">Não anexado</span>
                            @endif
                        </div>

                        @if($anexos->count() > 0)
                            <div class="mb-3">
                                <strong>Anexos Adicionais ({{ $anexos->count() }}):</strong>
                                <div class="mt-2">
                                    @foreach($anexos as $anexo)
                                        <div class="mb-3">
                                            @if($anexo->isImagem())
                                                <div class="mb-2">
                                                    <img src="{{ $anexo->url }}" 
                                                         alt="{{ $anexo->nome_arquivo ?? 'Anexo' }}" 
                                                         class="img-thumbnail" 
                                                         style="max-width: 100%; max-height: 400px; cursor: pointer;"
                                                         onclick="window.open('{{ $anexo->url }}', '_blank')">
                                                </div>
                                                <a href="{{ $anexo->url }}" target="_blank" class="btn btn-sm btn-secondary">
                                                    <i class="bx bx-download"></i> {{ $anexo->nome_arquivo ?? 'Ver Anexo' }}
                                                </a>
                                            @else
                                                <a href="{{ $anexo->url }}" target="_blank" class="btn btn-sm btn-secondary">
                                                    <i class="bx bx-download"></i> {{ $anexo->nome_arquivo ?? 'Ver Anexo' }}
                                                </a>
                                            @endif
                                            <small class="text-muted d-block mt-1">
                                                Enviado em: {{ $anexo->created_at->format('d/m/Y H:i') }}
                                                @if($anexo->isDocumentoEmpresa() && $anexo->empresa)
                                                    <span class="badge bg-info ms-2">Documento específico desta empresa</span>
                                                @elseif($anexo->isDocumentoOriginal())
                                                    <span class="badge bg-secondary ms-2">Documento original</span>
                                                @endif
                                            </small>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Empréstimos -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Empréstimos</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        @if($isSuperAdmin ?? false)
                                            <th>Empresa</th>
                                        @endif
                                        <th>Operação</th>
                                        <th>Valor (emprestado)</th>
                                        <th>Valor total (c/ juros)</th>
                                        <th>Próx. venc.</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cliente->emprestimos as $emprestimo)
                                        <tr>
                                            <td>#{{ $emprestimo->id }}</td>
                                            @if($isSuperAdmin ?? false)
                                                <td>
                                                    @if($emprestimo->operacao && $emprestimo->operacao->empresa)
                                                        <span class="badge bg-primary">{{ $emprestimo->operacao->empresa->nome }}</span>
                                                    @else
                                                        <span class="badge bg-secondary">Sem empresa</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>{{ $emprestimo->operacao->nome ?? '-' }}</td>
                                            <td>R$ {{ number_format($emprestimo->valor_total, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($emprestimo->calcularValorTotalComJuros(), 2, ',', '.') }}</td>
                                            <td>{{ $emprestimo->getProximoVencimento()?->format('d/m/Y') ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $emprestimo->status === 'ativo' ? 'success' : ($emprestimo->status === 'pendente' ? 'warning' : 'secondary') }}">
                                                    {{ ucfirst($emprestimo->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $emprestimo->created_at->format('d/m/Y') }}</td>
                                            <td>
                                                <a href="{{ route('emprestimos.show', $emprestimo->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ ($isSuperAdmin ?? false) ? 9 : 8 }}" class="text-center">Nenhum empréstimo encontrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card de Histórico Lateral -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0 text-white">
                            <i class="bx bx-history text-white"></i> Histórico do Cliente
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Empréstimos Ativos em Outras Operações -->
                        @if($emprestimosPorOperacao->count() > 0)
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">
                                    <i class="bx bx-money"></i> Empréstimos Ativos
                                </h6>
                                @foreach($emprestimosPorOperacao as $item)
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <div>
                                            <strong class="d-block">{{ $item['operacao'] }}</strong>
                                            @if($isSuperAdmin ?? false)
                                                @php
                                                    $operacao = \App\Modules\Core\Models\Operacao::with('empresa')->find($item['operacao_id']);
                                                @endphp
                                                @if($operacao && $operacao->empresa)
                                                    <small class="text-muted">
                                                        <i class="bx bx-building"></i> {{ $operacao->empresa->nome }}
                                                    </small>
                                                @endif
                                            @endif
                                            <small class="text-muted d-block">{{ $item['quantidade'] }} empréstimo(s)</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success">R$ {{ number_format($item['total'], 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Parcelas Atrasadas -->
                        @if($atrasadasPorOperacao->count() > 0)
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">
                                    <i class="bx bx-error-circle text-danger"></i> Parcelas Atrasadas
                                </h6>
                                @foreach($atrasadasPorOperacao as $item)
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <div>
                                            <strong class="d-block">{{ $item['operacao'] }}</strong>
                                            @if($isSuperAdmin ?? false)
                                                @php
                                                    $operacao = \App\Modules\Core\Models\Operacao::with('empresa')->find($item['operacao_id']);
                                                @endphp
                                                @if($operacao && $operacao->empresa)
                                                    <small class="text-muted">
                                                        <i class="bx bx-building"></i> {{ $operacao->empresa->nome }}
                                                    </small>
                                                @endif
                                            @endif
                                            <small class="text-muted d-block">{{ $item['quantidade'] }} parcela(s) atrasada(s)</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger">R$ {{ number_format($item['valor_total'], 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                                
                                <div class="mt-3 p-2 bg-danger bg-opacity-10 rounded border border-danger">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-danger">Total Devendo:</strong>
                                        <strong class="text-danger">R$ {{ number_format($totalAtrasado, 2, ',', '.') }}</strong>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-3">
                                <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">Nenhuma parcela atrasada</p>
                            </div>
                        @endif

                        <!-- Resumo -->
                        @if($emprestimosPorOperacao->count() == 0 && $atrasadasPorOperacao->count() == 0)
                            <div class="text-center py-3">
                                <i class="bx bx-info-circle text-info" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">Nenhum histórico disponível</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
    @endsection