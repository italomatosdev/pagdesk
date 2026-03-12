@extends('layouts.master')
@section('title')
    Dashboard - Super Admin
@endsection
@section('page-title')
    Dashboard - Super Admin
@endsection
@section('body')
    <body>
    @endsection
    @section('content')
        <!-- Cards de Métricas Principais -->
        <div class="row">
            <!-- Total de Empresas -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="font-size-15">Total de Empresas</h6>
                                <h4 class="mt-3 pt-1 mb-0 font-size-22">{{ number_format($totalEmpresas, 0, ',', '.') }}</h4>
                                <small class="text-muted">
                                    <span class="badge bg-success">{{ $empresasAtivas }} Ativas</span>
                                    <span class="badge bg-warning ms-1">{{ $empresasSuspensas }} Suspensas</span>
                                </small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-primary-subtle">
                                        <i class="bx bx-building font-size-24 mb-0 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total de Usuários -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Total de Usuários</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($totalUsuarios, 0, ',', '.') }}</h4>
                                <small class="text-muted">Média: {{ $mediaUsuariosPorEmpresa }}/empresa</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-user font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total de Clientes -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Total de Clientes</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($totalClientes, 0, ',', '.') }}</h4>
                                <small class="text-muted">Média: {{ $mediaClientesPorEmpresa }}/empresa</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-success-subtle">
                                        <i class="bx bx-group font-size-24 mb-0 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empréstimos Ativos -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Empréstimos Ativos</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($emprestimosAtivos, 0, ',', '.') }}</h4>
                                <small class="text-muted">Total: {{ number_format($totalEmprestimos, 0, ',', '.') }}</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-money font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <div class="row">
            <!-- Valor Total Emprestado -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Valor Total Emprestado</h6>
                                <h4 class="mt-3 mb-0 font-size-22">R$ {{ number_format($valorTotalEmprestado, 2, ',', '.') }}</h4>
                                <small class="text-muted">Média: R$ {{ number_format($valorMedioEmprestimo, 2, ',', '.') }}</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-danger-subtle">
                                        <i class="bx bx-dollar font-size-24 mb-0 text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operações Ativas -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Operações Ativas</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($totalOperacoes, 0, ',', '.') }}</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-secondary-subtle">
                                        <i class="bx bx-cog font-size-24 mb-0 text-secondary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taxa de Retenção -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa de Retenção</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($taxaRetencao, 2, ',', '.') }}%</h4>
                                <small class="text-muted">{{ $empresasAtivas }}/{{ $totalEmpresas }} empresas ativas</small>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-info-subtle">
                                        <i class="bx bx-trending-up font-size-24 mb-0 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Taxa de Inadimplência -->
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0 font-size-15">Taxa de Inadimplência</h6>
                                <h4 class="mt-3 mb-0 font-size-22">{{ number_format($taxaInadimplencia, 2, ',', '.') }}%</h4>
                            </div>
                            <div class="">
                                <div class="avatar">
                                    <div class="avatar-title rounded bg-warning-subtle">
                                        <i class="bx bx-error-circle font-size-24 mb-0 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END ROW -->

        <!-- Gráficos e Tabelas -->
        <div class="row">
            <!-- Status das Empresas -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Status das Empresas</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="statusEmpresasChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Distribuição por Plano -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Distribuição por Plano</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="distribuicaoPlanoChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crescimento -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Crescimento (Últimos 6 Meses)</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="crescimentoChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Empresas e Alertas -->
        <div class="row">
            <!-- Top 10 Empresas -->
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Top 10 Empresas por Volume</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Status</th>
                                        <th>Operações</th>
                                        <th>Usuários</th>
                                        <th>Clientes</th>
                                        <th>Empréstimos</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topEmpresas as $empresa)
                                        <tr>
                                            <td>
                                                <strong>{{ $empresa->nome }}</strong>
                                                @if($empresa->plano)
                                                    <br><small class="text-muted">{{ ucfirst($empresa->plano) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $empresa->status == 'ativa' ? 'success' : ($empresa->status == 'suspensa' ? 'warning' : 'danger') }}">
                                                    {{ ucfirst($empresa->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $empresa->operacoes_count }}</td>
                                            <td>{{ $empresa->usuarios_count }}</td>
                                            <td>{{ $empresa->clientes_count }}</td>
                                            <td>{{ $empresa->emprestimos_count }}</td>
                                            <td>
                                                <a href="{{ route('super-admin.empresas.show', $empresa->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">Nenhuma empresa encontrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas e Notificações -->
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Alertas e Notificações</h4>
                    </div>
                    <div class="card-body">
                        <!-- Empresas Expirando -->
                        @if($empresasExpirando->count() > 0)
                            <div class="alert alert-warning mb-3">
                                <h6 class="alert-heading">
                                    <i class="bx bx-time"></i> Empresas Expirando (30 dias)
                                </h6>
                                <ul class="mb-0 ps-3">
                                    @foreach($empresasExpirando as $empresa)
                                        <li>
                                            <strong>{{ $empresa->nome }}</strong>
                                            <br>
                                            <small>Expira em: {{ $empresa->data_expiracao->format('d/m/Y') }}</small>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Empresas Sem Atividade -->
                        @if($empresasSemAtividade->count() > 0)
                            <div class="alert alert-info mb-3">
                                <h6 class="alert-heading">
                                    <i class="bx bx-info-circle"></i> Empresas Sem Atividade (30 dias)
                                </h6>
                                <ul class="mb-0 ps-3">
                                    @foreach($empresasSemAtividade->take(5) as $empresa)
                                        <li>
                                            <strong>{{ $empresa->nome }}</strong>
                                        </li>
                                    @endforeach
                                    @if($empresasSemAtividade->count() > 5)
                                        <li><small>... e mais {{ $empresasSemAtividade->count() - 5 }} empresa(s)</small></li>
                                    @endif
                                </ul>
                            </div>
                        @endif

                        @if($empresasExpirando->count() == 0 && $empresasSemAtividade->count() == 0)
                            <div class="text-center py-3">
                                <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">Nenhum alerta no momento</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Empresas Recentes -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Empresas Recentes</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            @foreach($empresasRecentes as $empresa)
                                <li class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>{{ $empresa->nome }}</strong>
                                            <br>
                                            <small class="text-muted">
                                                Criada em: {{ $empresa->created_at->format('d/m/Y') }}
                                            </small>
                                        </div>
                                        <a href="{{ route('super-admin.empresas.show', $empresa->id) }}" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bx bx-show"></i>
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usuários por Papel -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Distribuição de Usuários por Papel</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($usuariosPorPapel as $papel => $total)
                                <div class="col-md-3 mb-3">
                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                        <div class="flex-shrink-0">
                                            <i class="bx bx-user font-size-24 text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <strong>{{ ucfirst($papel) }}</strong>
                                            <br>
                                            <span class="badge bg-primary">{{ $total }} usuário(s)</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Gráfico de Status das Empresas
            const statusCtx = document.getElementById('statusEmpresasChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: ['Ativas', 'Suspensas', 'Canceladas'],
                    datasets: [{
                        data: [{{ $empresasAtivas }}, {{ $empresasSuspensas }}, {{ $empresasCanceladas }}],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Gráfico de Distribuição por Plano
            const planoCtx = document.getElementById('distribuicaoPlanoChart').getContext('2d');
            const planos = @json(array_keys($distribuicaoPorPlano->toArray()));
            const planosData = @json(array_values($distribuicaoPorPlano->toArray()));
            
            new Chart(planoCtx, {
                type: 'bar',
                data: {
                    labels: planos.map(p => p ? p.charAt(0).toUpperCase() + p.slice(1) : 'Sem Plano'),
                    datasets: [{
                        label: 'Empresas',
                        data: planosData,
                        backgroundColor: '#038edc'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Gráfico de Crescimento
            const crescimentoCtx = document.getElementById('crescimentoChart').getContext('2d');
            const meses = @json(array_keys($crescimentoEmpresas));
            
            new Chart(crescimentoCtx, {
                type: 'line',
                data: {
                    labels: meses,
                    datasets: [
                        {
                            label: 'Empresas',
                            data: @json(array_values($crescimentoEmpresas)),
                            borderColor: '#038edc',
                            backgroundColor: 'rgba(3, 142, 220, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Usuários',
                            data: @json(array_values($crescimentoUsuarios)),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Clientes',
                            data: @json(array_values($crescimentoClientes)),
                            borderColor: '#ffc107',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        </script>
    @endsection
