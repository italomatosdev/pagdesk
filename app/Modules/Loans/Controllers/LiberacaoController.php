<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\PagamentoProdutoObjetoItem;
use App\Modules\Loans\Models\SolicitacaoNegociacao;
use App\Modules\Loans\Models\SolicitacaoPagamentoJurosContratoReduzido;
use App\Modules\Loans\Models\SolicitacaoPagamentoJurosParcial;
use App\Modules\Loans\Models\SolicitacaoRenovacaoAbate;
use App\Modules\Loans\Services\EmprestimoService;
use App\Modules\Loans\Services\LiberacaoService;
use App\Modules\Loans\Services\PagamentoService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LiberacaoController extends Controller
{
    protected LiberacaoService $liberacaoService;
    protected PagamentoService $pagamentoService;

    public function __construct(LiberacaoService $liberacaoService, PagamentoService $pagamentoService)
    {
        $this->middleware('auth');
        $this->liberacaoService = $liberacaoService;
        $this->pagamentoService = $pagamentoService;
    }

    /**
     * Listar liberações aguardando (Gestor)
     */
    public function index(Request $request): View
    {
        // Apenas gestor ou administrador
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem ver liberações.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');
        
        // Validar se o usuário tem acesso à operação selecionada (Super Admin vê todas; demais só as vinculadas)
        if ($operacaoId && !$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds) || !in_array((int) $operacaoId, $operacoesIds, true)) {
                $operacaoId = null;
            }
        }

        $liberacoes = $this->liberacaoService->listarAguardando($operacaoId, $user);

        // Operações disponíveis no filtro: Super Admin vê todas; demais só as vinculadas
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('liberacoes.index', compact('liberacoes', 'operacoes', 'operacaoId'));
    }

    /**
     * Liberar dinheiro (Gestor)
     */
    public function liberar(Request $request, int $id): RedirectResponse
    {
        // Apenas gestor ou administrador
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        // Upload de comprovante
        $comprovantePath = null;
        if ($request->hasFile('comprovante')) {
            $comprovantePath = $request->file('comprovante')->store('comprovantes/liberacoes', 'public');
        }

        try {
            $this->liberacaoService->liberar(
                $id,
                auth()->id(),
                $validated['observacoes'] ?? null,
                $comprovantePath
            );

            return redirect()->route('liberacoes.index')
                ->with('success', 'Dinheiro liberado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao liberar dinheiro: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Liberar dinheiro em lote (Gestor/Admin)
     */
    public function liberarLote(Request $request): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $liberacaoIds = $request->input('liberacao_ids', []);
        if (!is_array($liberacaoIds)) {
            $liberacaoIds = $liberacaoIds ? [$liberacaoIds] : [];
        }

        $validated = $request->validate([
            'liberacao_ids' => 'required|array',
            'liberacao_ids.*' => 'integer|exists:emprestimo_liberacoes,id',
            'observacoes' => 'nullable|string|max:1000',
            'comprovante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ], [
            'liberacao_ids.required' => 'Selecione ao menos uma liberação.',
            'comprovante.required' => 'O comprovante é obrigatório para liberação em lote.',
        ]);

        $comprovantePath = null;
        if ($request->hasFile('comprovante')) {
            $comprovantePath = $request->file('comprovante')->store('comprovantes/liberacoes', 'public');
        }

        try {
            $liberadas = $this->liberacaoService->liberarLote(
                $validated['liberacao_ids'],
                auth()->id(),
                $validated['observacoes'] ?? null,
                $comprovantePath
            );

            $qtd = count($liberadas);
            return redirect()->route('liberacoes.index')
                ->with('success', $qtd === 1
                    ? 'Dinheiro liberado com sucesso!'
                    : "{$qtd} liberações realizadas com sucesso!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao liberar em lote: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Listar liberações recebidas (Consultor)
     */
    public function minhasLiberacoes(Request $request): View
    {
        $user = auth()->user();
        $status = $request->input('status');
        $operacaoId = $request->input('operacao_id');
        if ($operacaoId !== null && $operacaoId !== '' && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null;
        }
        $liberacoes = $this->liberacaoService->listarPorConsultor($user->id, $status, $operacaoId);

        if ($user->isSuperAdmin()) {
            $operacoes = \App\Modules\Core\Models\Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? \App\Modules\Core\Models\Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('liberacoes.consultor', compact('liberacoes', 'status', 'operacoes', 'operacaoId'));
    }

    /**
     * Exibir detalhes da liberação
     */
    public function show(int $id): View
    {
        $liberacao = \App\Modules\Loans\Models\LiberacaoEmprestimo::with([
            'emprestimo.cliente',
            'emprestimo.operacao',
            'emprestimo.consultor',
            'consultor',
            'gestor'
        ])->findOrFail($id);

        $user = auth()->user();

        // Verificar permissões
        if ($user->hasRole('consultor')) {
            // Consultor só pode ver suas próprias liberações
            if ($liberacao->consultor_id !== $user->id) {
                abort(403, 'Acesso negado.');
            }
        } elseif ($user->hasAnyRole(['gestor', 'administrador'])) {
            // Gestor e admin podem ver todas, mas validar acesso à operação
            if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($liberacao->emprestimo->operacao_id)) {
                abort(403, 'Acesso negado.');
            }
        } else {
            abort(403, 'Acesso negado.');
        }

        return view('liberacoes.show', compact('liberacao'));
    }

    /**
     * Confirmar pagamento ao cliente (Consultor)
     */
    public function confirmarPagamento(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        // Upload de comprovante
        $comprovantePath = null;
        if ($request->hasFile('comprovante')) {
            $comprovantePath = $request->file('comprovante')->store('comprovantes/pagamentos-cliente', 'public');
        }

        try {
            $this->liberacaoService->confirmarPagamentoCliente(
                $id,
                auth()->id(),
                $validated['observacoes'] ?? null,
                $comprovantePath
            );

            // Redirecionar para a página de origem (empréstimo ou minhas liberações)
            $emprestimoId = \App\Modules\Loans\Models\LiberacaoEmprestimo::find($id)->emprestimo_id;
            $redirectTo = $request->input('redirect_to', 'emprestimos.show');
            
            if ($redirectTo === 'emprestimos.show') {
                return redirect()->route('emprestimos.show', $emprestimoId)
                    ->with('success', 'Pagamento ao cliente confirmado com sucesso!');
            }
            
            return redirect()->route('liberacoes.minhas')
                ->with('success', 'Pagamento ao cliente confirmado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao confirmar pagamento: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Anexar comprovante de liberação depois (somente se ainda não tiver).
     * Apenas gestor/administrador. Não permite editar/substituir comprovante existente.
     */
    public function anexarComprovanteLiberacao(Request $request, int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $request->validate([
            'comprovante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $comprovantePath = $request->file('comprovante')->store('comprovantes/liberacoes', 'public');

        try {
            $this->liberacaoService->anexarComprovanteLiberacao($id, $comprovantePath);
            $liberacao = \App\Modules\Loans\Models\LiberacaoEmprestimo::find($id);
            $redirectTo = $request->input('redirect_to', 'liberacoes.show');
            if ($redirectTo === 'emprestimos.show' && $liberacao) {
                return redirect()->route('emprestimos.show', $liberacao->emprestimo_id)
                    ->with('success', 'Comprovante de liberação anexado com sucesso.');
            }
            return redirect()->route('liberacoes.show', $id)
                ->with('success', 'Comprovante de liberação anexado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Anexar comprovante de pagamento ao cliente depois (somente se ainda não tiver).
     * Apenas o consultor dono da liberação. Não permite editar/substituir comprovante existente.
     */
    public function anexarComprovantePagamentoCliente(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $liberacao = \App\Modules\Loans\Models\LiberacaoEmprestimo::findOrFail($id);
        if ($liberacao->consultor_id !== $user->id) {
            abort(403, 'Você só pode anexar comprovante nas suas próprias liberações.');
        }

        $request->validate([
            'comprovante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $comprovantePath = $request->file('comprovante')->store('comprovantes/pagamentos-cliente', 'public');

        try {
            $this->liberacaoService->anexarComprovantePagamentoCliente($id, $user->id, $comprovantePath);
            $redirectTo = $request->input('redirect_to', 'liberacoes.show');
            if ($redirectTo === 'emprestimos.show') {
                return redirect()->route('emprestimos.show', $liberacao->emprestimo_id)
                    ->with('success', 'Comprovante de pagamento anexado com sucesso.');
            }
            return redirect()->route('liberacoes.show', $id)
                ->with('success', 'Comprovante de pagamento anexado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Listar itens de produto/objeto recebidos (estoque) — gestor/adm
     */
    public function produtosObjetoRecebidos(Request $request): View
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');
        $status = $request->input('status', 'todos'); // todos | aceito | pendente | rejeitado
        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = PagamentoProdutoObjetoItem::with([
            'pagamento.parcela.emprestimo.cliente',
            'pagamento.parcela.emprestimo.operacao',
            'pagamento.consultor',
        ])->whereHas('pagamento.parcela.emprestimo', function ($q) use ($user) {
            $q->where('empresa_id', $user->empresa_id);
            if (!$user->isSuperAdmin()) {
                $opsIds = $user->getOperacoesIds();
                if (!empty($opsIds)) {
                    $q->whereIn('operacao_id', $opsIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
            }
        });

        if ($operacaoId) {
            $query->whereHas('pagamento.parcela.emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        if ($status === 'aceito') {
            $query->whereHas('pagamento', fn ($q) => $q->whereNotNull('aceite_gestor_id'));
        } elseif ($status === 'pendente') {
            $query->whereHas('pagamento', fn ($q) => $q->whereNull('aceite_gestor_id')->whereNull('rejeitado_por_id'));
        } elseif ($status === 'rejeitado') {
            $query->whereHas('pagamento', fn ($q) => $q->whereNotNull('rejeitado_por_id'));
        }

        $itens = $query->orderByDesc('id')->paginate(20)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('liberacoes.produtos-objeto-recebidos', compact('itens', 'operacoes', 'operacaoId', 'status'));
    }

    /**
     * Listar pagamentos em produto/objeto aguardando aceite (gestor/adm)
     */
    public function pagamentosProdutoObjeto(Request $request): View
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aceitar pagamentos em produto/objeto.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');
        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = Pagamento::with(['parcela.emprestimo.cliente', 'parcela.emprestimo.operacao', 'consultor', 'produtoObjetoItens'])
            ->where('metodo', Pagamento::METODO_PRODUTO_OBJETO)
            ->whereNull('aceite_gestor_id')
            ->whereNull('rejeitado_por_id');

        if ($operacaoId) {
            $query->whereHas('parcela.emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $operacoesIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $pagamentos = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('liberacoes.pagamentos-produto-objeto', compact('pagamentos', 'operacoes', 'operacaoId'));
    }

    /**
     * Aceitar pagamento em produto/objeto (gestor/adm)
     */
    public function aceitarPagamentoProdutoObjeto(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $pagamento = Pagamento::with('parcela.emprestimo')->findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $pagamento->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $this->pagamentoService->aceitarPagamentoProdutoObjeto($id, $user->id);
            return redirect()->route('liberacoes.pagamentos-produto-objeto')
                ->with('success', 'Pagamento em produto/objeto aceito. A parcela foi creditada (sem movimentação de caixa).');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aceitar: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar pagamento em produto/objeto (gestor/adm). Pagamento continua pendente.
     */
    public function rejeitarPagamentoProdutoObjeto(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $pagamento = Pagamento::with('parcela.emprestimo')->findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $pagamento->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $this->pagamentoService->rejeitarPagamentoProdutoObjeto($id, $user->id);
            return redirect()->route('liberacoes.pagamentos-produto-objeto')
                ->with('success', 'Pagamento em produto/objeto rejeitado. A parcela permanece pendente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao rejeitar: ' . $e->getMessage());
        }
    }

    /**
     * Listar solicitações de pagamento com juros parcial (abaixo do devido) – aguardando aprovação.
     */
    public function solicitacoesJurosParcial(Request $request): View
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar solicitações de juros parcial.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');

        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = SolicitacaoPagamentoJurosParcial::with([
            'parcela.emprestimo.cliente',
            'parcela.emprestimo.operacao',
            'consultor',
        ])->where('status', 'aguardando');

        if ($operacaoId) {
            $query->whereHas('parcela.emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('liberacoes.solicitacoes-juros-parcial', compact('solicitacoes', 'operacoes', 'operacaoId'));
    }

    /**
     * Aprovar solicitação de pagamento com juros parcial: registra o pagamento e atualiza a solicitação.
     */
    public function aprovarSolicitacaoJurosParcial(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoPagamentoJurosParcial::with('parcela.emprestimo')->findOrFail($id);
        if (!$solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $this->pagamentoService->registrar($solicitacao->toDadosPagamento());
            $solicitacao->update([
                'status' => 'aprovado',
                'aprovado_por_id' => $user->id,
                'aprovado_em' => now(),
            ]);
            return redirect()->route('liberacoes.juros-parcial')
                ->with('success', 'Solicitação aprovada. O pagamento foi registrado na parcela.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de pagamento com juros parcial.
     */
    public function rejeitarSolicitacaoJurosParcial(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoPagamentoJurosParcial::with('parcela.emprestimo')->findOrFail($id);
        if (!$solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        $solicitacao->update([
            'status' => 'rejeitado',
            'rejeitado_por_id' => $user->id,
            'rejeitado_em' => now(),
        ]);
        return redirect()->route('liberacoes.juros-parcial')
            ->with('success', 'Solicitação rejeitada. O consultor pode registrar um novo pagamento com o valor de juros devido.');
    }

    /**
     * Listar solicitações de pagamento com valor inferior (juros do contrato reduzido) – aguardando aprovação.
     */
    public function solicitacoesJurosContratoReduzido(Request $request): View
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar estas solicitações.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');

        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = SolicitacaoPagamentoJurosContratoReduzido::with([
            'parcela.emprestimo.cliente',
            'parcela.emprestimo.operacao',
            'consultor',
        ])->where('status', 'aguardando');

        if ($operacaoId) {
            $query->whereHas('parcela.emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('liberacoes.solicitacoes-juros-contrato-reduzido', compact('solicitacoes', 'operacoes', 'operacaoId'));
    }

    /**
     * Aprovar solicitação de pagamento com valor inferior: registra o pagamento e atualiza a solicitação.
     */
    public function aprovarSolicitacaoJurosContratoReduzido(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoPagamentoJurosContratoReduzido::with('parcela.emprestimo')->findOrFail($id);
        if (!$solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $this->pagamentoService->registrar($solicitacao->toDadosPagamento());
            $solicitacao->update([
                'status' => 'aprovado',
                'aprovado_por_id' => $user->id,
                'aprovado_em' => now(),
            ]);
            return redirect()->route('liberacoes.juros-contrato-reduzido')
                ->with('success', 'Solicitação aprovada. O pagamento foi registrado na parcela.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de pagamento com valor inferior.
     */
    public function rejeitarSolicitacaoJurosContratoReduzido(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoPagamentoJurosContratoReduzido::with('parcela.emprestimo')->findOrFail($id);
        if (!$solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        $solicitacao->update([
            'status' => 'rejeitado',
            'rejeitado_por_id' => $user->id,
            'rejeitado_em' => now(),
        ]);
        return redirect()->route('liberacoes.juros-contrato-reduzido')
            ->with('success', 'Solicitação rejeitada. O consultor pode registrar um novo pagamento.');
    }

    /**
     * Listar solicitações de renovação com abate (valor inferior ao principal) – aguardando aprovação.
     */
    public function solicitacoesRenovacaoAbate(Request $request): View
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar estas solicitações.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');

        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = SolicitacaoRenovacaoAbate::with([
            'parcela.emprestimo.cliente',
            'parcela.emprestimo.operacao',
            'consultor',
        ])->where('status', 'aguardando');

        if ($operacaoId) {
            $query->whereHas('parcela.emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('liberacoes.solicitacoes-renovacao-abate', compact('solicitacoes', 'operacoes', 'operacaoId'));
    }

    /**
     * Aprovar solicitação de renovação com abate: executa a renovação com o valor da solicitação.
     */
    public function aprovarSolicitacaoRenovacaoAbate(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoRenovacaoAbate::with('parcela.emprestimo')->findOrFail($id);
        if (!$solicitacao->isAguardando()) {
            return redirect()->route('liberacoes.renovacao-abate')->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $emprestimoService = app(EmprestimoService::class);
            $dataPagamento = $solicitacao->data_pagamento instanceof \Carbon\Carbon
                ? $solicitacao->data_pagamento->format('Y-m-d')
                : $solicitacao->data_pagamento;
            $novoEmprestimo = $emprestimoService->renovar(
                $solicitacao->parcela->emprestimo_id,
                false,
                null,
                null,
                null,
                (float) $solicitacao->valor,
                $solicitacao->metodo,
                $dataPagamento,
                true
            );
            $solicitacao->update([
                'status' => 'aprovado',
                'aprovado_por_id' => $user->id,
                'aprovado_em' => now(),
            ]);

            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            if ($solicitacao->consultor_id) {
                $notificacaoService->criar([
                    'user_id' => $solicitacao->consultor_id,
                    'tipo' => 'renovacao_abate_aprovada',
                    'titulo' => 'Renovação com abate aprovada',
                    'mensagem' => 'Sua solicitação de renovação com abate do empréstimo #' . $solicitacao->parcela->emprestimo_id . ' foi aprovada. O novo empréstimo #' . $novoEmprestimo->id . ' foi criado.',
                    'url' => route('emprestimos.show', $novoEmprestimo->id),
                    'dados' => ['emprestimo_id' => $novoEmprestimo->id, 'emprestimo_origem_id' => $solicitacao->parcela->emprestimo_id],
                ]);
            }

            return redirect()->route('liberacoes.renovacao-abate')
                ->with('success', 'Solicitação aprovada. A renovação foi realizada e o novo empréstimo #' . $novoEmprestimo->id . ' foi criado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return redirect()->route('liberacoes.renovacao-abate')->with('error', $msg);
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.renovacao-abate')->with('error', 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de renovação com abate.
     */
    public function rejeitarSolicitacaoRenovacaoAbate(int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoRenovacaoAbate::with('parcela.emprestimo')->findOrFail($id);
        if (!$solicitacao->isAguardando()) {
            return redirect()->route('liberacoes.renovacao-abate')->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->parcela->emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        $solicitacao->update([
            'status' => 'rejeitado',
            'rejeitado_por_id' => $user->id,
            'rejeitado_em' => now(),
        ]);

        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        $emprestimoId = $solicitacao->parcela->emprestimo_id;
        if ($solicitacao->consultor_id) {
            $notificacaoService->criar([
                'user_id' => $solicitacao->consultor_id,
                'tipo' => 'renovacao_abate_rejeitada',
                'titulo' => 'Renovação com abate rejeitada',
                'mensagem' => 'Sua solicitação de renovação com abate do empréstimo #' . $emprestimoId . ' foi rejeitada. Você pode enviar uma nova solicitação.',
                'url' => route('emprestimos.show', $emprestimoId),
                'dados' => ['emprestimo_id' => $emprestimoId],
            ]);
        }

        return redirect()->route('liberacoes.renovacao-abate')
            ->with('success', 'Solicitação rejeitada. O consultor pode enviar uma nova solicitação.');
    }

    /**
     * Listar solicitações de negociação pendentes
     */
    public function negociacoes(Request $request): View
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar negociações.');
        }

        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');

        if ($operacaoId && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = SolicitacaoNegociacao::with([
            'emprestimo.cliente',
            'emprestimo.operacao',
            'consultor',
            'operacao',
        ])->where('status', 'pendente');

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereIn('operacao_id', $opsIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('liberacoes.negociacoes', compact('solicitacoes', 'operacoes', 'operacaoId'));
    }

    /**
     * Aprovar solicitação de negociação
     */
    public function aprovarNegociacao(Request $request, int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoNegociacao::with('emprestimo')->findOrFail($id);
        if (!$solicitacao->isPendente()) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $emprestimoService = app(EmprestimoService::class);
            $observacao = $request->input('observacao');
            
            $novoEmprestimo = $emprestimoService->aprovarNegociacao($id, $user->id, $observacao);
            
            return redirect()->route('liberacoes.negociacoes')
                ->with('success', "Negociação aprovada! Novo empréstimo #{$novoEmprestimo->id} criado.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return redirect()->route('liberacoes.negociacoes')->with('error', $msg);
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de negociação
     */
    public function rejeitarNegociacao(Request $request, int $id): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        $solicitacao = SolicitacaoNegociacao::with('emprestimo')->findOrFail($id);
        if (!$solicitacao->isPendente()) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Esta solicitação já foi processada.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $solicitacao->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        try {
            $emprestimoService = app(EmprestimoService::class);
            $observacao = $request->input('observacao');
            
            $emprestimoService->rejeitarNegociacao($id, $user->id, $observacao);
            
            return redirect()->route('liberacoes.negociacoes')
                ->with('success', 'Solicitação de negociação rejeitada.');
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Erro ao rejeitar: ' . $e->getMessage());
        }
    }
}
