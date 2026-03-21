@extends('layouts.master')
@section('title')
    Conferência da Prestação de Contas
@endsection
@section('page-title')
    Conferência da Prestação de Contas
@endsection
@section('body')

    <body>
    @endsection
    @section('content')
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Card de Resumo -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0 text-white">
                            <i class="bx bx-check-circle me-2"></i>Resumo da Prestação de Contas
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Operação</p>
                                    <h5 class="mb-0">{{ $operacao->nome }}</h5>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Período</p>
                                    <h5 class="mb-0">
                                        {{ \Carbon\Carbon::parse($validated['data_inicio'])->format('d/m/Y') }} até
                                        {{ \Carbon\Carbon::parse($validated['data_fim'])->format('d/m/Y') }}
                                    </h5>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Movimentações</p>
                                    <h5 class="mb-0">{{ $quantidadeMovimentacoes }}</h5>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1 small">Saldo Inicial</p>
                                    <h5 class="mb-0 {{ $saldoInicial >= 0 ? 'text-success' : 'text-danger' }}">
                                        R$ {{ number_format($saldoInicial, 2, ',', '.') }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4 mb-3 d-flex">
                                <div class="card border-success w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Total de Entradas</p>
                                        <h4 class="mb-0 text-success flex-grow-1">
                                            <i class="bx bx-trending-up me-2"></i>R$ {{ number_format($totalEntradas, 2, ',', '.') }}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 d-flex">
                                <div class="card border-danger w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Total de Saídas</p>
                                        <h4 class="mb-0 text-danger flex-grow-1">
                                            <i class="bx bx-trending-down me-2"></i>R$ {{ number_format($totalSaidas, 2, ',', '.') }}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 d-flex">
                                <div class="card border-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }} w-100">
                                    <div class="card-body d-flex flex-column">
                                        <p class="text-muted mb-1 small">Saldo Final (Valor da Prestação)</p>
                                        <h4 class="mb-0 text-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }} flex-grow-1">
                                            <i class="bx bx-dollar-circle me-2"></i>R$ {{ number_format($saldoFinal, 2, ',', '.') }}
                                        </h4>
                                        <small class="text-muted mt-auto">Saldo Inicial + Entradas - Saídas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($saldoFinal < 0)
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">
                                        <i class="bx bx-error-circle me-2"></i>
                                        <strong>Atenção!</strong> O saldo final é negativo (R$ {{ number_format(abs($saldoFinal), 2, ',', '.') }}). 
                                        Isso significa que você está devendo dinheiro. Verifique as movimentações antes de criar a prestação.
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Card de Observações (se houver) -->
                @if(!empty($validated['observacoes']))
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Observações</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $validated['observacoes'] }}</p>
                        </div>
                    </div>
                @endif

                <!-- Card de Movimentações -->
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-list-ul me-2"></i>Movimentações Incluídas
                            </h5>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse"
                                data-bs-target="#movimentacoesCollapse" aria-expanded="true"
                                aria-controls="movimentacoesCollapse">
                                <i class="bx bx-chevron-down"></i> Ver Detalhes
                            </button>
                        </div>
                    </div>
                    <div class="collapse show" id="movimentacoesCollapse">
                        <div class="card-body">
                            @if($movimentacoes->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Tipo</th>
                                                <th>Descrição</th>
                                                <th>Origem</th>
                                                <th class="text-end">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($movimentacoes as $movimentacao)
                                                <tr>
                                                    <td>{{ $movimentacao->data_movimentacao->format('d/m/Y') }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $movimentacao->isEntrada() ? 'success' : 'danger' }}">
                                                            {{ $movimentacao->isEntrada() ? 'Entrada' : 'Saída' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        {{ $movimentacao->descricao }}
                                                        @if($movimentacao->pagamento && $movimentacao->pagamento->parcela && $movimentacao->pagamento->parcela->emprestimo)
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="bx bx-user"></i>
                                                                {{ \App\Support\ClienteNomeExibicao::fromParcelaMap($movimentacao->pagamento->parcela, $fichasContatoPorClienteOperacao ?? collect()) }}
                                                            </small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-{{ $movimentacao->isManual() ? 'info' : 'secondary' }}">
                                                            {{ ucfirst($movimentacao->origem) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end {{ $movimentacao->isEntrada() ? 'text-success' : 'text-danger' }}">
                                                        <strong>
                                                            {{ $movimentacao->isEntrada() ? '+' : '-' }} 
                                                            R$ {{ number_format($movimentacao->valor, 2, ',', '.') }}
                                                        </strong>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <th colspan="4" class="text-end">Saldo Inicial:</th>
                                                <th class="text-end {{ $saldoInicial >= 0 ? 'text-success' : 'text-danger' }}">
                                                    R$ {{ number_format($saldoInicial, 2, ',', '.') }}
                                                </th>
                                            </tr>
                                            <tr class="table-success">
                                                <th colspan="4" class="text-end">Total Entradas:</th>
                                                <th class="text-end text-success">
                                                    + R$ {{ number_format($totalEntradas, 2, ',', '.') }}
                                                </th>
                                            </tr>
                                            <tr class="table-danger">
                                                <th colspan="4" class="text-end">Total Saídas:</th>
                                                <th class="text-end text-danger">
                                                    - R$ {{ number_format($totalSaidas, 2, ',', '.') }}
                                                </th>
                                            </tr>
                                            <tr class="table-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }}">
                                                <th colspan="4" class="text-end"><strong>Saldo Final (Valor da Prestação):</strong></th>
                                                <th class="text-end text-{{ $saldoFinal >= 0 ? 'primary' : 'warning' }}">
                                                    <strong>R$ {{ number_format($saldoFinal, 2, ',', '.') }}</strong>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Atenção:</strong> Nenhuma movimentação foi encontrada no período
                                    selecionado. Verifique se as datas estão corretas.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Formulário de Confirmação -->
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('prestacoes.store') }}" method="POST" id="formConfirmarPrestacao">
                            @csrf
                            <input type="hidden" name="operacao_id" value="{{ $validated['operacao_id'] }}">
                            <input type="hidden" name="data_inicio" value="{{ $validated['data_inicio'] }}">
                            <input type="hidden" name="data_fim" value="{{ $validated['data_fim'] }}">
                            @if(!empty($validated['observacoes']))
                                <input type="hidden" name="observacoes" value="{{ $validated['observacoes'] }}">
                            @endif

                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Confirme os dados acima.</strong> Após confirmar, a prestação de contas será criada
                                e enviada para aprovação do gestor.
                            </div>

                            <div class="d-flex justify-content-between gap-2">
                                <a href="{{ route('prestacoes.create') }}" class="btn btn-secondary">
                                    <i class="bx bx-arrow-back"></i> Voltar e Ajustar
                                </a>
                                <div>
                                    <a href="{{ route('prestacoes.index') }}" class="btn btn-outline-secondary me-2">
                                        <i class="bx bx-x"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="btnConfirmar">
                                        <i class="bx bx-check-circle"></i> Confirmar e Criar Prestação
                                    </button>
                                </div>
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
                const form = document.getElementById('formConfirmarPrestacao');
                const btnConfirmar = document.getElementById('btnConfirmar');

                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();

                        const operacao = '{{ $operacao->nome }}';
                        const periodo = '{{ \Carbon\Carbon::parse($validated["data_inicio"])->format("d/m/Y") }} até {{ \Carbon\Carbon::parse($validated["data_fim"])->format("d/m/Y") }}';
                        const saldoInicial = parseFloat({{ $saldoInicial }}).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        const totalEntradas = parseFloat({{ $totalEntradas }}).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        const totalSaidas = parseFloat({{ $totalSaidas }}).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        const saldoFinal = parseFloat({{ $saldoFinal }}).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        const quantidadeMovimentacoes = {{ $quantidadeMovimentacoes }};
                        const saldoFinalNegativo = {{ $saldoFinal < 0 ? 'true' : 'false' }};

                        let htmlContent = `
                            <div class="text-start">
                                <p><strong>Operação:</strong> ${operacao}</p>
                                <p><strong>Período:</strong> ${periodo}</p>
                                <p><strong>Movimentações:</strong> ${quantidadeMovimentacoes}</p>
                                <hr>
                                <p><strong>Saldo Inicial:</strong> ${saldoInicial}</p>
                                <p><strong>Total Entradas:</strong> ${totalEntradas}</p>
                                <p><strong>Total Saídas:</strong> ${totalSaidas}</p>
                                <p><strong>Saldo Final (Valor da Prestação):</strong> <strong class="${saldoFinalNegativo ? 'text-warning' : 'text-primary'}">${saldoFinal}</strong></p>
                            </div>
                        `;

                        if (saldoFinalNegativo) {
                            htmlContent += `<p class="text-warning mt-3"><strong>Atenção:</strong> O saldo final é negativo. Você está devendo dinheiro.</p>`;
                        } else {
                            htmlContent += `<p class="text-muted mt-3">A prestação será criada e enviada para aprovação do gestor.</p>`;
                        }

                        Swal.fire({
                            title: 'Confirmar Criação da Prestação?',
                            html: htmlContent,
                            icon: saldoFinalNegativo ? 'warning' : 'question',
                            showCancelButton: true,
                            confirmButtonColor: saldoFinalNegativo ? '#ffc107' : '#038edc',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="bx bx-check"></i> Sim, Confirmar!',
                            cancelButtonText: '<i class="bx bx-x"></i> Cancelar',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Desabilitar botão para evitar duplo submit
                                btnConfirmar.disabled = true;
                                btnConfirmar.innerHTML = '<i class="bx bx-loader bx-spin"></i> Criando...';
                                form.submit();
                            }
                        });
                    });
                }
            });
        </script>
    @endsection
