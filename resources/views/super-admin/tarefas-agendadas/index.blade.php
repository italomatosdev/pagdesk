@extends('layouts.master')
@section('title')
    Tarefas Agendadas (Crons) - Super Admin
@endsection
@section('page-title')
    Tarefas Agendadas (Crons)
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <!-- Cards de Estatísticas -->
        <div class="row mb-3">
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="bx bx-time-five font-size-24 text-primary"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['total'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Total de Execuções</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-success">
                    <div class="card-body text-center">
                        <i class="bx bx-check-circle font-size-24 text-success"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['success'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Sucesso</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-danger">
                    <div class="card-body text-center">
                        <i class="bx bx-x-circle font-size-24 text-danger"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['failed'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Falhas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-info">
                    <div class="card-body text-center">
                        <i class="bx bx-loader-alt font-size-24 text-info"></i>
                        <h4 class="mt-2 mb-0">{{ number_format($stats['running'], 0, ',', '.') }}</h4>
                        <small class="text-muted">Em execução</small>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bx bx-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bx bx-error-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        @endif

        <!-- Executar agora -->
        @if(!empty($allowedTasksToRun))
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary">
                <h5 class="card-title mb-0 text-white">
                    <i class="bx bx-play-circle me-1"></i> Executar tarefa agora
                </h5>
            </div>
            <div class="card-body text-body">
                <form action="{{ route('super-admin.tarefas-agendadas.executar') }}" method="POST" class="form-executar-tarefa">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">Tarefa</label>
                            <select name="task_name" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach($allowedTasksToRun as $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <button type="submit" class="btn btn-primary btn-executar-tarefa">
                                <i class="bx bx-play me-1"></i> Executar agora
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        A execução será registrada no histórico abaixo. Não inicie se a mesma tarefa já estiver em execução.
                    </p>
                </form>
            </div>
        </div>
        @endif

        <script>
            document.querySelectorAll('.form-executar-tarefa').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    if (form.dataset.confirmed === '1') {
                        form.dataset.confirmed = '0';
                        var btn = form.querySelector('.btn-executar-tarefa');
                        if (btn && !btn.disabled) {
                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Executando...';
                        }
                        return;
                    }
                    e.preventDefault();
                    var taskName = form.querySelector('select[name="task_name"]').value;
                    if (!taskName) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atenção!',
                            text: 'Selecione uma tarefa.',
                            confirmButtonColor: '#038edc'
                        });
                        return;
                    }
                    Swal.fire({
                        title: 'Executar tarefa agora?',
                        html: 'Deseja executar a tarefa <strong>' + taskName + '</strong>? A execução será registrada no histórico.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#038edc',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sim, executar!',
                        cancelButtonText: 'Cancelar'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            form.dataset.confirmed = '1';
                            form.submit();
                        }
                    });
                });
            });
        </script>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bx bx-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('super-admin.tarefas-agendadas.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Tarefa</label>
                            <select name="task_name" class="form-select">
                                <option value="">Todas</option>
                                @foreach($taskNames as $name)
                                    <option value="{{ $name }}" {{ request('task_name') === $name ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="running" {{ request('status') === 'running' ? 'selected' : '' }}>Em execução</option>
                                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Sucesso</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Falha</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Filtrar
                            </button>
                            <a href="{{ route('super-admin.tarefas-agendadas.index') }}" class="btn btn-secondary">
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
                    <i class="bx bx-time-five"></i> Histórico de Execuções
                </h5>
                <span class="badge bg-primary">{{ $runs->total() }} registros</span>
            </div>
            <div class="card-body">
                @if($runs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">ID</th>
                                    <th>Tarefa</th>
                                    <th width="140">Início</th>
                                    <th width="140">Término</th>
                                    <th width="100">Status</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($runs as $run)
                                    <tr>
                                        <td>#{{ $run->id }}</td>
                                        <td>
                                            <code>{{ $run->task_name }}</code>
                                        </td>
                                        <td>
                                            <small>
                                                {{ $run->started_at?->format('d/m/Y H:i:s') ?? '-' }}
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                {{ $run->finished_at?->format('d/m/Y H:i:s') ?? ($run->status === 'running' ? '—' : '-') }}
                                            </small>
                                        </td>
                                        <td>
                                            @if($run->status === 'success')
                                                <span class="badge bg-success">Sucesso</span>
                                            @elseif($run->status === 'failed')
                                                <span class="badge bg-danger">Falha</span>
                                            @else
                                                <span class="badge bg-info">Em execução</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($run->message)
                                                <small class="text-muted">{{ Str::limit($run->message, 80) }}</small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="mt-3">
                        {{ $runs->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-time-five font-size-48 text-muted"></i>
                        <p class="text-muted mt-3">Nenhuma execução registrada ainda.</p>
                        <p class="text-muted small">As tarefas agendadas (crons) aparecerão aqui após serem executadas.</p>
                    </div>
                @endif
            </div>
        </div>
    @endsection
