@extends('layouts.master')
@section('title')
    Garantia #{{ $garantia->id }}
@endsection
@section('page-title')
    Garantia #{{ $garantia->id }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8">
                <!-- Informações da Garantia -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Informações da Garantia</h4>
                            @php
                                $status = $garantia->status ?? 'ativa';
                            @endphp
                            <span class="badge bg-{{ $garantia->status_cor }}">
                                <i class="bx bx-shield-{{ $status === 'ativa' ? 'quarter' : ($status === 'liberada' ? 'check' : 'x') }}"></i>
                                {{ $garantia->status_nome }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Categoria:</strong><br>
                                <span class="badge bg-{{ $garantia->categoria === 'imovel' ? 'primary' : ($garantia->categoria === 'veiculo' ? 'info' : 'secondary') }}">
                                    <i class="bx {{ $garantia->categoria_icone }}"></i> {{ $garantia->categoria_nome }}
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Descrição:</strong><br>
                                {{ $garantia->descricao }}
                            </div>
                            @if($garantia->valor_avaliado)
                            <div class="col-md-6 mb-3">
                                <strong>Valor Avaliado:</strong><br>
                                <span class="text-success fw-bold fs-5">{{ $garantia->valor_formatado }}</span>
                            </div>
                            @endif
                            @if($garantia->localizacao)
                            <div class="col-md-6 mb-3">
                                <strong>Localização:</strong><br>
                                <i class="bx bx-map text-muted"></i> {{ $garantia->localizacao }}
                            </div>
                            @endif
                            @if($garantia->observacoes)
                            <div class="col-12 mb-3">
                                <strong>Observações:</strong><br>
                                <div class="alert alert-light mb-0" style="white-space: pre-wrap;">{{ $garantia->observacoes }}</div>
                            </div>
                            @endif
                            
                            <!-- Informações de Status -->
                            @if($garantia->isLiberada() && $garantia->data_liberacao)
                            <div class="col-12 mb-3">
                                <div class="alert alert-success">
                                    <i class="bx bx-shield-check"></i>
                                    <strong>Garantia Liberada</strong><br>
                                    <small>Liberada em: {{ $garantia->data_liberacao->format('d/m/Y H:i') }}</small>
                                </div>
                            </div>
                            @endif
                            
                            @if($garantia->isExecutada() && $garantia->data_execucao)
                            <div class="col-12 mb-3">
                                <div class="alert alert-danger">
                                    <i class="bx bx-shield-x"></i>
                                    <strong>Garantia Executada</strong><br>
                                    <small>Executada em: {{ $garantia->data_execucao->format('d/m/Y H:i') }}</small>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Anexos -->
                @if($garantia->anexos->count() > 0)
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-paperclip"></i> Anexos ({{ $garantia->anexos->count() }})
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($garantia->anexos as $anexo)
                                <div class="col-md-4 col-6">
                                    @if($anexo->tipo === 'imagem')
                                        <div class="card border">
                                            <a href="{{ $anexo->url }}" target="_blank" class="text-decoration-none">
                                                <img src="{{ $anexo->url }}" 
                                                     alt="{{ $anexo->nome_arquivo }}" 
                                                     class="card-img-top"
                                                     style="height: 200px; object-fit: cover;">
                                                <div class="card-body p-2">
                                                    <small class="text-muted d-block text-truncate" title="{{ $anexo->nome_arquivo }}">
                                                        {{ $anexo->nome_arquivo }}
                                                    </small>
                                                </div>
                                            </a>
                                        </div>
                                    @else
                                        <div class="card border">
                                            <div class="card-body text-center">
                                                <i class="bx {{ $anexo->icone }} font-size-48 text-muted"></i>
                                                <p class="mb-1 small text-truncate" title="{{ $anexo->nome_arquivo }}">
                                                    {{ $anexo->nome_arquivo }}
                                                </p>
                                                <small class="text-muted">{{ $anexo->tamanho_formatado }}</small>
                                                <br>
                                                <a href="{{ $anexo->url }}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                                    <i class="bx bx-download"></i> Baixar
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4">
                <!-- Informações do Empréstimo -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-money"></i> Empréstimo Relacionado
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>Empréstimo:</strong><br>
                            <a href="{{ route('emprestimos.show', $garantia->emprestimo_id) }}">
                                #{{ $garantia->emprestimo_id }}
                            </a>
                        </p>
                        <p class="mb-2">
                            <strong>Cliente:</strong><br>
                            <a href="{{ \App\Support\ClienteUrl::show($garantia->emprestimo->cliente_id, $garantia->emprestimo->operacao_id) }}">
                                {{ $nomeClienteExibicao ?? \App\Support\ClienteNomeExibicao::forEmprestimo($garantia->emprestimo) }}
                            </a>
                        </p>
                        <p class="mb-2">
                            <strong>Status:</strong><br>
                            @php
                                $statusBadge = match($garantia->emprestimo->status) {
                                    'ativo' => 'success',
                                    'pendente' => 'warning',
                                    'aprovado' => 'info',
                                    'finalizado' => 'secondary',
                                    'cancelado' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $statusBadge }}">{{ ucfirst($garantia->emprestimo->status) }}</span>
                        </p>
                        @if($garantia->emprestimo->valor_total)
                        <p class="mb-2">
                            <strong>Valor:</strong><br>
                            R$ {{ number_format($garantia->emprestimo->valor_total, 2, ',', '.') }}
                        </p>
                        @endif
                        <div class="mt-3">
                            <a href="{{ route('emprestimos.show', $garantia->emprestimo_id) }}" class="btn btn-primary w-100">
                                <i class="bx bx-show"></i> Ver Detalhes do Empréstimo
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Informações da Operação -->
                @if($garantia->emprestimo->operacao)
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-building"></i> Operação
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">
                            <strong>{{ $garantia->emprestimo->operacao->nome }}</strong>
                        </p>
                    </div>
                </div>
                @endif

                <!-- Ações -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-cog"></i> Ações
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('garantias.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar para Listagem
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
