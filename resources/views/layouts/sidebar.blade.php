<!-- ========== Left Sidebar Start ========== -->
<div class="vertical-menu">

    <!-- LOGO -->
    <div class="navbar-brand-box">
        <a href="{{ route('dashboard.index') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ URL::asset('build/images/logo-dark-sm.png') }}" alt="" height="26">
            </span>
            <span class="logo-lg">
                <img src="{{ URL::asset('build/images/logo-dark.png') }}" alt="" height="28">
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

    <div data-simplebar class="sidebar-menu-scroll">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu">Dashboard</li>

               <li>
                    <a href="{{ route('dashboard.index') }}">
                        <i class="bx bx-home-alt icon nav-icon"></i>
                        <span class="menu-item" data-key="t-dashboard">Dashboard</span>
                    </a>
                </li>

                @if(!auth()->user()->isSuperAdmin())
                <li>
                    <a href="{{ route('kanban.index') }}">
                        <i class="bx bx-grid-alt icon nav-icon"></i>
                        <span class="menu-item">Painel de Pendências</span>
                    </a>
                </li>
                @endif

                @if(auth()->user()->isSuperAdmin())
                <li class="menu-title" data-key="t-super-admin">Super Admin</li>
                <li>
                    <a href="{{ route('super-admin.configuracoes.index') }}">
                        <i class="bx bx-cog icon nav-icon"></i>
                        <span class="menu-item">Configurações do sistema</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('super-admin.empresas.index') }}">
                        <i class="bx bx-building icon nav-icon"></i>
                        <span class="menu-item">Empresas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('super-admin.operacoes.index') }}">
                        <i class="bx bx-briefcase icon nav-icon"></i>
                        <span class="menu-item">Operações</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('super-admin.usuarios.index') }}">
                        <i class="bx bx-group icon nav-icon"></i>
                        <span class="menu-item">Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('clientes.index') }}">
                        <i class="bx bx-user icon nav-icon"></i>
                        <span class="menu-item">Clientes</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('super-admin.auditoria.index') }}">
                        <i class="bx bx-list-check icon nav-icon"></i>
                        <span class="menu-item">Logs de Auditoria</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('super-admin.tarefas-agendadas.index') }}">
                        <i class="bx bx-time-five icon nav-icon"></i>
                        <span class="menu-item">Tarefas Agendadas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('super-admin.sandbox.index') }}">
                        <i class="bx bx-code-block icon nav-icon"></i>
                        <span class="menu-item">Sandbox</span>
                    </a>
                </li>
                @endif

                @if(!auth()->user()->isSuperAdmin())
                <li class="menu-title" data-key="t-sistema">PagDesk</li>

                <li>
                    <a href="{{ route('clientes.index') }}">
                        <i class="bx bx-user icon nav-icon"></i>
                        <span class="menu-item">Clientes</span>
                    </a>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i class="bx bx-search-alt icon nav-icon"></i>
                        <span class="menu-item">Consultas</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('radar.index') }}">Radar</a></li>
                        <li><a href="{{ route('consultas.devedores') }}">Devedores</a></li>
                    </ul>
                </li>

                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                <li>
                    <a href="{{ route('vendas.index') }}">
                        <i class="bx bx-cart icon nav-icon"></i>
                        <span class="menu-item">Vendas</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('produtos.index') }}">
                        <i class="bx bx-package icon nav-icon"></i>
                        <span class="menu-item">Produtos</span>
                    </a>
                </li>
                @endif
                @endif

                @if(!auth()->user()->isSuperAdmin())
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i class="bx bx-money icon nav-icon"></i>
                        <span class="menu-item">Empréstimos</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('emprestimos.index') }}">Listar Empréstimos</a></li>
                        <li><a href="{{ route('emprestimos.create') }}">Novo Empréstimo</a></li>
                        <li><a href="{{ route('renovacoes.index') }}">Renovações</a></li>
                    </ul>
                </li>

                <!-- Garantias -->
                <li>
                    <a href="{{ route('garantias.index') }}">
                        <i class="bx bx-shield-quarter icon nav-icon"></i>
                        <span class="menu-item">Garantias</span>
                    </a>
                </li>

                <!-- Cheques -->
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i class="bx bx-money icon nav-icon"></i>
                        <span class="menu-item">Cheques</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('cheques.index') }}">Listar Cheques</a></li>
                        <li><a href="{{ route('cheques.hoje') }}">Cheques de Hoje</a></li>
                    </ul>
                </li>
                @endif

                @if(!auth()->user()->isSuperAdmin())
                <li>
                    <a href="{{ route('cobrancas.index') }}">
                        <i class="bx bx-calendar-check icon nav-icon"></i>
                        <span class="menu-item">Cobranças do Dia</span>
                    </a>
                </li>
                @endif

                @if(!auth()->user()->isSuperAdmin())
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i class="bx bx-wallet icon nav-icon"></i>
                        <span class="menu-item">Caixa / Financeiro</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('caixa.index') }}">Movimentações</a></li>
                        <li><a href="{{ route('caixa.categorias.index') }}">Categorias</a></li>
                        <li>
                            <a href="{{ route('fechamento-caixa.index') }}">
                                Fechamento de Caixa
                                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])))
                                    @php
                                        $aguardandoConfirmacao = app(\App\Modules\Cash\Services\SettlementService::class)->contarAguardandoConfirmacao(auth()->user());
                                    @endphp
                                    @if($aguardandoConfirmacao > 0)
                                        <span class="badge rounded-pill bg-warning text-dark">{{ $aguardandoConfirmacao }}</span>
                                    @endif
                                @endif
                            </a>
                        </li>
                    </ul>
                </li>
                @endif

                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])))
                <li>
                    <a href="{{ route('produtos-recebidos.index') }}">
                        <i class="bx bx-package icon nav-icon"></i>
                        <span class="menu-item">Produtos recebidos</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('liberacoes.index') }}">
                        <i class="bx bx-transfer icon nav-icon"></i>
                        <span class="menu-item">Liberações</span>
                        @php
                            $userSidebar = auth()->user();
                            $opsIdsSidebar = $userSidebar->getOperacoesIds();
                            $aplicarFiltroOps = !$userSidebar->isSuperAdmin();
                            $queryLiberacoes = \App\Modules\Loans\Models\LiberacaoEmprestimo::where('status', 'aguardando');
                            if ($aplicarFiltroOps) {
                                if (!empty($opsIdsSidebar)) {
                                    $queryLiberacoes->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                } else {
                                    $queryLiberacoes->whereRaw('1 = 0');
                                }
                            }
                            $liberacoesPendentes = $queryLiberacoes->count();
                            $produtoObjetoPendentes = \App\Modules\Loans\Models\Pagamento::where('metodo', 'produto_objeto')->whereNull('aceite_gestor_id')->whereNull('rejeitado_por_id');
                            $solicitacoesJurosParcial = \App\Modules\Loans\Models\SolicitacaoPagamentoJurosParcial::where('status', 'aguardando');
                            $solicitacoesJurosContratoReduzido = \App\Modules\Loans\Models\SolicitacaoPagamentoJurosContratoReduzido::where('status', 'aguardando');
                            $solicitacoesRenovacaoAbate = \App\Modules\Loans\Models\SolicitacaoRenovacaoAbate::where('status', 'aguardando');
                            $solicitacoesQuitacaoDesconto = \App\Modules\Loans\Models\SolicitacaoQuitacao::where('status', 'pendente');
                            $solicitacoesNegociacao = \App\Modules\Loans\Models\SolicitacaoNegociacao::where('status', 'pendente');
                            $solicitacoesRetroativo = \App\Modules\Loans\Models\SolicitacaoEmprestimoRetroativo::where('status', 'aguardando');
                            if ($aplicarFiltroOps) {
                                if (!empty($opsIdsSidebar)) {
                                    $produtoObjetoPendentes->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                    $solicitacoesJurosParcial->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                    $solicitacoesNegociacao->whereIn('operacao_id', $opsIdsSidebar);
                                    $solicitacoesJurosContratoReduzido->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                    $solicitacoesRenovacaoAbate->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                    $solicitacoesQuitacaoDesconto->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                    $solicitacoesRetroativo->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIdsSidebar));
                                } else {
                                    $produtoObjetoPendentes->whereRaw('1 = 0');
                                    $solicitacoesJurosParcial->whereRaw('1 = 0');
                                    $solicitacoesJurosContratoReduzido->whereRaw('1 = 0');
                                    $solicitacoesRenovacaoAbate->whereRaw('1 = 0');
                                    $solicitacoesQuitacaoDesconto->whereRaw('1 = 0');
                                    $solicitacoesNegociacao->whereRaw('1 = 0');
                                    $solicitacoesRetroativo->whereRaw('1 = 0');
                                }
                            }
                            $countProdutoObjeto = $produtoObjetoPendentes->count();
                            $countJurosParcial = $solicitacoesJurosParcial->count();
                            $countJurosContratoReduzido = $solicitacoesJurosContratoReduzido->count();
                            $countRenovacaoAbate = $solicitacoesRenovacaoAbate->count();
                            $countQuitacaoDesconto = $solicitacoesQuitacaoDesconto->count();
                            $countNegociacao = $solicitacoesNegociacao->count();
                            $countRetroativo = $solicitacoesRetroativo->count();
                        @endphp
                        @if($liberacoesPendentes > 0)
                            <span class="badge rounded-pill bg-danger">{{ $liberacoesPendentes }}</span>
                        @endif
                        @if($countRenovacaoAbate > 0)
                            <span class="badge rounded-pill bg-primary">{{ $countRenovacaoAbate }}</span>
                        @endif
                        @if($countNegociacao > 0)
                            <span class="badge rounded-pill bg-dark">{{ $countNegociacao }}</span>
                        @endif
                        @if($countProdutoObjeto > 0)
                            <span class="badge rounded-pill bg-warning text-dark">{{ $countProdutoObjeto }}</span>
                        @endif
                        @if($countJurosParcial > 0)
                            <span class="badge rounded-pill bg-info">{{ $countJurosParcial }}</span>
                        @endif
                        @if($countJurosContratoReduzido > 0)
                            <span class="badge rounded-pill bg-secondary">{{ $countJurosContratoReduzido }}</span>
                        @endif
                        @if($countQuitacaoDesconto > 0)
                            <span class="badge rounded-pill bg-success">{{ $countQuitacaoDesconto }}</span>
                        @endif
                    </a>
                </li>
                @endif

                @php
                    $temLiberacoesComoConsultor = \App\Modules\Loans\Models\LiberacaoEmprestimo::where('consultor_id', auth()->id())->exists();
                    $temPapelConsultorEmAlgumaOp = !empty(auth()->user()->getOperacoesIdsOndeTemPapel(['consultor']));
                    $mostrarMenuLiberacoes = $temPapelConsultorEmAlgumaOp || $temLiberacoesComoConsultor;
                @endphp
                @if($mostrarMenuLiberacoes)
                <li>
                    <a href="{{ route('liberacoes.minhas') }}">
                        <i class="bx bx-receipt icon nav-icon"></i>
                        <span class="menu-item">Minhas Liberações</span>
                        @php
                            $liberacoesParaPagar = \App\Modules\Loans\Models\LiberacaoEmprestimo::where('consultor_id', auth()->id())
                                ->where('status', 'liberado')
                                ->count();
                        @endphp
                        @if($liberacoesParaPagar > 0)
                            <span class="badge rounded-pill bg-warning">{{ $liberacoesParaPagar }}</span>
                        @endif
                    </a>
                </li>
                @endif

                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i class="bx bx-file-blank icon nav-icon"></i>
                        <span class="menu-item">Relatórios</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('relatorios.recebimento-juros-dia') }}">Recebimento e juros por dia</a></li>
                        <li><a href="{{ route('relatorios.parcelas-atrasadas') }}">Parcelas atrasadas</a></li>
                        <li><a href="{{ route('relatorios.quitacoes') }}">Quitações</a></li>
                        <li><a href="{{ route('relatorios.juros-quitacoes') }}">Juros por quitação</a></li>
                        <li><a href="{{ route('relatorios.comissoes') }}">Comissões</a></li>
                        <li><a href="{{ route('relatorios.entradas-saidas-categoria') }}">Entradas e saídas</a></li>
                    </ul>
                </li>
                @endif

                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador'])))
                <li>
                    <a href="{{ route('aprovacoes.index') }}">
                        <i class="bx bx-check-circle icon nav-icon"></i>
                        <span class="menu-item">Aprovações</span>
                        @php
                            $queryPendentes = \App\Modules\Loans\Models\Emprestimo::where('status', 'pendente');
                            if (!auth()->user()->isSuperAdmin()) {
                                $opsAprov = auth()->user()->getOperacoesIdsOndeTemPapel(['administrador']);
                                if (!empty($opsAprov)) {
                                    $queryPendentes->whereIn('operacao_id', $opsAprov);
                                } else {
                                    $queryPendentes->whereRaw('1 = 0');
                                }
                            }
                            $pendentes = $queryPendentes->count();
                        @endphp
                        @if($pendentes > 0)
                            <span class="badge rounded-pill bg-danger">{{ $pendentes }}</span>
                        @endif
                    </a>
                </li>
                @endif

                @if(!empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor'])))
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i class="bx bx-cog icon nav-icon"></i>
                        <span class="menu-item">Administração</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('operacoes.index') }}">Operações</a></li>
                        <li><a href="{{ route('usuarios.index') }}">Usuários / Permissões</a></li>
                    </ul>
                </li>
                @endif

            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>
<!-- Left Sidebar End -->