@extends('layouts.master')
@section('title')
    Garantias de Empenho
@endsection
@section('page-title')
    Garantias de Empenho
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Cards de Estatísticas -->
        <div class="row g-2 mb-3">
            <div class="col mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center">
                        <i class="bx bx-shield-quarter font-size-24 text-warning"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['total'] }}</h4>
                        <small class="text-muted">Total de Garantias</small>
                    </div>
                </div>
            </div>
            <div class="col mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-money font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}</h4>
                        <small class="text-muted">Valor Total Avaliado</small>
                    </div>
                </div>
            </div>
            <div class="col mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-building-house font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['imoveis'] }}</h4>
                        <small class="text-muted">Imóveis</small>
                    </div>
                </div>
            </div>
            <div class="col mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-car font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['veiculos'] }}</h4>
                        <small class="text-muted">Veículos</small>
                    </div>
                </div>
            </div>
            <div class="col mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-package font-size-24 text-secondary"></i>
                        <h4 class="mt-2 mb-0">{{ $stats['outros'] }}</h4>
                        <small class="text-muted">Outros</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bx bx-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('garantias.index') }}">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="busca" class="form-control" 
                                   placeholder="Descrição, localização ou cliente..." 
                                   value="{{ request('busca') }}">
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Categoria</label>
                            <select name="categoria" class="form-select">
                                <option value="">Todas</option>
                                <option value="imovel" {{ request('categoria') == 'imovel' ? 'selected' : '' }}>Imóvel</option>
                                <option value="veiculo" {{ request('categoria') == 'veiculo' ? 'selected' : '' }}>Veículo</option>
                                <option value="outros" {{ request('categoria') == 'outros' ? 'selected' : '' }}>Outros</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Operação</label>
                            <select name="operacao_id" class="form-select">
                                <option value="">Todas</option>
                                @foreach($operacoes as $operacao)
                                    <option value="{{ $operacao->id }}" {{ request('operacao_id') == $operacao->id ? 'selected' : '' }}>
                                        {{ $operacao->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Status Garantia</label>
                            <select name="status" class="form-select">
                                <option value="">Todas</option>
                                <option value="ativa" {{ request('status') == 'ativa' ? 'selected' : '' }}>Ativa</option>
                                <option value="liberada" {{ request('status') == 'liberada' ? 'selected' : '' }}>Liberada</option>
                                <option value="executada" {{ request('status') == 'executada' ? 'selected' : '' }}>Executada</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label">Status Empréstimo</label>
                            <select name="status_emprestimo" class="form-select">
                                <option value="">Todos</option>
                                <option value="pendente" {{ request('status_emprestimo') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                <option value="aprovado" {{ request('status_emprestimo') == 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                <option value="ativo" {{ request('status_emprestimo') == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                <option value="finalizado" {{ request('status_emprestimo') == 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                                <option value="cancelado" {{ request('status_emprestimo') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Buscar
                            </button>
                            <a href="{{ route('garantias.index') }}" class="btn btn-secondary">
                                <i class="bx bx-x"></i> Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listagem -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bx bx-shield-quarter text-warning"></i> Garantias Cadastradas
                </h5>
                <span class="badge bg-warning">{{ $garantias->total() }} garantias</span>
            </div>
            <div class="card-body">
                @if($garantias->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th>Categoria</th>
                                    <th>Descrição</th>
                                    <th>Status</th>
                                    <th>Valor Avaliado</th>
                                    <th>Localização</th>
                                    <th>Cliente</th>
                                    <th>Empréstimo</th>
                                    <th>Anexos</th>
                                    <th width="100">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($garantias as $garantia)
                                    <tr>
                                        <td>{{ $garantia->id }}</td>
                                        <td>
                                            @php
                                                $catBadge = match($garantia->categoria) {
                                                    'imovel' => ['Imóvel', 'primary', 'bx-building-house'],
                                                    'veiculo' => ['Veículo', 'info', 'bx-car'],
                                                    default => ['Outros', 'secondary', 'bx-package']
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $catBadge[1] }}">
                                                <i class="bx {{ $catBadge[2] }}"></i> {{ $catBadge[0] }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ Str::limit($garantia->descricao, 40) }}</strong>
                                            @if($garantia->observacoes)
                                                <br><small class="text-muted" style="white-space: pre-wrap;">{{ Str::limit($garantia->observacoes, 30) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $garantia->status_cor }}">
                                                <i class="bx bx-shield-{{ $garantia->status === 'ativa' ? 'quarter' : ($garantia->status === 'liberada' ? 'check' : 'x') }}"></i>
                                                {{ $garantia->status_nome }}
                                            </span>
                                            @if($garantia->data_liberacao)
                                                <br><small class="text-muted">Liberada em: {{ $garantia->data_liberacao->format('d/m/Y H:i') }}</small>
                                            @endif
                                            @if($garantia->data_execucao)
                                                <br><small class="text-muted">Executada em: {{ $garantia->data_execucao->format('d/m/Y H:i') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($garantia->valor_avaliado)
                                                <span class="text-success fw-bold">{{ $garantia->valor_formatado }}</span>
                                            @else
                                                <span class="text-muted">Não informado</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($garantia->localizacao)
                                                <i class="bx bx-map text-muted"></i> {{ Str::limit($garantia->localizacao, 25) }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ \App\Support\ClienteUrl::show($garantia->emprestimo->cliente_id, $garantia->emprestimo->operacao_id) }}">
                                                {{ \App\Support\ClienteNomeExibicao::fromEmprestimoMap($garantia->emprestimo, $fichasContatoPorClienteOperacao ?? collect()) }}
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ route('emprestimos.show', $garantia->emprestimo_id) }}">
                                                #{{ $garantia->emprestimo_id }}
                                            </a>
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
                                        </td>
                                        <td class="text-center">
                                            @if($garantia->anexos->count() > 0)
                                                <span class="badge bg-primary" title="{{ $garantia->anexos->count() }} anexos">
                                                    <i class="bx bx-paperclip"></i> {{ $garantia->anexos->count() }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('garantias.show', $garantia->id) }}" 
                                                   class="btn btn-sm btn-info" title="Ver Detalhes da Garantia">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                @if($garantia->anexos->where('tipo', 'imagem')->count() > 0)
                                                    <button type="button" class="btn btn-sm btn-secondary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalGaleria{{ $garantia->id }}"
                                                            title="Ver Fotos">
                                                        <i class="bx bx-image"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="mt-3">
                        {{ $garantias->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-shield-quarter font-size-48 text-muted"></i>
                        <p class="text-muted mt-3">Nenhuma garantia encontrada.</p>
                        <a href="{{ route('emprestimos.create') }}" class="btn btn-warning">
                            <i class="bx bx-plus"></i> Criar Empréstimo Empenho
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Modais de Galeria -->
        @foreach($garantias as $garantia)
            @if($garantia->anexos->where('tipo', 'imagem')->count() > 0)
                <div class="modal fade" id="modalGaleria{{ $garantia->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bx bx-image"></i> Fotos - {{ $garantia->descricao }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-2">
                                    @foreach($garantia->anexos->where('tipo', 'imagem') as $anexo)
                                        <div class="col-md-4 col-6">
                                            <a href="{{ $anexo->url }}" target="_blank">
                                                <img src="{{ $anexo->url }}" 
                                                     alt="{{ $anexo->nome_arquivo }}" 
                                                     class="img-fluid rounded shadow-sm"
                                                     style="height: 150px; width: 100%; object-fit: cover;">
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                <a href="{{ route('emprestimos.show', $garantia->emprestimo_id) }}" class="btn btn-primary">
                                    Ver Empréstimo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

    @endsection
