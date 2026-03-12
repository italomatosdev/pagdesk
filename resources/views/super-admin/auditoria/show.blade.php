@extends('layouts.master')
@section('title')
    Log de Auditoria #{{ $log->id }} - Super Admin
@endsection
@section('page-title')
    Log de Auditoria #{{ $log->id }}
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-8">
                <!-- Informações Gerais -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Informações do Log</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>ID:</strong> #{{ $log->id }}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Data/Hora:</strong> 
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                                <small class="text-muted">({{ $log->created_at->diffForHumans() }})</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Usuário:</strong>
                                @if($log->user)
                                    <a href="{{ route('super-admin.usuarios.show', $log->user_id) }}">
                                        {{ $log->user->name }}
                                    </a>
                                    <br><small class="text-muted">{{ $log->user->email }}</small>
                                @else
                                    <span class="text-muted">Sistema</span>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ação:</strong>
                                <span class="badge bg-info">{{ $log->action }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Modelo Afetado:</strong>
                                @if($log->model_type && $log->model_id)
                                    <span class="badge bg-secondary">{{ class_basename($log->model_type) }}</span> 
                                    #{{ $log->model_id }}
                                    @if($modelo)
                                        <br><small class="text-muted">Modelo ainda existe no sistema</small>
                                    @else
                                        <br><small class="text-warning">Modelo não encontrado (pode ter sido excluído)</small>
                                    @endif
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>IP Address:</strong> 
                                <code>{{ $log->ip_address ?? 'N/A' }}</code>
                            </div>
                            @if($log->user_agent)
                                <div class="col-12 mb-3">
                                    <strong>User Agent:</strong>
                                    <br><small class="text-muted">{{ $log->user_agent }}</small>
                                </div>
                            @endif
                            @if($log->observacoes)
                                <div class="col-12 mb-3">
                                    <strong>Observações:</strong>
                                    <div class="alert alert-info mb-0 mt-2">
                                        {{ $log->observacoes }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Comparação Old vs New -->
                @if($log->old_values || $log->new_values)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Alterações Realizadas</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @if($log->old_values)
                                    <div class="col-md-6">
                                        <h5 class="text-danger mb-3">
                                            <i class="bx bx-x-circle"></i> Valores Anteriores
                                        </h5>
                                        <div class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;">
                                            <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    </div>
                                @endif
                                @if($log->new_values)
                                    <div class="col-md-6">
                                        <h5 class="text-success mb-3">
                                            <i class="bx bx-check-circle"></i> Valores Novos
                                        </h5>
                                        <div class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;">
                                            <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($log->old_values && $log->new_values)
                                <hr>
                                <h5 class="mb-3">Diferenças Destacadas</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Campo</th>
                                                <th>Valor Anterior</th>
                                                <th>Valor Novo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $allKeys = array_unique(array_merge(array_keys($log->old_values), array_keys($log->new_values)));
                                            @endphp
                                            @foreach($allKeys as $key)
                                                @php
                                                    $oldValue = $log->old_values[$key] ?? null;
                                                    $newValue = $log->new_values[$key] ?? null;
                                                    $changed = $oldValue !== $newValue;
                                                @endphp
                                                <tr class="{{ $changed ? 'table-warning' : '' }}">
                                                    <td><strong>{{ $key }}</strong></td>
                                                    <td>
                                                        @if($changed)
                                                            <span class="text-danger">
                                                                {{ is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : ($oldValue ?? 'null') }}
                                                            </span>
                                                        @else
                                                            {{ is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : ($oldValue ?? 'null') }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($changed)
                                                            <span class="text-success">
                                                                {{ is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : ($newValue ?? 'null') }}
                                                            </span>
                                                        @else
                                                            {{ is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : ($newValue ?? 'null') }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Link para o Modelo (se existir) -->
                @if($modelo)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Modelo Relacionado</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <i class="bx bx-info-circle"></i> 
                                O modelo <strong>{{ class_basename($log->model_type) }} #{{ $log->model_id }}</strong> ainda existe no sistema.
                                @php
                                    $routeName = match(class_basename($log->model_type)) {
                                        'Emprestimo' => 'emprestimos.show',
                                        'Cliente' => 'clientes.show',
                                        'Operacao' => 'super-admin.operacoes.show',
                                        'Empresa' => 'super-admin.empresas.show',
                                        default => null
                                    };
                                @endphp
                                @if($routeName)
                                    <a href="{{ route($routeName, $log->model_id) }}" class="btn btn-sm btn-primary ms-2">
                                        <i class="bx bx-show"></i> Ver Detalhes
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ações</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('super-admin.auditoria.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Voltar para Lista
                            </a>
                            @if($log->user)
                                <a href="{{ route('super-admin.usuarios.show', $log->user_id) }}" class="btn btn-info">
                                    <i class="bx bx-user"></i> Ver Usuário
                                </a>
                            @endif
                            @if($log->model_type && $log->model_id)
                                <form method="GET" action="{{ route('super-admin.auditoria.index') }}" class="d-inline">
                                    <input type="hidden" name="model_type" value="{{ $log->model_type }}">
                                    <input type="hidden" name="model_id" value="{{ $log->model_id }}">
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="bx bx-filter"></i> Ver Todos os Logs Deste Modelo
                                    </button>
                                </form>
                            @endif
                            @if($log->user_id)
                                <form method="GET" action="{{ route('super-admin.auditoria.index') }}" class="d-inline">
                                    <input type="hidden" name="user_id" value="{{ $log->user_id }}">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bx bx-user"></i> Ver Todos os Logs Deste Usuário
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Informações Técnicas -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informações Técnicas</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Model Type:</strong><br>
                            <code class="small">{{ $log->model_type ?? 'N/A' }}</code>
                        </div>
                        <div class="mb-2">
                            <strong>Model ID:</strong><br>
                            <code>{{ $log->model_id ?? 'N/A' }}</code>
                        </div>
                        <div>
                            <strong>Log ID:</strong><br>
                            <code>#{{ $log->id }}</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
