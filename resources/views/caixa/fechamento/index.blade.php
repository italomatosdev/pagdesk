@extends('layouts.master')
@section('title')
    Fechamento de Caixa
@endsection
@section('page-title')
    Fechamento de Caixa
@endsection
@section('body')
    <body>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif

            <!-- Filtros no topo: operação e demais critérios aplicam à página inteira -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-filter-alt"></i> Filtros
                    </h5>
                    <small class="text-muted">A operação selecionada vale para seu saldo, para a lista de usuários e para os fechamentos abaixo.</small>
                </div>
                <div class="card-body pb-2">
                    <form method="GET" action="{{ route('fechamento-caixa.index') }}" class="mb-0">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Operação</label>
                                <select name="operacao_id" id="fechamento-operacao-select" class="form-select">
                                    @foreach($operacoes as $op)
                                        <option value="{{ $op->id }}" {{ $operacaoId == $op->id ? 'selected' : '' }}>
                                            {{ $op->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                            <div class="col-md-3">
                                <label class="form-label">Usuário</label>
                                <select name="consultor_id" id="fechamento-usuario-filtro-select" class="form-select" data-select2-placeholder="Todos os usuários">
                                    <option value="">Todos</option>
                                    @foreach($usuariosFiltro ?? [] as $u)
                                        <option value="{{ $u->id }}" {{ (int) ($consultorId ?? 0) === (int) $u->id ? 'selected' : '' }}>
                                            {{ $u->id === auth()->id() ? $u->name . ' (Você)' : $u->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="aprovado" {{ ($status ?? '') === 'aprovado' ? 'selected' : '' }}>Aguardando Envio</option>
                                    <option value="enviado" {{ ($status ?? '') === 'enviado' ? 'selected' : '' }}>Aguardando Confirmação</option>
                                    <option value="concluido" {{ ($status ?? '') === 'concluido' ? 'selected' : '' }}>Concluído</option>
                                    <option value="rejeitado" {{ ($status ?? '') === 'rejeitado' ? 'selected' : '' }}>Rejeitado</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data início</label>
                                <input type="date" name="data_inicio" class="form-control" value="{{ $dataInicio ?? '' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Data fim</label>
                                <input type="date" name="data_fim" class="form-control" value="{{ $dataFim ?? '' }}">
                            </div>
                        </div>
                        <div class="row g-3 align-items-end mt-1">
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bx bx-search"></i> Filtrar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('fechamento-caixa.index', ['operacao_id' => $operacaoId]) }}" class="btn btn-outline-secondary w-100">
                                    Limpar filtros da lista
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card: Meu Saldo / Fechar ou apenas extrato -->
            @if(round(abs((float) $meuSaldo), 2) > 0)
            @php $meuSaldoRounded = round((float) $meuSaldo, 2); @endphp
            <div class="card mb-3 border-{{ $meuSaldoRounded > 0 ? 'primary' : 'danger' }}">
                <div class="card-header {{ $meuSaldoRounded > 0 ? 'bg-primary' : 'bg-danger' }} text-white">
                    <h5 class="card-title mb-0 text-white">
                        <i class="bx bx-wallet"></i> Meu Caixa na Operação: {{ $operacaoSelecionada?->nome ?? 'Selecione uma operação' }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <p class="text-muted mb-1">Meu saldo atual</p>
                            <h3 class="mb-0 {{ $meuSaldoRounded > 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($meuSaldo, 2, ',', '.') }}</h3>
                            @if($meuSaldoRounded < 0)
                                <small class="text-muted">Saldo negativo: use apenas para conferir o extrato; o fechamento padrão não está disponível.</small>
                            @endif
                        </div>
                        @if($meuSaldoRounded > 0)
                            @php
                                $podeFecharProprioCaixa = auth()->user()->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador']);
                            @endphp
                            <a href="{{ route('fechamento-caixa.conferir', ['usuario_id' => auth()->id(), 'operacao_id' => $operacaoId]) }}" class="btn btn-primary btn-lg">
                                <i class="bx bx-search-alt"></i> {{ $podeFecharProprioCaixa ? 'Conferir e fechar meu caixa' : 'Conferir meu caixa' }}
                            </a>
                        @else
                            <a href="{{ route('fechamento-caixa.conferir', ['usuario_id' => auth()->id(), 'operacao_id' => $operacaoId]) }}" class="btn btn-outline-danger btn-lg">
                                <i class="bx bx-list-ul"></i> Ver extrato
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @php
                $outrosComSaldo = ($usuariosComSaldo ?? collect())->filter(fn ($u) => (int) $u->id !== (int) auth()->id());
            @endphp
            <!-- Card: Saldos de outros usuários (apenas gestor/admin) -->
            @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])) && $outrosComSaldo->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-group"></i> Saldos por usuário (conferência)
                    </h5>
                    <small class="text-dark">Saldo positivo: pode fechar. Saldo negativo: apenas extrato — não é possível fechar pelo fluxo padrão.</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuário</th>
                                    <th>Função</th>
                                    <th class="text-end">Saldo</th>
                                    <th class="text-center" width="180">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($outrosComSaldo as $usuario)
                                    <tr>
                                        <td>
                                            <strong>{{ $usuario->name }}</strong>
                                            <br><small class="text-muted">{{ $usuario->email }}</small>
                                        </td>
                                        <td>
                                            @foreach($usuario->roles as $role)
                                                <span class="badge bg-{{ $role->name === 'administrador' ? 'danger' : ($role->name === 'gestor' ? 'warning' : 'primary') }}">
                                                    {{ ucfirst($role->name) }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bold {{ round((float) $usuario->saldo_operacao, 2) >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($usuario->saldo_operacao, 2, ',', '.') }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if(round((float) $usuario->saldo_operacao, 2) > 0)
                                                <a href="{{ route('fechamento-caixa.conferir', ['usuario_id' => $usuario->id, 'operacao_id' => $operacaoId]) }}" class="btn btn-danger btn-sm">
                                                    <i class="bx bx-search-alt"></i> Conferir e fechar
                                                </a>
                                            @else
                                                <a href="{{ route('fechamento-caixa.conferir', ['usuario_id' => $usuario->id, 'operacao_id' => $operacaoId]) }}" class="btn btn-outline-secondary btn-sm">
                                                    <i class="bx bx-list-ul"></i> Ver extrato
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Card: Lista de Fechamentos -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-list-ul"></i> Fechamentos de Caixa
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Tabela -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Iniciado por</th>
                                    <th>Período</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fechamentos as $f)
                                    <tr>
                                        <td>#{{ $f->id }}</td>
                                        <td>{{ $f->consultor?->name ?? '-' }}</td>
                                        <td>
                                            @if($f->isFechamentoPorGestor())
                                                <span class="badge bg-warning text-dark">{{ $f->criador?->name ?? 'Gestor' }}</span>
                                            @else
                                                <span class="badge bg-info">Próprio</span>
                                            @endif
                                        </td>
                                        <td>{{ $f->data_inicio->format('d/m/Y') }} - {{ $f->data_fim->format('d/m/Y') }}</td>
                                        <td class="text-end fw-bold">R$ {{ number_format($f->valor_total, 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @php
                                                $badgeClass = match($f->status) {
                                                    'concluido' => 'success',
                                                    'enviado' => 'info',
                                                    'aprovado' => 'warning',
                                                    'pendente' => 'secondary',
                                                    'rejeitado' => 'danger',
                                                    default => 'secondary'
                                                };
                                                $statusText = match($f->status) {
                                                    'concluido' => 'Concluído',
                                                    'enviado' => 'Aguardando Confirmação',
                                                    'aprovado' => 'Aguardando Envio',
                                                    'pendente' => 'Pendente',
                                                    'rejeitado' => 'Rejeitado',
                                                    default => ucfirst($f->status)
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $badgeClass }}">{{ $statusText }}</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <a href="{{ route('fechamento-caixa.show', $f->id) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                
                                                @if($f->status === 'aprovado' && $f->consultor_id === auth()->id())
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="mostrarModalComprovante({{ $f->id }})">
                                                        <i class="bx bx-upload"></i> Enviar
                                                    </button>
                                                @endif
                                                
                                                @if($f->status === 'enviado' && !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])))
                                                    <form action="{{ route('fechamento-caixa.confirmar', $f->id) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirmarRecebimento(event)">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="bx bx-check"></i> Confirmar
                                                        </button>
                                                    </form>
                                                @endif
                                                
                                                @if($f->comprovante_path)
                                                    <a href="{{ asset('storage/' . $f->comprovante_path) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="bx bx-file"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Nenhum fechamento encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-3">
                        {{ $fechamentos->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function() {
    const opFe = document.getElementById('fechamento-operacao-select');
    if (opFe) {
        opFe.addEventListener('change', function() {
            const c = document.getElementById('fechamento-usuario-filtro-select');
            if (c && typeof $ !== 'undefined' && $.fn.select2 && $(c).data('select2')) {
                $(c).select2('destroy');
            }
            if (c) {
                c.value = '';
            }
            this.form.submit();
        });
    }

    const usuarioFiltroEl = document.getElementById('fechamento-usuario-filtro-select');
    if (usuarioFiltroEl && typeof $ !== 'undefined' && $.fn.select2) {
        var ph = usuarioFiltroEl.getAttribute('data-select2-placeholder') || 'Todos os usuários';
        $(usuarioFiltroEl).select2({ theme: 'bootstrap-5', placeholder: ph, allowClear: true });
    }
});

function mostrarModalComprovante(fechamentoId) {
    Swal.fire({
        title: 'Anexar Comprovante',
        html: `
            <div class="mb-3 text-start">
                <label class="form-label">Comprovante de envio <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="comprovante" accept=".pdf,.jpg,.jpeg,.png" required>
                <small class="text-muted">PDF ou imagem (máx. 2MB)</small>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
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
            
            Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch(`/fechamento-caixa/${fechamentoId}/anexar-comprovante`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Comprovante enviado!', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao enviar.', 'error');
                }
            })
            .catch(() => Swal.fire('Erro!', 'Erro ao enviar.', 'error'));
        }
    });
}

function confirmarRecebimento(event) {
    event.preventDefault();
    const form = event.target;
    Swal.fire({
        title: 'Confirmar Recebimento?',
        text: 'Isso irá gerar as movimentações de caixa.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
    return false;
}
</script>
@endsection
