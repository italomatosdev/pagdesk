@extends('layouts.master')
@section('title')
    Prestação de Contas
@endsection
@section('page-title')
    Prestação de Contas
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
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title mb-0">Prestações de Contas</h4>
                            <a href="{{ route('prestacoes.create') }}" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Nova Prestação
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="GET" action="{{ route('prestacoes.index') }}" class="mb-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Operação</label>
                                    <select name="operacao_id" class="form-select">
                                        <option value="">Todas as Operações</option>
                                        @foreach($operacoes as $operacao)
                                            <option value="{{ $operacao->id }}" 
                                                    {{ $operacaoId == $operacao->id ? 'selected' : '' }}>
                                                {{ $operacao->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                                <div class="col-md-3">
                                    <label class="form-label">Usuário</label>
                                    <select name="consultor_id" id="consultor-select-prestacoes" class="form-select">
                                        <option value="">Todos os usuários</option>
                                        @if(isset($consultorSelecionado) && $consultorSelecionado)
                                            @php
                                                $roles = $consultorSelecionado->roles->pluck('name')->map(fn($r) => ucfirst($r))->implode(', ');
                                            @endphp
                                            <option value="{{ $consultorSelecionado->id }}" selected>
                                                {{ $consultorSelecionado->name }} - {{ $consultorSelecionado->email }} ({{ $roles }})
                                            </option>
                                        @endif
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="pendente" {{ ($status ?? '') === 'pendente' ? 'selected' : '' }}>Pendente</option>
                                        <option value="aprovado" {{ ($status ?? '') === 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                        <option value="enviado" {{ ($status ?? '') === 'enviado' ? 'selected' : '' }}>Aguardando Confirmação</option>
                                        <option value="concluido" {{ ($status ?? '') === 'concluido' ? 'selected' : '' }}>Concluído</option>
                                        <option value="rejeitado" {{ ($status ?? '') === 'rejeitado' ? 'selected' : '' }}>Rejeitado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-search"></i> Filtrar
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <a href="{{ route('prestacoes.index') }}" class="btn btn-outline-secondary w-100">
                                        <i class="bx bx-x"></i> Limpar
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Consultor</th>
                                        <th>Operação</th>
                                        <th>Período</th>
                                        <th>Valor Total</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($settlements as $settlement)
                                        <tr>
                                            <td>#{{ $settlement->id }}</td>
                                            <td>{{ $settlement->consultor->name }}</td>
                                            <td>{{ $settlement->operacao->nome }}</td>
                                            <td>
                                                {{ $settlement->data_inicio->format('d/m/Y') }} até 
                                                {{ $settlement->data_fim->format('d/m/Y') }}
                                            </td>
                                            <td>R$ {{ number_format($settlement->valor_total, 2, ',', '.') }}</td>
                                            <td>
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
                                                <span class="badge bg-{{ $badgeClass }}">
                                                    {{ $statusText }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="{{ route('prestacoes.show', $settlement->id) }}" class="btn btn-sm btn-info">
                                                        <i class="bx bx-show"></i> Ver Detalhes
                                                    </a>
                                                    
                                                    @if($settlement->isPendente() && !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])))
                                                        <form action="{{ route('prestacoes.aprovar', $settlement->id) }}" 
                                                              method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="bx bx-check"></i> Aprovar
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="mostrarModalRejeitar({{ $settlement->id }})">
                                                            <i class="bx bx-x"></i> Rejeitar
                                                        </button>
                                                    @endif
                                                    
                                                    @if($settlement->isAprovado() && $settlement->consultor_id == auth()->id())
                                                        <button type="button" class="btn btn-sm btn-primary" 
                                                                onclick="mostrarModalComprovante({{ $settlement->id }})">
                                                            <i class="bx bx-file"></i> Anexar Comprovante
                                                        </button>
                                                    @endif
                                                    
                                                    @if($settlement->isEnviado() && !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])) && ($settlement->criado_por === null || (int) auth()->id() === (int) $settlement->criado_por))
                                                        <form action="{{ route('prestacoes.confirmar-recebimento', $settlement->id) }}" 
                                                              method="POST" class="d-inline" 
                                                              onsubmit="return confirmarRecebimento(this)">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="bx bx-check-circle"></i> Confirmar Recebimento
                                                            </button>
                                                        </form>
                                                    @endif
                                                    
                                                    @if($settlement->comprovante_path)
                                                        <a href="{{ asset('storage/' . $settlement->comprovante_path) }}" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-outline-info">
                                                            <i class="bx bx-file-blank"></i> Comprovante
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">Nenhuma prestação de contas encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-2">
                            {{ $settlements->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Inicializar Select2 para busca de consultores (se o campo existir)
                const consultorSelect = document.getElementById('consultor-select-prestacoes');
                if (consultorSelect) {
                    const consultorJaSelecionado = consultorSelect.options.length > 0 && consultorSelect.options[0].value;
                    
                    const select2Config = {
                        theme: 'bootstrap-5',
                        placeholder: 'Digite o nome ou email do consultor/gestor...',
                        allowClear: true,
                        minimumInputLength: 2,
                        language: {
                            inputTooShort: function() {
                                return 'Digite pelo menos 2 caracteres para buscar';
                            },
                            noResults: function() {
                                return 'Nenhum usuário encontrado';
                            },
                            searching: function() {
                                return 'Buscando...';
                            }
                        },
                        ajax: {
                            url: '{{ route("usuarios.api.buscar") }}',
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term,
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
                    
                    if (consultorJaSelecionado) {
                        select2Config.minimumInputLength = 0;
                    }
                    
                    $('#consultor-select-prestacoes').select2(select2Config);
                }
            });

            function mostrarModalRejeitar(settlementId) {
                Swal.fire({
                    title: 'Rejeitar Prestação de Contas?',
                    html: `
                        <form id="formRejeitar">
                            <div class="mb-3">
                                <label class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="motivo_rejeicao" rows="3" required maxlength="500"></textarea>
                            </div>
                        </form>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, Rejeitar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const motivo = document.getElementById('motivo_rejeicao').value;
                        if (!motivo || motivo.trim().length < 5) {
                            Swal.showValidationMessage('O motivo da rejeição deve ter pelo menos 5 caracteres.');
                            return false;
                        }
                        return motivo;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `/prestacoes/${settlementId}/rejeitar`;
                        
                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = '{{ csrf_token() }}';
                        form.appendChild(csrf);
                        
                        const motivo = document.createElement('input');
                        motivo.type = 'hidden';
                        motivo.name = 'motivo_rejeicao';
                        motivo.value = result.value;
                        form.appendChild(motivo);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            function mostrarModalComprovante(settlementId) {
                Swal.fire({
                    title: 'Anexar Comprovante',
                    html: `
                        <form id="formComprovante" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Comprovante <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="comprovante" 
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="text-muted">PDF ou imagem (máx. 2MB)</small>
                            </div>
                        </form>
                    `,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#038edc',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Anexar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const file = document.getElementById('comprovante').files[0];
                        if (!file) {
                            Swal.showValidationMessage('Selecione um arquivo.');
                            return false;
                        }
                        if (file.size > 2048 * 1024) {
                            Swal.showValidationMessage('O arquivo deve ter no máximo 2MB.');
                            return false;
                        }
                        return file;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('comprovante', result.value);
                        formData.append('_token', '{{ csrf_token() }}');
                        
                        fetch(`/prestacoes/${settlementId}/anexar-comprovante`, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Sucesso!', 'Comprovante anexado com sucesso!', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Erro!', data.message || 'Erro ao anexar comprovante.', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Erro!', 'Erro ao anexar comprovante.', 'error');
                        });
                    }
                });
            }

            function confirmarRecebimento(form) {
                event.preventDefault();
                Swal.fire({
                    title: 'Confirmar Recebimento?',
                    text: 'Isso irá gerar as movimentações de caixa automaticamente. Deseja continuar?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, Confirmar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
                return false;
            }
        </script>
    @endsection