<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Services\ClienteService;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\EmprestimoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmprestimoController extends Controller
{
    protected EmprestimoService $emprestimoService;
    protected ClienteService $clienteService;

    public function __construct(
        EmprestimoService $emprestimoService,
        ClienteService $clienteService
    ) {
        $this->middleware('auth');
        $this->emprestimoService = $emprestimoService;
        $this->clienteService = $clienteService;
    }

    /**
     * Listar empréstimos
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = Emprestimo::with(['cliente', 'operacao', 'consultor']);

        // Aplicar filtro de operações do usuário (exceto administradores)
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                // Se não tem operações vinculadas, retorna vazio
                $query->whereRaw('1 = 0');
            }
        }

        // Filtros
        if ($request->filled('operacao_id')) {
            // Validar se o usuário tem acesso a essa operação
            if ($user->temAcessoOperacao($request->operacao_id)) {
                $query->where('operacao_id', $request->operacao_id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tipo')) {
            if ($request->tipo === 'outros') {
                $query->whereNotIn('tipo', ['dinheiro', 'price', 'empenho', 'troca_cheque', 'crediario']);
            } else {
                $query->where('tipo', $request->tipo);
            }
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro: apenas com parcelas atrasadas
        if ($request->boolean('apenas_atrasadas')) {
            $query->whereHas('parcelas', fn ($q) => $q->where('status', 'atrasada'));
        }

        // Contadores (respeitam os mesmos filtros da listagem)
        $stats = [
            'total' => (clone $query)->count(),
            'valor_total_emprestado' => (clone $query)->sum('valor_total'),
            'ativos' => (clone $query)->where('status', 'ativo')->count(),
            'pendentes' => (clone $query)->where('status', 'pendente')->count(),
            'com_parcela_atrasada' => (clone $query)->whereHas('parcelas', fn ($q) => $q->where('status', 'atrasada'))->count(),
            'novos_mes' => (clone $query)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
        // Valor a receber (parcelas não pagas dos empréstimos filtrados)
        $stats['valor_a_receber'] = Parcela::whereIn('emprestimo_id', (clone $query)->select('id'))
            ->whereNotIn('status', ['paga', 'quitada_garantia'])
            ->sum(DB::raw('valor - COALESCE(valor_pago, 0)'));

        $emprestimos = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Filtrar operações disponíveis para o usuário
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('emprestimos.index', compact('emprestimos', 'operacoes', 'stats'));
    }

    /**
     * Exportar listagem de empréstimos em CSV (abre no Excel).
     * Respeita os mesmos filtros da listagem (operacao_id, status, tipo, cliente_id).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $query = Emprestimo::with(['cliente', 'operacao', 'consultor']);

        if (! $user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (! empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('operacao_id') && $user->temAcessoOperacao($request->operacao_id)) {
            $query->where('operacao_id', $request->operacao_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tipo')) {
            if ($request->tipo === 'outros') {
                $query->whereNotIn('tipo', ['dinheiro', 'price', 'empenho', 'troca_cheque', 'crediario']);
            } else {
                $query->where('tipo', $request->tipo);
            }
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        // Filtro: apenas com parcelas atrasadas
        if ($request->boolean('apenas_atrasadas')) {
            $query->whereHas('parcelas', fn ($q) => $q->where('status', 'atrasada'));
        }

        $emprestimos = $query->orderBy('created_at', 'desc')->get();

        $filename = 'emprestimos_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($emprestimos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['ID', 'Cliente', 'Operação', 'Valor', 'Status', 'Tipo', 'Data início', 'Consultor'], ';');

            foreach ($emprestimos as $e) {
                fputcsv($out, [
                    $e->id,
                    $e->cliente?->nome ?? '',
                    $e->operacao?->nome ?? '',
                    number_format((float) $e->valor_total, 2, ',', '.'),
                    $e->status,
                    $e->tipo ?? '',
                    $e->data_inicio?->format('d/m/Y') ?? '',
                    $e->consultor?->name ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Mostrar formulário de criação
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode criar empréstimos
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar empréstimos.');
        }
        
        try {
            
            // Filtrar operações disponíveis para o usuário
            if ($user->hasRole('administrador')) {
                $operacoes = Operacao::where('ativo', true)->get();
            } else {
                $operacoesIds = $user->getOperacoesIds();
                $operacoes = !empty($operacoesIds) 
                    ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                    : collect([]);
            }
            
            // Se o usuário tem apenas 1 operação, já vir selecionada
            $operacaoSelecionadaId = $operacoes->count() === 1 ? $operacoes->first()->id : null;
            
            // Verificar se há cliente_id na query string (vindo da página de detalhes do cliente)
            $clientePreSelecionado = null;
            if ($request->has('cliente_id')) {
                $clienteId = $request->input('cliente_id');
                $clientePreSelecionado = Cliente::find($clienteId);
            }
            
            // Verificar se é uma negociação de empréstimo existente
            $negociacao = false;
            $emprestimoOrigem = null;
            $saldoDevedor = null;
            
            if ($request->has('negociacao_de')) {
                $emprestimoOrigemId = (int) $request->input('negociacao_de');
                $emprestimoOrigem = Emprestimo::with(['cliente', 'operacao', 'parcelas'])->find($emprestimoOrigemId);
                
                if ($emprestimoOrigem) {
                    // Verificar permissão
                    if (!$user->hasAnyRole(['administrador', 'gestor'])) {
                        // Consultor só pode negociar seus próprios empréstimos
                        if ($emprestimoOrigem->consultor_id !== $user->id) {
                            abort(403, 'Você só pode negociar seus próprios empréstimos.');
                        }
                    }
                    
                    // Verificar se pode ser negociado
                    if (!$emprestimoOrigem->isAtivo()) {
                        return redirect()->route('emprestimos.show', $emprestimoOrigem->id)
                            ->with('error', 'Apenas empréstimos ativos podem ser negociados.');
                    }

                    // Bloquear negociação de empréstimo que já é negociação
                    if ($emprestimoOrigem->emprestimo_origem_id) {
                        return redirect()->route('emprestimos.show', $emprestimoOrigem->id)
                            ->with('error', 'Este empréstimo já é resultado de uma negociação anterior e não pode ser negociado novamente.');
                    }
                    
                    $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
                    $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimoOrigem);
                    
                    if ($saldoDevedor <= 0) {
                        return redirect()->route('emprestimos.show', $emprestimoOrigem->id)
                            ->with('error', 'Este empréstimo não possui saldo devedor para negociação.');
                    }
                    
                    $negociacao = true;
                    $clientePreSelecionado = $emprestimoOrigem->cliente;
                    $operacaoSelecionadaId = $emprestimoOrigem->operacao_id;
                }
            }
            
            return view('emprestimos.create', compact(
                'operacoes', 
                'clientePreSelecionado', 
                'operacaoSelecionadaId',
                'negociacao',
                'emprestimoOrigem',
                'saldoDevedor'
            ));
        } catch (\Exception $e) {
            \Log::error('Erro ao carregar formulário de empréstimo: ' . $e->getMessage());
            $operacoes = collect([]);
            $clientePreSelecionado = null;
            $operacaoSelecionadaId = null;
            $negociacao = false;
            $emprestimoOrigem = null;
            $saldoDevedor = null;
            return view('emprestimos.create', compact(
                'operacoes', 
                'clientePreSelecionado', 
                'operacaoSelecionadaId',
                'negociacao',
                'emprestimoOrigem',
                'saldoDevedor'
            ));
        }
    }

    /**
     * Criar novo empréstimo
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        // Super Admin não pode criar empréstimos
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar empréstimos.');
        }

        // Normalizar valor_total: pode vir como array (dois campos no form) ou string;
        // aceitar formato BR (1.234,56) e EN (1234.56).
        $valorTotalRaw = $request->input('valor_total');
        if (is_array($valorTotalRaw)) {
            $valorTotalRaw = collect($valorTotalRaw)->map(fn ($v) => is_scalar($v) ? trim((string) $v) : null)
                ->filter(fn ($v) => $v !== '' && $v !== null)->first();
        }
        if (is_string($valorTotalRaw) || is_numeric($valorTotalRaw)) {
            $valorTotalRaw = trim((string) $valorTotalRaw);
            // Formato BR: 1.234,56 ou 1234,56
            if (str_contains($valorTotalRaw, ',')) {
                $valorTotalRaw = str_replace('.', '', $valorTotalRaw);
                $valorTotalRaw = str_replace(',', '.', $valorTotalRaw);
            }
        }
        $request->merge(['valor_total' => $valorTotalRaw !== '' && $valorTotalRaw !== null ? $valorTotalRaw : null]);

        // Normalizar numero_parcelas: o formulário tem dois campos (manual + hidden troca_cheque);
        // PHP pode receber como array — usar o valor correto conforme o tipo.
        $numeroParcelasRaw = $request->input('numero_parcelas');
        if (is_array($numeroParcelasRaw)) {
            $tipo = $request->input('tipo');
            // Ordem no form: primeiro o input visível (dinheiro/price/empenho), depois o hidden (troca_cheque)
            $numeroParcelasRaw = $tipo === 'troca_cheque'
                ? (collect($numeroParcelasRaw)->filter(fn ($v) => $v !== '' && $v !== null)->last() ?? 1)
                : (collect($numeroParcelasRaw)->filter(fn ($v) => $v !== '' && $v !== null)->first() ?? 1);
        }
        $request->merge(['numero_parcelas' => $numeroParcelasRaw]);

        $validated = $request->validate([
            'operacao_id' => 'required|exists:operacoes,id',
            'cliente_id' => 'required|exists:clientes,id',
            'valor_total' => 'required|numeric|min:0.01',
            'numero_parcelas' => 'required|integer|min:1',
            'frequencia' => 'required|in:diaria,semanal,mensal',
            'data_inicio' => 'required|date',
            'tipo' => 'required|in:dinheiro,price,empenho,troca_cheque',
            'taxa_juros' => 'nullable|numeric|min:0|max:100',
            'observacoes' => 'nullable|string',
            // Validação para cheques (troca_cheque)
            'cheques' => 'required_if:tipo,troca_cheque|array|min:1',
            'cheques.*.banco' => 'nullable|string|max:100',
            'cheques.*.agencia' => 'nullable|string|max:20',
            'cheques.*.conta' => 'nullable|string|max:20',
            'cheques.*.numero_cheque' => 'nullable|string|max:50',
            'cheques.*.data_vencimento' => 'required_with:cheques|date|after_or_equal:today',
            'cheques.*.valor_cheque' => 'required_with:cheques|numeric|min:0.01',
            'cheques.*.taxa_juros' => 'nullable|numeric|min:0|max:100',
            'cheques.*.portador' => 'nullable|string|max:255',
            'cheques.*.observacoes' => 'nullable|string|max:1000',
        ]);

        // Validação específica para Price: taxa de juros obrigatória
        if ($validated['tipo'] === 'price' && (empty($validated['taxa_juros']) || $validated['taxa_juros'] <= 0)) {
            return back()->withErrors(['taxa_juros' => 'A taxa de juros é obrigatória para empréstimos do tipo Price.'])->withInput();
        }

        // Validação específica para troca_cheque: deve ter pelo menos um cheque
        if ($validated['tipo'] === 'troca_cheque') {
            if (empty($validated['cheques']) || count($validated['cheques']) < 1) {
                return back()->withErrors(['cheques' => 'Troca de cheque precisa ter pelo menos um cheque cadastrado.'])->withInput();
            }
        }

        $validated['consultor_id'] = $user->id;

        // Validar se o usuário tem acesso à operação selecionada
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($validated['operacao_id'])) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }

        // Verificar se é uma negociação
        $negociacaoEmprestimoId = $request->input('negociacao_emprestimo_id');
        
        if ($negociacaoEmprestimoId) {
            return $this->processarNegociacao($request, $user, $validated, (int) $negociacaoEmprestimoId);
        }

        try {
            $emprestimo = $this->emprestimoService->criar($validated);

            $mensagem = $emprestimo->status === 'pendente'
                ? 'Empréstimo criado e aguardando aprovação.'
                : 'Empréstimo criado e aprovado automaticamente!';

            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('success', $mensagem);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao criar empréstimo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao criar empréstimo: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Processar negociação de empréstimo
     */
    protected function processarNegociacao(Request $request, $user, array $validated, int $emprestimoOrigemId): RedirectResponse
    {
        $motivo = $request->input('motivo_negociacao');
        
        if (empty($motivo)) {
            return back()->withErrors(['motivo_negociacao' => 'O motivo da negociação é obrigatório.'])->withInput();
        }

        $emprestimoOrigem = Emprestimo::with(['operacao', 'cliente'])->findOrFail($emprestimoOrigemId);

        // Verificar permissão
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            if ($emprestimoOrigem->consultor_id !== $user->id) {
                return back()->with('error', 'Você só pode negociar seus próprios empréstimos.')->withInput();
            }
        }

        // Dados do novo empréstimo
        $dadosNovoEmprestimo = [
            'tipo' => $validated['tipo'],
            'frequencia' => $validated['frequencia'],
            'taxa_juros' => $validated['taxa_juros'] ?? 0,
            'numero_parcelas' => $validated['numero_parcelas'],
            'data_inicio' => $validated['data_inicio'],
            'observacoes' => $validated['observacoes'] ?? null,
        ];

        try {
            // Gestor/Admin executa direto
            if ($user->hasAnyRole(['administrador', 'gestor'])) {
                $novoEmprestimo = $this->emprestimoService->negociar(
                    $emprestimoOrigemId,
                    $dadosNovoEmprestimo,
                    $motivo
                );

                return redirect()->route('emprestimos.show', $novoEmprestimo->id)
                    ->with('success', "Negociação realizada com sucesso! Novo empréstimo #{$novoEmprestimo->id} criado a partir do empréstimo #{$emprestimoOrigemId}.");
            }

            // Consultor cria solicitação
            $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
            $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimoOrigem);

            $solicitacao = \App\Modules\Loans\Models\SolicitacaoNegociacao::create([
                'emprestimo_id' => $emprestimoOrigemId,
                'consultor_id' => $user->id,
                'operacao_id' => $emprestimoOrigem->operacao_id,
                'saldo_devedor' => $saldoDevedor,
                'dados_novo_emprestimo' => $dadosNovoEmprestimo,
                'motivo' => $motivo,
                'status' => 'pendente',
            ]);

            // Notificar gestores e admins
            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            
            $notificacaoService->criarParaRole('gestor', [
                'tipo' => 'negociacao_pendente',
                'titulo' => 'Nova Solicitação de Negociação',
                'mensagem' => "{$user->name} solicitou negociação do empréstimo #{$emprestimoOrigemId} - Cliente: {$emprestimoOrigem->cliente->nome}. Saldo devedor: R$ " . number_format($saldoDevedor, 2, ',', '.'),
                'url' => route('liberacoes.negociacoes'),
                'dados' => ['solicitacao_id' => $solicitacao->id],
            ]);

            $notificacaoService->criarParaRole('administrador', [
                'tipo' => 'negociacao_pendente',
                'titulo' => 'Nova Solicitação de Negociação',
                'mensagem' => "{$user->name} solicitou negociação do empréstimo #{$emprestimoOrigemId} - Cliente: {$emprestimoOrigem->cliente->nome}. Saldo devedor: R$ " . number_format($saldoDevedor, 2, ',', '.'),
                'url' => route('liberacoes.negociacoes'),
                'dados' => ['solicitacao_id' => $solicitacao->id],
            ]);

            return redirect()->route('emprestimos.show', $emprestimoOrigemId)
                ->with('success', 'Solicitação de negociação enviada para aprovação do gestor/administrador.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao processar negociação: ' . $e->getMessage());
            return back()->with('error', 'Erro ao processar negociação: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar detalhes do empréstimo
     */
    public function show(int $id): View
    {
        $emprestimo = Emprestimo::with([
            'cliente',
            'operacao',
            'consultor',
            'aprovador',
            'parcelas' => function ($query) {
                $query->orderBy('numero');
            },
            'parcelas.pagamentos.consultor',
            'parcelas.pagamentos.produtoObjetoItens',
            'parcelas.pagamentos.rejeitadoPor',
            'aprovacao.aprovador',
            'liberacao.consultor',
            'liberacao.gestor',
            'emprestimoOrigem',
            'renovacoes',
            'garantias.anexos', // Garantias para empréstimo tipo empenho
            'cheques', // Cheques para empréstimo tipo troca_cheque
        ])->findOrFail($id);

        return view('emprestimos.show', compact('emprestimo'));
    }

    /**
     * Renovar empréstimo (pagar apenas juros e gerar novo empréstimo)
     */
    public function renovar(int $id): RedirectResponse
    {
        $user = auth()->user();
        $emprestimo = Emprestimo::findOrFail($id);

        // Verificar se o consultor é dono do empréstimo (ou é gestor/admin)
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            // Consultor só pode renovar seus próprios empréstimos
            if ($emprestimo->consultor_id !== $user->id) {
                abort(403, 'Você só pode renovar seus próprios empréstimos.');
            }
        }

        try {
            $novoEmprestimo = $this->emprestimoService->renovar($id);

            return redirect()
                ->route('emprestimos.show', $novoEmprestimo->id)
                ->with(
                    'success',
                    "Empréstimo renovado com sucesso! Este empréstimo é a renovação do empréstimo #{$id}."
                );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Erro ao renovar empréstimo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao renovar empréstimo: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar empréstimo (apenas administradores)
     */
    public function cancelar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();

        // Apenas administradores podem cancelar
        if (!$user->hasRole('administrador')) {
            abort(403, 'Apenas administradores podem cancelar empréstimos.');
        }

        $request->validate([
            'motivo_cancelamento' => 'required|string|min:10|max:1000',
        ], [
            'motivo_cancelamento.required' => 'O motivo do cancelamento é obrigatório.',
            'motivo_cancelamento.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo_cancelamento.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ]);

        try {
            $this->emprestimoService->cancelar($id, $user->id, $request->motivo_cancelamento);

            return redirect()
                ->route('emprestimos.show', $id)
                ->with('success', 'Empréstimo cancelado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Erro ao cancelar empréstimo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao cancelar empréstimo: ' . $e->getMessage());
        }
    }

    /**
     * Executar garantia de empréstimo tipo empenho
     */
    public function executarGarantia(Request $request, int $id, int $garantiaId): RedirectResponse
    {
        $user = auth()->user();

        // Apenas gestores e administradores podem executar garantias
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            abort(403, 'Apenas gestores e administradores podem executar garantias.');
        }

        $request->validate([
            'observacoes' => 'required|string|min:10|max:1000',
        ]);

        try {
            $garantia = $this->emprestimoService->executarGarantia(
                $id,
                $garantiaId,
                $user->id,
                $request->input('observacoes')
            );

            return redirect()
                ->route('emprestimos.show', $id)
                ->with('success', 'Garantia executada com sucesso! O empréstimo foi finalizado automaticamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao executar garantia: ' . $e->getMessage());
            return back()->with('error', 'Erro ao executar garantia: ' . $e->getMessage());
        }
    }
}
