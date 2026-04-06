<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Auth::routes(['register' => false]);

// GET /logout: redireciona para login (logout real é via POST pelo botão Sair)
Route::get('/logout', function () {
    return redirect()->route('login');
})->name('logout.get');

Route::get('/', [App\Http\Controllers\HomeController::class, 'root'])->name('home');

// Cadastro público do cliente via link (sem login)
Route::get('/cadastro/cliente', [App\Modules\Core\Controllers\CadastroClienteController::class, 'showForm'])->name('cadastro-cliente.form');
Route::post('/cadastro/cliente', [App\Modules\Core\Controllers\CadastroClienteController::class, 'store'])->name('cadastro-cliente.store');
Route::get('/cadastro/cliente/concluido', [App\Modules\Core\Controllers\CadastroClienteController::class, 'concluido'])->name('cadastro-cliente.concluido');

// Health check para monitoramento e load balancers
Route::get('/health', App\Http\Controllers\HealthController::class)->name('health');
Route::get('/health/live', [App\Http\Controllers\HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [App\Http\Controllers\HealthController::class, 'ready'])->name('health.ready');

// Página de manutenção: se não estiver em manutenção, redireciona para o dashboard
Route::get('/manutencao', function () {
    if (! \Illuminate\Support\Facades\Cache::get(\App\Http\Middleware\CheckManutencaoSistema::CACHE_KEY, false)) {
        return redirect()->route('dashboard.index');
    }

    return view('pages-maintenance');
})->name('manutencao');

// Página exibida quando o usuário loga com conta bloqueada (ativo = false)
Route::get('/conta-bloqueada', function () {
    return view('pages-conta-bloqueada', [
        'motivo' => session('motivo'),
    ]);
})->name('conta.bloqueada');

// Rotas autenticadas (throttle.sensitive: 40 ações POST/PUT/PATCH/DELETE por minuto por usuário)
Route::middleware(['auth', 'throttle.sensitive'])->group(function () {

    // Super Admin - Gestão de Empresas e Usuários
    Route::prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/configuracoes', [App\Http\Controllers\SuperAdmin\ConfiguracoesSistemaController::class, 'index'])->name('configuracoes.index');
        Route::post('/manutencao/toggle', [App\Http\Controllers\SuperAdmin\ManutencaoController::class, 'toggle'])->name('manutencao.toggle');

        // Usuários (todas as empresas)
        Route::prefix('usuarios')->name('usuarios.')->group(function () {
            Route::get('/', [App\Http\Controllers\SuperAdmin\UsuarioController::class, 'index'])->name('index');
            Route::get('/{id}', [App\Http\Controllers\SuperAdmin\UsuarioController::class, 'show'])->name('show');
            Route::put('/{id}', [App\Http\Controllers\SuperAdmin\UsuarioController::class, 'update'])->name('update');
        });

        // Operações (todas as empresas)
        Route::prefix('operacoes')->name('operacoes.')->group(function () {
            Route::get('/', [App\Http\Controllers\SuperAdmin\OperacaoController::class, 'index'])->name('index');
            Route::get('/{id}', [App\Http\Controllers\SuperAdmin\OperacaoController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [App\Http\Controllers\SuperAdmin\OperacaoController::class, 'edit'])->name('edit');
            Route::put('/{id}', [App\Http\Controllers\SuperAdmin\OperacaoController::class, 'update'])->name('update');
        });

        // Auditoria / Logs
        Route::prefix('auditoria')->name('auditoria.')->group(function () {
            Route::get('/', [App\Http\Controllers\SuperAdmin\AuditoriaController::class, 'index'])->name('index');
            Route::get('/{id}', [App\Http\Controllers\SuperAdmin\AuditoriaController::class, 'show'])->name('show');
        });

        // Tarefas Agendadas (Crons)
        Route::prefix('tarefas-agendadas')->name('tarefas-agendadas.')->group(function () {
            Route::get('/', [App\Http\Controllers\SuperAdmin\TarefasAgendadasController::class, 'index'])->name('index');
            Route::post('/executar', [App\Http\Controllers\SuperAdmin\TarefasAgendadasController::class, 'executar'])->name('executar');
        });

        // Sandbox (ambiente de testes)
        Route::get('/sandbox', [App\Http\Controllers\SuperAdmin\SandboxController::class, 'index'])->name('sandbox.index');
        Route::post('/sandbox/clientes', [App\Http\Controllers\SuperAdmin\SandboxController::class, 'storeClientes'])->name('sandbox.store-clientes');
        Route::post('/sandbox/cenario', [App\Http\Controllers\SuperAdmin\SandboxController::class, 'storeCenario'])->name('sandbox.store-cenario');
        Route::post('/sandbox/cenario-diaria', [App\Http\Controllers\SuperAdmin\SandboxController::class, 'storeCenarioDiaria'])->name('sandbox.store-cenario-diaria');
        Route::delete('/sandbox', [App\Http\Controllers\SuperAdmin\SandboxController::class, 'destroy'])->name('sandbox.destroy');

        Route::prefix('empresas')->name('empresas.')->group(function () {
            Route::get('/', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'store'])->name('store');

            // Rotas específicas de empresa (devem vir antes da rota genérica /{id})
            Route::get('/{id}/usuarios/create', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'createUsuario'])->name('usuarios.create');
            Route::post('/{id}/usuarios', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'storeUsuario'])->name('usuarios.store');
            Route::get('/{id}/operacoes/create', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'createOperacao'])->name('operacoes.create');
            Route::post('/{id}/operacoes', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'storeOperacao'])->name('operacoes.store');

            Route::get('/{id}', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'edit'])->name('edit');
            Route::put('/{id}', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'update'])->name('update');
            Route::post('/{id}/suspender', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'suspender'])->name('suspender');
            Route::post('/{id}/ativar', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'ativar'])->name('ativar');
            Route::delete('/{id}', [App\Http\Controllers\SuperAdmin\EmpresaController::class, 'destroy'])->name('destroy');
        });
    });

    // Dashboard
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\DashboardController::class, 'index'])->name('index');
    });

    // Kanban Board (Painel de Pendências)
    Route::prefix('kanban')->name('kanban.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\KanbanBoardController::class, 'index'])->name('index');
    });

    // Comprovantes adicionais (múltiplos por entidade)
    Route::post('/comprovante-anexos', [App\Http\Controllers\ComprovanteAnexoController::class, 'store'])->name('comprovante-anexos.store');

    // Busca Global
    Route::get('/api/search', [App\Modules\Core\Controllers\SearchController::class, 'buscar'])->name('search.global');

    // Radar — Consulta cadastral interna (CPF/CNPJ, pendências, empréstimos ativos)
    Route::get('/radar', [App\Modules\Core\Controllers\RadarController::class, 'index'])->name('radar.index');

    // Consultas (menu agrupado)
    Route::get('/consultas/devedores', [App\Modules\Core\Controllers\DevedoresController::class, 'index'])->name('consultas.devedores');

    // Usuários (API para busca)
    Route::get('/api/usuarios/buscar', [App\Modules\Core\Controllers\UsuarioController::class, 'buscar'])->name('usuarios.api.buscar');

    // Clientes
    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\ClienteController::class, 'index'])->name('index');
        Route::get('/export', [App\Modules\Core\Controllers\ClienteController::class, 'export'])->name('export');
        Route::get('/link-cadastro', [App\Modules\Core\Controllers\ClienteController::class, 'linkCadastro'])->name('link-cadastro');
        Route::get('/create', [App\Modules\Core\Controllers\ClienteController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Core\Controllers\ClienteController::class, 'store'])->name('store');
        Route::get('/{id}/vincular', [App\Modules\Core\Controllers\ClienteController::class, 'vincular'])->name('vincular');
        Route::post('/{id}/desvincular-operacao', [App\Modules\Core\Controllers\ClienteController::class, 'desvincularOperacao'])->name('desvincular-operacao');
        Route::get('/{id}', [App\Modules\Core\Controllers\ClienteController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [App\Modules\Core\Controllers\ClienteController::class, 'edit'])->name('edit');
        Route::put('/{id}', [App\Modules\Core\Controllers\ClienteController::class, 'update'])->name('update');
        Route::get('/buscar/cpf', [App\Modules\Core\Controllers\ClienteController::class, 'buscarPorCpf'])->name('buscar.cpf');
        Route::get('/api/buscar', [App\Modules\Core\Controllers\ClienteController::class, 'buscar'])->name('api.buscar');
    });

    // Vendas (Administrador e Gestor)
    Route::prefix('vendas')->name('vendas.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\VendaController::class, 'index'])->name('index');
        Route::get('/create', [App\Modules\Core\Controllers\VendaController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Core\Controllers\VendaController::class, 'store'])->name('store');
        Route::get('/{venda}/formas/{forma}/comprovante', [App\Modules\Core\Controllers\VendaController::class, 'comprovante'])->name('formas.comprovante');
        Route::get('/{id}', [App\Modules\Core\Controllers\VendaController::class, 'show'])->name('show');
    });

    // Produtos (Administrador e Gestor)
    Route::prefix('produtos')->name('produtos.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\ProdutoController::class, 'index'])->name('index');
        Route::get('/create', [App\Modules\Core\Controllers\ProdutoController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Core\Controllers\ProdutoController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Modules\Core\Controllers\ProdutoController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [App\Modules\Core\Controllers\ProdutoController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch', 'post'], '/{id}', [App\Modules\Core\Controllers\ProdutoController::class, 'update'])->name('update');
        Route::delete('/{id}/anexos/{anexoId}', [App\Modules\Core\Controllers\ProdutoController::class, 'destroyAnexo'])->name('anexos.destroy');
    });

    // Empréstimos
    Route::prefix('emprestimos')->name('emprestimos.')->group(function () {
        Route::get('/', [App\Modules\Loans\Controllers\EmprestimoController::class, 'index'])->name('index');
        Route::get('/export', [App\Modules\Loans\Controllers\EmprestimoController::class, 'export'])->name('export');
        Route::get('/create', [App\Modules\Loans\Controllers\EmprestimoController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Loans\Controllers\EmprestimoController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Modules\Loans\Controllers\EmprestimoController::class, 'show'])->name('show');
        Route::post('/{id}/renovar', [App\Modules\Loans\Controllers\EmprestimoController::class, 'renovar'])->name('renovar');
        Route::post('/{id}/cancelar', [App\Modules\Loans\Controllers\EmprestimoController::class, 'cancelar'])->name('cancelar');
        Route::post('/{id}/cancelar-com-desfazimento', [App\Modules\Loans\Controllers\EmprestimoController::class, 'cancelarComDesfazimento'])->name('cancelar-com-desfazimento');
        Route::post('/{id}/garantias/{garantiaId}/executar', [App\Modules\Loans\Controllers\EmprestimoController::class, 'executarGarantia'])->name('garantias.executar');
        Route::post('/{id}/parcelas-retroativo', [App\Modules\Loans\Controllers\EmprestimoController::class, 'registrarParcelasPagasRetroativo'])->name('parcelas-retroativo');

        // Garantias (empréstimo empenho)
        Route::post('/{id}/garantias', [App\Modules\Loans\Controllers\GarantiaController::class, 'store'])->name('garantias.store');
        Route::put('/garantias/{garantiaId}', [App\Modules\Loans\Controllers\GarantiaController::class, 'update'])->name('garantias.update');
        Route::delete('/garantias/{garantiaId}', [App\Modules\Loans\Controllers\GarantiaController::class, 'destroy'])->name('garantias.destroy');
        Route::post('/garantias/{garantiaId}/anexos', [App\Modules\Loans\Controllers\GarantiaController::class, 'uploadAnexo'])->name('garantias.anexos.upload');
        Route::delete('/garantias/anexos/{anexoId}', [App\Modules\Loans\Controllers\GarantiaController::class, 'destroyAnexo'])->name('garantias.anexos.destroy');

        // Cheques (troca de cheque)
        Route::post('/{id}/cheques', [App\Modules\Loans\Controllers\ChequeController::class, 'store'])->name('cheques.store');
        Route::put('/cheques/{chequeId}', [App\Modules\Loans\Controllers\ChequeController::class, 'update'])->name('cheques.update');
        Route::delete('/cheques/{chequeId}', [App\Modules\Loans\Controllers\ChequeController::class, 'destroy'])->name('cheques.destroy');
        Route::post('/cheques/{chequeId}/depositar', [App\Modules\Loans\Controllers\ChequeController::class, 'depositar'])->name('cheques.depositar');
        Route::post('/cheques/{chequeId}/compensar', [App\Modules\Loans\Controllers\ChequeController::class, 'compensar'])->name('cheques.compensar');
        Route::post('/cheques/{chequeId}/devolver', [App\Modules\Loans\Controllers\ChequeController::class, 'devolver'])->name('cheques.devolver');
        Route::get('/cheques/{chequeId}/pagar', [App\Modules\Loans\Controllers\ChequeController::class, 'showPagar'])->name('cheques.pagar');
        Route::post('/cheques/{chequeId}/pagar-dinheiro', [App\Modules\Loans\Controllers\ChequeController::class, 'pagarEmDinheiro'])->name('cheques.pagar-dinheiro');
        Route::post('/cheques/{chequeId}/substituir', [App\Modules\Loans\Controllers\ChequeController::class, 'substituir'])->name('cheques.substituir');

        // Quitação completa (tela dedicada)
        Route::get('/{id}/quitar', [App\Modules\Loans\Controllers\QuitacaoController::class, 'quitar'])->name('quitar');
    });

    // Quitação: store e listagem de pendentes (gestor/admin)
    Route::post('/quitacao', [App\Modules\Loans\Controllers\QuitacaoController::class, 'store'])->name('quitacao.store');
    Route::prefix('quitacao')->name('quitacao.')->group(function () {
        Route::get('/pendentes', [App\Modules\Loans\Controllers\QuitacaoController::class, 'indexPendentes'])->name('pendentes');
        Route::post('/pendentes/{id}/aprovar', [App\Modules\Loans\Controllers\QuitacaoController::class, 'aprovar'])->name('aprovar');
        Route::post('/pendentes/{id}/rejeitar', [App\Modules\Loans\Controllers\QuitacaoController::class, 'rejeitar'])->name('rejeitar');
    });

    // Empréstimos retroativos aguardando aceite (gestor/admin)
    Route::prefix('emprestimos-retroativo')->name('emprestimos.retroativo.')->group(function () {
        Route::get('/pendentes', [App\Modules\Loans\Controllers\EmprestimoController::class, 'indexPendentesRetroativo'])->name('pendentes');
        Route::post('/pendentes/aprovar-lote', [App\Modules\Loans\Controllers\EmprestimoController::class, 'aprovarRetroativoLote'])->name('aprovar-lote');
        Route::post('/pendentes/{id}/aprovar', [App\Modules\Loans\Controllers\EmprestimoController::class, 'aprovarRetroativo'])->name('aprovar');
        Route::post('/pendentes/{id}/rejeitar', [App\Modules\Loans\Controllers\EmprestimoController::class, 'rejeitarRetroativo'])->name('rejeitar');
    });

    // Garantias (listagem geral)
    Route::get('/garantias', [App\Modules\Loans\Controllers\GarantiaController::class, 'index'])->name('garantias.index');
    Route::get('/garantias/{id}', [App\Modules\Loans\Controllers\GarantiaController::class, 'show'])->name('garantias.show');

    // Cheques (listagem geral)
    Route::prefix('cheques')->name('cheques.')->group(function () {
        Route::get('/', [App\Modules\Loans\Controllers\ChequeController::class, 'index'])->name('index');
        Route::get('/hoje', [App\Modules\Loans\Controllers\ChequeController::class, 'hoje'])->name('hoje');
    });

    // Renovações
    Route::prefix('renovacoes')->name('renovacoes.')->group(function () {
        Route::get('/', [App\Modules\Loans\Controllers\RenovacaoController::class, 'index'])->name('index');
        Route::get('/cliente/{clienteId}', [App\Modules\Loans\Controllers\RenovacaoController::class, 'showCliente'])->name('show-cliente');
    });

    // Cobranças do Dia
    Route::prefix('cobrancas')->name('cobrancas.')->group(function () {
        Route::get('/', [App\Modules\Loans\Controllers\ParcelaController::class, 'cobrancasDoDia'])->name('index');
    });

    // Parcelas Atrasadas
    Route::prefix('parcelas')->name('parcelas.')->group(function () {
        Route::get('/atrasadas', [App\Modules\Loans\Controllers\ParcelaController::class, 'parcelasAtrasadas'])->name('atrasadas');
    });

    // Relatórios (Administrador e Gestor)
    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\RelatorioController::class, 'index'])->name('index');
        Route::get('/consultores-por-operacao', [App\Modules\Core\Controllers\RelatorioController::class, 'consultoresPorOperacao'])->name('consultores-por-operacao');
        Route::get('/recebimento-juros-dia', [App\Modules\Core\Controllers\RelatorioController::class, 'recebimentoJurosDia'])->name('recebimento-juros-dia');
        Route::get('/parcelas-atrasadas', [App\Modules\Core\Controllers\RelatorioController::class, 'parcelasAtrasadas'])->name('parcelas-atrasadas');
        Route::get('/receber-por-cliente', [App\Modules\Core\Controllers\RelatorioController::class, 'receberPorCliente'])->name('receber-por-cliente');
        Route::get('/quitacoes', [App\Modules\Core\Controllers\RelatorioController::class, 'quitacoes'])->name('quitacoes');
        Route::get('/comissoes/detalhe', [App\Modules\Core\Controllers\RelatorioController::class, 'comissoesDetalheConsultor'])->name('comissoes-detalhe');
        Route::get('/comissoes', [App\Modules\Core\Controllers\RelatorioController::class, 'comissoes'])->name('comissoes');
        Route::get('/valor-emprestado-principal', [App\Modules\Core\Controllers\RelatorioController::class, 'valorEmprestadoPrincipal'])->name('valor-emprestado-principal');
        Route::get('/entradas-saidas-categoria', [App\Modules\Core\Controllers\RelatorioController::class, 'entradasSaidasPorCategoria'])->name('entradas-saidas-categoria');
        Route::get('/juros-quitacoes', [App\Modules\Core\Controllers\RelatorioController::class, 'jurosQuitacoes'])->name('juros-quitacoes');
    });

    // Pagamentos
    Route::prefix('pagamentos')->name('pagamentos.')->group(function () {
        Route::get('/create', [App\Modules\Loans\Controllers\PagamentoController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Loans\Controllers\PagamentoController::class, 'store'])->name('store');
        Route::post('/{id}/anexar-comprovante', [App\Modules\Loans\Controllers\PagamentoController::class, 'anexarComprovante'])->name('anexar-comprovante');
        Route::get('/quitar-diarias/{emprestimo}', [App\Modules\Loans\Controllers\PagamentoController::class, 'quitarDiariasCreate'])->name('quitar-diarias.create');
        Route::post('/quitar-diarias/{emprestimo}', [App\Modules\Loans\Controllers\PagamentoController::class, 'quitarDiariasStore'])->name('quitar-diarias.store');
        Route::get('/multi-parcelas/{emprestimo}', [App\Modules\Loans\Controllers\PagamentoController::class, 'multiParcelasCreate'])->name('multi-parcelas.create');
        Route::post('/multi-parcelas/{emprestimo}', [App\Modules\Loans\Controllers\PagamentoController::class, 'multiParcelasStore'])->name('multi-parcelas.store');
    });

    // Aprovações (apenas administradores)
    Route::prefix('aprovacoes')->name('aprovacoes.')->group(function () {
        Route::get('/', [App\Modules\Approvals\Controllers\AprovacaoController::class, 'index'])->name('index');
        Route::post('/{emprestimoId}/aprovar', [App\Modules\Approvals\Controllers\AprovacaoController::class, 'aprovar'])->name('aprovar');
        Route::post('/{emprestimoId}/rejeitar', [App\Modules\Approvals\Controllers\AprovacaoController::class, 'rejeitar'])->name('rejeitar');
    });

    // Produtos recebidos (Gestor e Administrador)
    Route::get('/produtos-recebidos', [App\Modules\Loans\Controllers\LiberacaoController::class, 'produtosObjetoRecebidos'])->name('produtos-recebidos.index');

    // Liberações (Gestor e Administrador)
    Route::prefix('liberacoes')->name('liberacoes.')->group(function () {
        Route::get('/', [App\Modules\Loans\Controllers\LiberacaoController::class, 'index'])->name('index');
        Route::get('/pagamentos-produto-objeto', [App\Modules\Loans\Controllers\LiberacaoController::class, 'pagamentosProdutoObjeto'])->name('pagamentos-produto-objeto');
        Route::post('/pagamentos-produto-objeto/{id}/aceitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'aceitarPagamentoProdutoObjeto'])->name('aceitar-pagamento-produto-objeto');
        Route::post('/pagamentos-produto-objeto/{id}/rejeitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'rejeitarPagamentoProdutoObjeto'])->name('rejeitar-pagamento-produto-objeto');
        Route::get('/solicitacoes-juros-parcial', [App\Modules\Loans\Controllers\LiberacaoController::class, 'solicitacoesJurosParcial'])->name('juros-parcial');
        Route::post('/solicitacoes-juros-parcial/{id}/aprovar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'aprovarSolicitacaoJurosParcial'])->name('juros-parcial.aprovar');
        Route::post('/solicitacoes-juros-parcial/{id}/rejeitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'rejeitarSolicitacaoJurosParcial'])->name('juros-parcial.rejeitar');
        Route::get('/solicitacoes-juros-contrato-reduzido', [App\Modules\Loans\Controllers\LiberacaoController::class, 'solicitacoesJurosContratoReduzido'])->name('juros-contrato-reduzido');
        Route::post('/solicitacoes-juros-contrato-reduzido/{id}/aprovar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'aprovarSolicitacaoJurosContratoReduzido'])->name('juros-contrato-reduzido.aprovar');
        Route::post('/solicitacoes-juros-contrato-reduzido/{id}/rejeitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'rejeitarSolicitacaoJurosContratoReduzido'])->name('juros-contrato-reduzido.rejeitar');
        Route::get('/solicitacoes-renovacao-abate', [App\Modules\Loans\Controllers\LiberacaoController::class, 'solicitacoesRenovacaoAbate'])->name('renovacao-abate');
        Route::post('/solicitacoes-renovacao-abate/{id}/aprovar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'aprovarSolicitacaoRenovacaoAbate'])->name('renovacao-abate.aprovar');
        Route::post('/solicitacoes-renovacao-abate/{id}/rejeitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'rejeitarSolicitacaoRenovacaoAbate'])->name('renovacao-abate.rejeitar');
        Route::get('/solicitacoes-diaria-parcial', [App\Modules\Loans\Controllers\LiberacaoController::class, 'solicitacoesDiariaParcial'])->name('diaria-parcial');
        Route::post('/solicitacoes-diaria-parcial/{id}/aprovar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'aprovarSolicitacaoDiariaParcial'])->name('diaria-parcial.aprovar');
        Route::post('/solicitacoes-diaria-parcial/{id}/rejeitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'rejeitarSolicitacaoDiariaParcial'])->name('diaria-parcial.rejeitar');
        Route::get('/negociacoes', [App\Modules\Loans\Controllers\LiberacaoController::class, 'negociacoes'])->name('negociacoes');
        Route::post('/negociacoes/{id}/aprovar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'aprovarNegociacao'])->name('negociacoes.aprovar');
        Route::post('/negociacoes/{id}/rejeitar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'rejeitarNegociacao'])->name('negociacoes.rejeitar');
        Route::post('/liberar-lote', [App\Modules\Loans\Controllers\LiberacaoController::class, 'liberarLote'])->name('liberar-lote');
        Route::get('/{id}', [App\Modules\Loans\Controllers\LiberacaoController::class, 'show'])->name('show');
        Route::post('/{id}/liberar', [App\Modules\Loans\Controllers\LiberacaoController::class, 'liberar'])->name('liberar');
        Route::post('/{id}/anexar-comprovante-liberacao', [App\Modules\Loans\Controllers\LiberacaoController::class, 'anexarComprovanteLiberacao'])->name('anexar-comprovante-liberacao');
        Route::post('/{id}/anexar-comprovante-pagamento-cliente', [App\Modules\Loans\Controllers\LiberacaoController::class, 'anexarComprovantePagamentoCliente'])->name('anexar-comprovante-pagamento-cliente');
    });

    // Minhas Liberações (Consultor)
    Route::prefix('minhas-liberacoes')->name('liberacoes.')->group(function () {
        Route::get('/', [App\Modules\Loans\Controllers\LiberacaoController::class, 'minhasLiberacoes'])->name('minhas');
        Route::post('/{id}/confirmar-pagamento', [App\Modules\Loans\Controllers\LiberacaoController::class, 'confirmarPagamento'])->name('confirmar-pagamento');
    });

    // Caixa
    Route::prefix('caixa')->name('caixa.')->group(function () {
        Route::get('/', [App\Modules\Cash\Controllers\CashController::class, 'index'])->name('index');
        // Categorias de movimentação (entrada/despesa)
        Route::get('/categorias', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'index'])->name('categorias.index');
        Route::get('/categorias/create', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'create'])->name('categorias.create');
        Route::post('/categorias', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'store'])->name('categorias.store');
        Route::post('/categorias/ajax', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'storeAjax'])->name('categorias.store.ajax');
        Route::get('/categorias/{id}/edit', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'edit'])->name('categorias.edit');
        Route::put('/categorias/{id}', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'update'])->name('categorias.update');
        Route::delete('/categorias/{id}', [App\Modules\Cash\Controllers\CategoriaMovimentacaoController::class, 'destroy'])->name('categorias.destroy');
        // Movimentações manuais (apenas gestor e administrador)
        Route::get('/movimentacao/create', [App\Modules\Cash\Controllers\CashController::class, 'create'])->name('movimentacao.create');
        Route::post('/movimentacao', [App\Modules\Cash\Controllers\CashController::class, 'store'])->name('movimentacao.store');
        // Sangria: próprio caixa → Caixa da Operação (gestor/admin)
        Route::get('/sangria/create', [App\Modules\Cash\Controllers\CashController::class, 'sangriaCreate'])->name('sangria.create');
        Route::post('/sangria', [App\Modules\Cash\Controllers\CashController::class, 'sangriaStore'])->name('sangria.store');
        Route::get('/transferencia-operacao/create', [App\Modules\Cash\Controllers\CashController::class, 'transferenciaOperacaoCreate'])->name('transferencia_operacao.create');
        Route::post('/transferencia-operacao', [App\Modules\Cash\Controllers\CashController::class, 'transferenciaOperacaoStore'])->name('transferencia_operacao.store');
        Route::get('/movimentacao/{id}', [App\Modules\Cash\Controllers\CashController::class, 'show'])->name('movimentacao.show');
    });

    // Fechamento de Caixa (nova tela unificada)
    Route::prefix('fechamento-caixa')->name('fechamento-caixa.')->group(function () {
        Route::get('/', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'index'])->name('index');
        Route::get('/conferir', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'conferir'])->name('conferir');
        Route::post('/fechar', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'fechar'])->name('fechar');
        Route::get('/{id}', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'show'])->name('show');
        Route::post('/{id}/aprovar', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'aprovar'])->name('aprovar');
        Route::post('/{id}/rejeitar', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'rejeitar'])->name('rejeitar');
        Route::post('/{id}/anexar-comprovante', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'anexarComprovante'])->name('anexar-comprovante');
        Route::post('/{id}/confirmar', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'confirmarRecebimento'])->name('confirmar');
        Route::post('/{id}/marcar-pago-consultor-bloqueado', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'marcarComoPagoConsultorBloqueado'])->name('marcar-pago-consultor-bloqueado');
    });

    // Prestações de Contas (rotas legadas - redirecionam para nova tela)
    Route::get('/prestacoes', fn () => redirect()->route('fechamento-caixa.index'))->name('prestacoes.index');
    Route::get('/prestacoes/{id}', fn ($id) => redirect()->route('fechamento-caixa.show', $id))->name('prestacoes.show');
    Route::post('/prestacoes/{id}/confirmar-recebimento', [App\Modules\Cash\Controllers\FechamentoCaixaController::class, 'confirmarRecebimento'])->name('prestacoes.confirmar-recebimento');

    // Operações (administradores e gestores)
    Route::prefix('operacoes')->name('operacoes.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\OperacaoController::class, 'index'])->name('index');
        Route::get('/create', [App\Modules\Core\Controllers\OperacaoController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Core\Controllers\OperacaoController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Modules\Core\Controllers\OperacaoController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [App\Modules\Core\Controllers\OperacaoController::class, 'edit'])->name('edit');
        Route::put('/{id}', [App\Modules\Core\Controllers\OperacaoController::class, 'update'])->name('update');
    });

    // Usuários (administradores e gestores)
    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\UsuarioController::class, 'index'])->name('index');
        Route::get('/create', [App\Modules\Core\Controllers\UsuarioController::class, 'create'])->name('create');
        Route::post('/', [App\Modules\Core\Controllers\UsuarioController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Modules\Core\Controllers\UsuarioController::class, 'show'])->name('show');
        Route::post('/{id}/atribuir-papel', [App\Modules\Core\Controllers\UsuarioController::class, 'atribuirPapel'])->name('atribuir-papel');
        Route::post('/{id}/remover-papel', [App\Modules\Core\Controllers\UsuarioController::class, 'removerPapel'])->name('remover-papel');
        Route::post('/{id}/atualizar-operacoes', [App\Modules\Core\Controllers\UsuarioController::class, 'atualizarOperacoes'])->name('atualizar-operacoes');
    });

    // Notificações
    Route::prefix('notificacoes')->name('notificacoes.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\NotificacaoController::class, 'listarTodas'])->name('index');
        Route::post('/{id}/marcar-lida', [App\Modules\Core\Controllers\NotificacaoController::class, 'marcarLida'])->name('marcar-lida');
        Route::post('/marcar-todas-lidas', [App\Modules\Core\Controllers\NotificacaoController::class, 'marcarTodasLidas'])->name('marcar-todas-lidas');
    });

    // Notificações (API)
    Route::prefix('api/notificacoes')->name('api.notificacoes.')->group(function () {
        Route::get('/', [App\Modules\Core\Controllers\NotificacaoController::class, 'index'])->name('index');
        Route::get('/contar', [App\Modules\Core\Controllers\NotificacaoController::class, 'contarNaoLidas'])->name('contar');
        Route::post('/{id}/marcar-lida', [App\Modules\Core\Controllers\NotificacaoController::class, 'marcarComoLida'])->name('marcar-lida');
        Route::post('/marcar-todas-lidas', [App\Modules\Core\Controllers\NotificacaoController::class, 'marcarTodasComoLidas'])->name('marcar-todas-lidas');
    });

    // Perfil do usuário
    Route::prefix('perfil')->name('profile.')->group(function () {
        Route::get('/', [App\Http\Controllers\ProfileController::class, 'show'])->name('show');
        Route::get('/operacoes', [App\Http\Controllers\ProfileController::class, 'operacoes'])->name('operacoes');
        Route::put('/operacoes/preferida', [App\Http\Controllers\ProfileController::class, 'updateOperacaoPreferida'])->name('operacoes-preferida');
        Route::put('/', [App\Http\Controllers\ProfileController::class, 'update'])->name('update');
        Route::post('/avatar', [App\Http\Controllers\ProfileController::class, 'updateAvatar'])->name('update-avatar');
        Route::delete('/avatar', [App\Http\Controllers\ProfileController::class, 'removeAvatar'])->name('remove-avatar');
    });

});

// Rota catch-all para outras páginas do template (deve ficar por último)
Route::get('{any}', [App\Http\Controllers\HomeController::class, 'index'])->name('index');
