@php
    $badgeClass = match($item['tipo']) {
        'emprestimo' => 'primary',
        'prestacao' => 'info',
        'liberacao' => 'success',
        'liberacao_pagar' => 'warning',
        'cobranca' => 'warning',
        'prestacao_comprovante' => 'info',
        'prestacao_recebimento' => 'secondary',
        'parcela_atrasada' => 'danger',
        default => 'secondary'
    };

    $icon = match($item['tipo']) {
        'emprestimo' => 'bx-money',
        'prestacao' => 'bx-file-text',
        'liberacao' => 'bx-transfer',
        'liberacao_pagar' => 'bx-money',
        'cobranca' => 'bx-calendar-check',
        'prestacao_comprovante' => 'bx-upload',
        'prestacao_recebimento' => 'bx-check-circle',
        'parcela_atrasada' => 'bx-error-circle',
        default => 'bx-info-circle'
    };
@endphp

<div class="card mb-2 kanban-card" data-tipo="{{ $item['tipo'] }}" data-id="{{ $item['id'] }}" style="cursor: move;">
    <div class="card-body p-2">
        <div class="d-flex justify-content-between align-items-start mb-1">
            <h6 class="card-title mb-0 small fw-bold">
                <i class="bx {{ $icon }}"></i> {{ $item['titulo'] }}
            </h6>
            <span class="badge bg-{{ $badgeClass }}">{{ ucfirst($item['tipo']) }}</span>
        </div>

        @if(isset($item['cliente']))
            <p class="mb-1 small">
                <i class="bx bx-user"></i> <strong>Cliente:</strong> {{ $item['cliente'] }}
            </p>
        @endif

        @if(isset($item['consultor']))
            <p class="mb-1 small">
                <i class="bx bx-user-circle"></i> <strong>Consultor:</strong> {{ $item['consultor'] }}
            </p>
        @endif

        @if(isset($item['valor']))
            <p class="mb-1 small">
                <i class="bx bx-dollar"></i> <strong>Valor:</strong> R$ {{ number_format($item['valor'], 2, ',', '.') }}
            </p>
        @endif

        @if(isset($item['operacao']))
            <p class="mb-1 small">
                <i class="bx bx-building"></i> <strong>Operação:</strong> {{ $item['operacao'] }}
            </p>
        @endif

        @if(isset($item['periodo']))
            <p class="mb-1 small">
                <i class="bx bx-calendar"></i> <strong>Período:</strong> {{ $item['periodo'] }}
            </p>
        @endif

        @if(isset($item['dias_atraso']))
            <p class="mb-1 small text-danger">
                <i class="bx bx-time"></i> <strong>Atraso:</strong> {{ $item['dias_atraso'] }} dias
            </p>
        @endif

        @if(isset($item['dias_pendente']) && isset($item['data']))
            @php
                $data = \Carbon\Carbon::parse($item['data']);
                $agora = now();
                $segundos = (int) $data->diffInSeconds($agora);
                $minutos = (int) $data->diffInMinutes($agora);
                $horas = (int) $data->diffInHours($agora);
                $dias = (int) $data->diffInDays($agora);
                
                if ($segundos < 60) {
                    $tempoTexto = $segundos <= 1 ? 'Há 1 segundo' : "Há {$segundos} segundos";
                } elseif ($minutos < 60) {
                    $tempoTexto = $minutos == 1 ? 'Há 1 minuto' : "Há {$minutos} minutos";
                } elseif ($horas < 24) {
                    $tempoTexto = $horas == 1 ? 'Há 1 hora' : "Há {$horas} horas";
                } elseif ($dias == 1) {
                    $tempoTexto = 'Ontem';
                } elseif ($dias < 7) {
                    $tempoTexto = "Há {$dias} dias";
                } elseif ($dias < 30) {
                    $semanas = floor($dias / 7);
                    $tempoTexto = $semanas == 1 ? 'Há 1 semana' : "Há {$semanas} semanas";
                } elseif ($dias < 365) {
                    $meses = floor($dias / 30);
                    $tempoTexto = $meses == 1 ? 'Há 1 mês' : "Há {$meses} meses";
                } else {
                    $anos = floor($dias / 365);
                    $tempoTexto = $anos == 1 ? 'Há 1 ano' : "Há {$anos} anos";
                }
            @endphp
            <p class="mb-1 small text-muted">
                <i class="bx bx-time"></i> {{ $tempoTexto }}
            </p>
        @endif

        @if(isset($item['data_vencimento']))
            <p class="mb-1 small">
                <i class="bx bx-calendar"></i> <strong>Vencimento:</strong> {{ $item['data_vencimento']->format('d/m/Y') }}
            </p>
        @endif

        <div class="d-flex gap-1 flex-wrap mt-2">
            @if(isset($item['url']))
                <a href="{{ $item['url'] }}" class="btn btn-sm btn-info" title="Ver Detalhes">
                    <i class="bx bx-show"></i>
                </a>
            @endif

            @if(isset($item['acoes']))
                @foreach($item['acoes'] as $acaoNome => $acaoUrl)
                    @if($acaoNome === 'aprovar')
                        <form action="{{ $acaoUrl }}" method="POST" class="d-inline" onsubmit="return confirmarAcao('aprovar', this)">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success" title="Aprovar">
                                <i class="bx bx-check"></i>
                            </button>
                        </form>
                    @elseif($acaoNome === 'rejeitar')
                        <button type="button" class="btn btn-sm btn-danger" onclick="mostrarModalRejeitarEmprestimo('{{ $item['id'] }}', '{{ $acaoUrl }}')" title="Rejeitar">
                            <i class="bx bx-x"></i>
                        </button>
                    @elseif($acaoNome === 'liberar')
                        <form action="{{ $acaoUrl }}" method="POST" class="d-inline" onsubmit="return confirmarAcao('liberar', this)">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" title="Liberar">
                                <i class="bx bx-transfer"></i>
                            </button>
                        </form>
                    @elseif($acaoNome === 'confirmar')
                        <form action="{{ $acaoUrl }}" method="POST" class="d-inline" onsubmit="return confirmarAcao('confirmar', this)">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success" title="Confirmar">
                                <i class="bx bx-check"></i>
                            </button>
                        </form>
                    @elseif($acaoNome === 'pagar')
                        <a href="{{ $acaoUrl }}" class="btn btn-sm btn-success" title="Registrar Pagamento">
                            <i class="bx bx-money"></i>
                        </a>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
</div>
