<header id="page-topbar" class="isvertical-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="{{ route('dashboard.index') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ URL::asset('build/images/logo-dark-sm.png') }}" alt="" height="26">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ URL::asset('build/images/logo-dark-sm.png') }}" alt="" height="26">
                    </span>
                </a>

                <a href="{{ route('dashboard.index') }}" class="logo logo-light">
                    <span class="logo-lg">
                        <img src="{{ URL::asset('build/images/logo-light.png') }}" alt="" height="30">
                    </span>
                    <span class="logo-sm">
                        <img src="{{ URL::asset('build/images/logo-light-sm.png') }}" alt="" height="26">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect vertical-menu-btn">
                <i class="bx bx-menu align-middle"></i>
            </button>

            <!-- start page title -->
            <div class="page-title-box align-self-center d-none d-md-block">
                <h4 class="page-title mb-0">@yield('page-title')</h4>
            </div>
            <!-- end page title -->

        </div>

        <div class="d-flex">

            {{-- Saldo do Usuário --}}
            @php
                $saldosHeader = app(\App\Modules\Cash\Services\CashService::class)->getSaldosUsuarioHeader(auth()->user());
            @endphp
            @if(count($saldosHeader['operacoes']) > 0)
            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item d-flex align-items-center" id="header-saldo-dropdown"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-wallet text-primary me-1" style="font-size: 20px;"></i>
                    <span class="d-none d-md-inline-block">
                        <span id="saldo-valor" class="fw-semibold {{ $saldosHeader['total'] >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format($saldosHeader['total'], 2, ',', '.') }}
                        </span>
                        <span id="saldo-oculto" class="fw-semibold text-muted" style="display: none;">
                            R$ •••••
                        </span>
                    </span>
                    <i class="bx bx-chevron-down ms-1 d-none d-md-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 280px;">
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Meu Saldo</h6>
                            <button type="button" class="btn btn-sm btn-light" id="toggle-saldo-visibilidade" title="Mostrar/Ocultar saldo">
                                <i class="bx bx-show" id="icone-visibilidade"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-2">
                        @if(count($saldosHeader['operacoes']) > 1)
                            <div class="mb-2 px-2">
                                <small class="text-muted">Total (todas operações)</small>
                                <div class="saldo-item fw-bold {{ $saldosHeader['total'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    R$ {{ number_format($saldosHeader['total'], 2, ',', '.') }}
                                </div>
                            </div>
                            <hr class="my-2">
                            <small class="text-muted px-2">Por operação:</small>
                        @endif
                        @foreach($saldosHeader['operacoes'] as $op)
                            <div class="d-flex justify-content-between align-items-center px-2 py-1">
                                <span class="text-truncate" style="max-width: 150px;" title="{{ $op['nome'] }}">{{ $op['nome'] }}</span>
                                <span class="saldo-item fw-semibold {{ $op['saldo'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    R$ {{ number_format($op['saldo'], 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="p-2 border-top">
                        <a href="{{ route('caixa.index') }}" class="btn btn-sm btn-primary w-100">
                            <i class="bx bx-list-ul me-1"></i> Ver Movimentações
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon" id="page-header-notifications-dropdown-v"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-bell icon-sm align-middle"></i>
                    <span class="noti-dot bg-danger rounded-pill" id="notificacao-badge" style="display: none;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-xl dropdown-menu-end p-0"
                    aria-labelledby="page-header-notifications-dropdown-v"
                    style="max-height: calc(100vh - 100px);">
                    <div class="p-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="m-0 font-size-15">Notificações</h5>
                            </div>
                            <div class="col-auto">
                                <a href="javascript:void(0);" id="marcar-todas-lidas" class="small fw-semibold text-decoration-underline">
                                    Marcar todas como lidas
                                </a>
                            </div>
                        </div>
                    </div>
                    <div data-simplebar style="max-height: 400px; overflow-y: auto;" id="notificacoes-lista">
                        <div class="text-center p-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-2 border-top d-grid">
                        <a class="btn btn-sm btn-link font-size-14 btn-block text-center" href="{{ route('notificacoes.index') }}">
                            <i class="uil-arrow-circle-right me-1"></i> <span>Ver Todas</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item user text-start d-flex align-items-center"
                    id="page-header-user-dropdown-v" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    @if(auth()->user()->avatar)
                        <img class="rounded-circle header-profile-user"
                            src="{{ auth()->user()->avatar_url }}" 
                            alt="Header Avatar">
                    @else
                        <div class="rounded-circle header-profile-user bg-primary-subtle d-flex align-items-center justify-content-center text-primary fw-bold" 
                             style="width: 38px; height: 38px; font-size: 16px;">
                            {{ auth()->user()->initial }}
                        </div>
                    @endif
                    <span class="d-none d-xl-inline-block ms-2 fw-medium font-size-15">{{ auth()->user()->name }}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end pt-0">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0">{{ auth()->user()->name }}</h6>
                        <p class="mb-0 font-size-11 text-muted">{{ auth()->user()->email }}</p>
                        @if(auth()->user()->operacoes->count() > 0)
                            @php
                                $operacoesUsuario = auth()->user()->operacoes;
                                $limiteOperacoesDropdown = 5;
                                $operacoesMostrar = $operacoesUsuario->take($limiteOperacoesDropdown);
                                $temMaisOperacoes = $operacoesUsuario->count() > $limiteOperacoesDropdown;
                            @endphp
                            <div class="mt-2">
                                <small class="text-muted">Operações:</small>
                                <div>
                                    @foreach($operacoesMostrar as $operacao)
                                        <span class="badge bg-secondary me-1">{{ $operacao->nome }}</span>
                                    @endforeach
                                    @if($temMaisOperacoes)
                                        <a href="{{ route('profile.operacoes') }}" class="small text-primary text-decoration-none ms-1">Ver todas ({{ $operacoesUsuario->count() }})</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    <a class="dropdown-item" href="{{ route('profile.show') }}"><i
                            class="mdi mdi-account-circle text-muted font-size-16 align-middle me-2"></i> <span
                            class="align-middle">Meu Perfil</span></a>
                    @if(auth()->user()->operacoes->count() > 0)
                    <a class="dropdown-item" href="{{ route('profile.operacoes') }}"><i
                            class="mdi mdi-briefcase-outline text-muted font-size-16 align-middle me-2"></i> <span
                            class="align-middle">Minhas Operações</span></a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="javascript:void();"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i
                            class="mdi mdi-logout text-muted font-size-16 align-middle me-2"></i> <span
                            class="align-middle">Deslogar</span></a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Script para toggle de visibilidade do saldo --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-saldo-visibilidade');
    const saldoValor = document.getElementById('saldo-valor');
    const saldoOculto = document.getElementById('saldo-oculto');
    const iconeVisibilidade = document.getElementById('icone-visibilidade');
    const saldoItens = document.querySelectorAll('.saldo-item');
    const STORAGE_KEY = 'saldo_visivel';

    if (!toggleBtn || !saldoValor || !saldoOculto) return;

    // Carregar preferência salva
    const saldoVisivel = localStorage.getItem(STORAGE_KEY) !== 'false';
    atualizarVisibilidade(saldoVisivel);

    toggleBtn.addEventListener('click', function() {
        const visivel = saldoValor.style.display !== 'none';
        atualizarVisibilidade(!visivel);
        localStorage.setItem(STORAGE_KEY, !visivel);
    });

    function atualizarVisibilidade(visivel) {
        if (visivel) {
            saldoValor.style.display = '';
            saldoOculto.style.display = 'none';
            iconeVisibilidade.classList.remove('bx-hide');
            iconeVisibilidade.classList.add('bx-show');
            saldoItens.forEach(function(item) {
                item.style.visibility = 'visible';
            });
        } else {
            saldoValor.style.display = 'none';
            saldoOculto.style.display = '';
            iconeVisibilidade.classList.remove('bx-show');
            iconeVisibilidade.classList.add('bx-hide');
            saldoItens.forEach(function(item) {
                item.style.visibility = 'hidden';
            });
        }
    }
});
</script>
