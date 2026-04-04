<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Services\OperacaoDadosClienteService;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\PagamentoProdutoObjetoItem;
use App\Modules\Loans\Models\SolicitacaoNegociacao;
use App\Modules\Loans\Models\SolicitacaoPagamentoDiariaParcial;
use App\Modules\Loans\Models\SolicitacaoPagamentoJurosContratoReduzido;
use App\Modules\Loans\Models\SolicitacaoPagamentoJurosParcial;
use App\Modules\Loans\Models\SolicitacaoRenovacaoAbate;
use App\Modules\Loans\Services\EmprestimoService;
use App\Modules\Loans\Services\LiberacaoService;
use App\Modules\Loans\Services\PagamentoService;
use App\Support\ClienteNomeExibicao;
use App\Support\ClienteRenovacaoCreditoLookup;
use App\Support\ClienteVinculosOperacoesLookup;
use App\Support\FichaContatoLookup;
use App\Support\OperacaoPreferida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiberacaoController extends Controller
{
    protected LiberacaoService $liberacaoService;

    protected PagamentoService $pagamentoService;

    protected OperacaoDadosClienteService $operacaoDadosClienteService;

    public function __construct(
        LiberacaoService $liberacaoService,
        PagamentoService $pagamentoService,
        OperacaoDadosClienteService $operacaoDadosClienteService
    ) {
        $this->middleware('auth');
        $this->liberacaoService = $liberacaoService;
        $this->pagamentoService = $pagamentoService;
        $this->operacaoDadosClienteService = $operacaoDadosClienteService;
    }

    /**
     * Listar liberações aguardando (Gestor)
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem ver liberações.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($operacoesIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIdsList)->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds) || ! in_array((int) $operacaoId, $operacoesIds, true)) {
                $operacaoId = null;
            }
        }

        $liberacoes = $this->liberacaoService->listarAguardando($operacaoId, $user);

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromEmprestimos(
            $liberacoes->map(fn ($l) => $l->emprestimo)->filter()
        );

        $cpfs = $liberacoes
            ->map(fn ($l) => ClienteVinculosOperacoesLookup::cpfFromDocumento($l->emprestimo?->cliente?->documento))
            ->filter()
            ->values()
            ->all();
        $operacoesIdsPorCpf = ClienteVinculosOperacoesLookup::operacoesIdsPorCpf($cpfs);

        $outrosVinculosPorLiberacaoId = [];
        foreach ($liberacoes as $l) {
            $cpf = ClienteVinculosOperacoesLookup::cpfFromDocumento($l->emprestimo?->cliente?->documento);
            if (! $cpf) {
                $outrosVinculosPorLiberacaoId[$l->id] = 0;

                continue;
            }
            $ops = $operacoesIdsPorCpf[$cpf] ?? [];
            $outros = array_values(array_filter($ops, fn ($opId) => (int) $opId !== (int) $l->emprestimo?->operacao_id));
            $outrosVinculosPorLiberacaoId[$l->id] = count($outros);
        }

        $ehRenovacaoPorEmprestimoId = ClienteRenovacaoCreditoLookup::mapEhRenovacaoPorEmprestimoId(
            $liberacoes->map(fn ($l) => $l->emprestimo)->filter()
        );

        return view('liberacoes.index', compact(
            'liberacoes',
            'operacoes',
            'operacaoId',
            'fichasContatoPorClienteOperacao',
            'outrosVinculosPorLiberacaoId',
            'ehRenovacaoPorEmprestimoId'
        ));
    }

    /**
     * Liberar dinheiro (Gestor)
     */
    public function liberar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $liberacao = \App\Modules\Loans\Models\LiberacaoEmprestimo::with('emprestimo')->findOrFail($id);
        if (! $user->temAlgumPapelNaOperacao($liberacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
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

            $redirectEmprestimoId = $request->input('redirect_emprestimo_id');
            if ($redirectEmprestimoId && is_numeric($redirectEmprestimoId)) {
                return redirect()->route('emprestimos.show', (int) $redirectEmprestimoId)
                    ->with('success', 'Dinheiro liberado com sucesso!');
            }

            return redirect()->route('liberacoes.index')
                ->with('success', 'Dinheiro liberado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao liberar dinheiro: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Liberar dinheiro em lote (Gestor/Admin)
     */
    public function liberarLote(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado.');
        }

        $liberacaoIds = $request->input('liberacao_ids', []);
        if (! is_array($liberacaoIds)) {
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
            return back()->with('error', 'Erro ao liberar em lote: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Listar liberações recebidas (Consultor)
     */
    public function minhasLiberacoes(Request $request): View
    {
        $user = auth()->user();
        $status = $request->input('status');

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = ! empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId !== null && ! $user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null;
        }

        $liberacoes = $this->liberacaoService->listarPorConsultor($user->id, $status, $operacaoId);

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromEmprestimos(
            $liberacoes->map(fn ($l) => $l->emprestimo)->filter()
        );

        $ehRenovacaoPorEmprestimoId = ClienteRenovacaoCreditoLookup::mapEhRenovacaoPorEmprestimoId(
            $liberacoes->map(fn ($l) => $l->emprestimo)->filter()
        );

        return view('liberacoes.consultor', compact(
            'liberacoes',
            'status',
            'operacoes',
            'operacaoId',
            'fichasContatoPorClienteOperacao',
            'ehRenovacaoPorEmprestimoId'
        ));
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
            'gestor',
            'confirmadoPagamentoPor',
        ])->findOrFail($id);

        $user = auth()->user();
        $operacaoId = $liberacao->emprestimo->operacao_id;

        if ($liberacao->consultor_id === $user->id) {
            // Dono da liberação (consultor) pode ver
        } elseif ($user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            // Gestor ou admin na operação pode ver
        } else {
            abort(403, 'Acesso negado.');
        }

        $podeAprovarLiberacao = $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador']);
        $podeConfirmarPagamentoCliente = ($liberacao->consultor_id === $user->id)
            || $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador']);
        $ehGestorAdminConfirmando = $podeConfirmarPagamentoCliente && $liberacao->consultor_id !== $user->id;

        $fichaContatoLiberacao = $this->operacaoDadosClienteService->obterParaOperacao(
            (int) $liberacao->emprestimo->cliente_id,
            (int) $liberacao->emprestimo->operacao_id
        );

        $nomeClienteExibicao = ClienteNomeExibicao::fromFicha($fichaContatoLiberacao, $liberacao->emprestimo->cliente);

        $cpf = ClienteVinculosOperacoesLookup::cpfFromDocumento($liberacao->emprestimo->cliente?->documento);
        $vinculosOutrasOperacoesCount = 0;
        if ($cpf) {
            $operacoesIdsPorCpf = ClienteVinculosOperacoesLookup::operacoesIdsPorCpf([$cpf]);
            $ops = $operacoesIdsPorCpf[$cpf] ?? [];
            $outros = array_values(array_filter($ops, fn ($opId) => (int) $opId !== (int) $liberacao->emprestimo->operacao_id));
            $vinculosOutrasOperacoesCount = count($outros);
        }

        $alertaRenovacaoCredito = ClienteRenovacaoCreditoLookup::emprestimoEhRenovacao($liberacao->emprestimo);

        return view('liberacoes.show', compact(
            'liberacao',
            'podeAprovarLiberacao',
            'podeConfirmarPagamentoCliente',
            'ehGestorAdminConfirmando',
            'fichaContatoLiberacao',
            'nomeClienteExibicao',
            'vinculosOutrasOperacoesCount',
            'alertaRenovacaoCredito'
        ));
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
            return back()->with('error', 'Erro ao confirmar pagamento: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Anexar comprovante de liberação depois (somente se ainda não tiver).
     * Apenas gestor/administrador na operação. Não permite editar/substituir comprovante existente.
     */
    public function anexarComprovanteLiberacao(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $liberacao = \App\Modules\Loans\Models\LiberacaoEmprestimo::with('emprestimo')->findOrFail($id);
        if (! $user->temAlgumPapelNaOperacao($liberacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
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
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($operacoesIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIdsList)->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $status = $request->input('status', 'todos'); // todos | aceito | pendente | rejeitado

        $query = PagamentoProdutoObjetoItem::with([
            'pagamento.parcela.emprestimo.cliente',
            'pagamento.parcela.emprestimo.operacao',
            'pagamento.consultor',
        ])->whereHas('pagamento.parcela.emprestimo', function ($q) use ($user) {
            $q->where('empresa_id', $user->empresa_id);
            if (! $user->isSuperAdmin()) {
                $opsIds = $user->getOperacoesIds();
                if (! empty($opsIds)) {
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

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromEmprestimos(
            $itens->map(fn ($item) => $item->pagamento?->parcela?->emprestimo)->filter()
        );

        return view('liberacoes.produtos-objeto-recebidos', compact('itens', 'operacoes', 'operacaoId', 'status', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Listar pagamentos em produto/objeto aguardando aceite (gestor/adm)
     */
    public function pagamentosProdutoObjeto(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aceitar pagamentos em produto/objeto.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($operacoesIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIdsList)->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
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
        if (! $user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (! empty($operacoesIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $operacoesIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $pagamentos = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromParcelas($pagamentos->map(fn ($p) => $p->parcela)->filter());

        return view('liberacoes.pagamentos-produto-objeto', compact('pagamentos', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Aceitar pagamento em produto/objeto (gestor/adm)
     */
    public function aceitarPagamentoProdutoObjeto(int $id): RedirectResponse
    {
        $user = auth()->user();
        $pagamento = Pagamento::with('parcela.emprestimo')->findOrFail($id);
        $operacaoId = $pagamento->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        try {
            $this->pagamentoService->aceitarPagamentoProdutoObjeto($id, $user->id);

            return redirect()->route('liberacoes.pagamentos-produto-objeto')
                ->with('success', 'Pagamento em produto/objeto aceito. A parcela foi creditada (sem movimentação de caixa).');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aceitar: '.$e->getMessage());
        }
    }

    /**
     * Rejeitar pagamento em produto/objeto (gestor/adm). Pagamento continua pendente.
     */
    public function rejeitarPagamentoProdutoObjeto(int $id): RedirectResponse
    {
        $user = auth()->user();
        $pagamento = Pagamento::with('parcela.emprestimo')->findOrFail($id);
        $operacaoId = $pagamento->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        try {
            $this->pagamentoService->rejeitarPagamentoProdutoObjeto($id, $user->id);

            return redirect()->route('liberacoes.pagamentos-produto-objeto')
                ->with('success', 'Pagamento em produto/objeto rejeitado. A parcela permanece pendente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao rejeitar: '.$e->getMessage());
        }
    }

    /**
     * Listar solicitações de pagamento com juros parcial (abaixo do devido) – aguardando aprovação.
     */
    public function solicitacoesJurosParcial(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar solicitações de juros parcial.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($opsIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIdsList)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
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
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (! empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromParcelas($solicitacoes->map(fn ($s) => $s->parcela)->filter());

        return view('liberacoes.solicitacoes-juros-parcial', compact('solicitacoes', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Aprovar solicitação de pagamento com juros parcial: registra o pagamento e atualiza a solicitação.
     */
    public function aprovarSolicitacaoJurosParcial(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoPagamentoJurosParcial::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
            return back()->with('error', 'Erro ao aprovar: '.$e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de pagamento com juros parcial.
     */
    public function rejeitarSolicitacaoJurosParcial(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoPagamentoJurosParcial::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar estas solicitações.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($opsIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIdsList)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
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
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (! empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromParcelas($solicitacoes->map(fn ($s) => $s->parcela)->filter());

        return view('liberacoes.solicitacoes-juros-contrato-reduzido', compact('solicitacoes', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Aprovar solicitação de pagamento com valor inferior: registra o pagamento e atualiza a solicitação.
     */
    public function aprovarSolicitacaoJurosContratoReduzido(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoPagamentoJurosContratoReduzido::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
            return back()->with('error', 'Erro ao aprovar: '.$e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de pagamento com valor inferior.
     */
    public function rejeitarSolicitacaoJurosContratoReduzido(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoPagamentoJurosContratoReduzido::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar estas solicitações.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($opsIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIdsList)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
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
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (! empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromParcelas($solicitacoes->map(fn ($s) => $s->parcela)->filter());

        return view('liberacoes.solicitacoes-renovacao-abate', compact('solicitacoes', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Aprovar solicitação de renovação com abate: executa a renovação com o valor da solicitação.
     */
    public function aprovarSolicitacaoRenovacaoAbate(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoRenovacaoAbate::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return redirect()->route('liberacoes.renovacao-abate')->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
                true,
                $solicitacao->consultor_id
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
                    'mensagem' => 'Sua solicitação de renovação com abate do empréstimo #'.$solicitacao->parcela->emprestimo_id.' foi aprovada. O novo empréstimo #'.$novoEmprestimo->id.' foi criado.',
                    'url' => route('emprestimos.show', $novoEmprestimo->id),
                    'dados' => ['emprestimo_id' => $novoEmprestimo->id, 'emprestimo_origem_id' => $solicitacao->parcela->emprestimo_id],
                ]);
            }

            return redirect()->route('liberacoes.renovacao-abate')
                ->with('success', 'Solicitação aprovada. A renovação foi realizada e o novo empréstimo #'.$novoEmprestimo->id.' foi criado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return redirect()->route('liberacoes.renovacao-abate')->with('error', $msg);
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.renovacao-abate')->with('error', 'Erro ao aprovar: '.$e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de renovação com abate.
     */
    public function rejeitarSolicitacaoRenovacaoAbate(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoRenovacaoAbate::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return redirect()->route('liberacoes.renovacao-abate')->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
                'mensagem' => 'Sua solicitação de renovação com abate do empréstimo #'.$emprestimoId.' foi rejeitada. Você pode enviar uma nova solicitação.',
                'url' => route('emprestimos.show', $emprestimoId),
                'dados' => ['emprestimo_id' => $emprestimoId],
            ]);
        }

        return redirect()->route('liberacoes.renovacao-abate')
            ->with('success', 'Solicitação rejeitada. O consultor pode enviar uma nova solicitação.');
    }

    /**
     * Listar solicitações de pagamento parcial em diária (aguardando aprovação).
     */
    public function solicitacoesDiariaParcial(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar estas solicitações.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($opsIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIdsList)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
                $operacaoId = null;
            }
        }

        $query = SolicitacaoPagamentoDiariaParcial::with([
            'parcela.emprestimo.cliente',
            'parcela.emprestimo.operacao',
            'consultor',
        ])->where('status', 'aguardando');

        if ($operacaoId) {
            $query->whereHas('parcela.emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (! empty($opsIds)) {
                $query->whereHas('parcela.emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromParcelas($solicitacoes->map(fn ($s) => $s->parcela)->filter());

        return view('liberacoes.solicitacoes-diaria-parcial', compact('solicitacoes', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    public function aprovarSolicitacaoDiariaParcial(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoPagamentoDiariaParcial::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return redirect()->route('liberacoes.diaria-parcial')->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        try {
            $this->pagamentoService->aprovarSolicitacaoPagamentoDiariaParcial($solicitacao, $user->id);

            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            if ($solicitacao->consultor_id) {
                $notificacaoService->criar([
                    'user_id' => $solicitacao->consultor_id,
                    'tipo' => 'pagamento_diaria_parcial_aprovado',
                    'titulo' => 'Pagamento parcial (diária) aprovado',
                    'mensagem' => 'Sua solicitação de pagamento parcial do empréstimo #'.$solicitacao->emprestimo_id.' foi aprovada. A parcela foi marcada como paga (parcial) e o faltante foi acrescido à última parcela.',
                    'url' => route('emprestimos.show', $solicitacao->emprestimo_id),
                    'dados' => ['emprestimo_id' => $solicitacao->emprestimo_id],
                ]);
            }

            return redirect()->route('liberacoes.diaria-parcial')
                ->with('success', 'Solicitação aprovada. Parcela atualizada como paga (parcial) e última parcela ajustada.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return redirect()->route('liberacoes.diaria-parcial')->with('error', $msg);
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.diaria-parcial')->with('error', 'Erro ao aprovar: '.$e->getMessage());
        }
    }

    public function rejeitarSolicitacaoDiariaParcial(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoPagamentoDiariaParcial::with('parcela.emprestimo')->findOrFail($id);
        if (! $solicitacao->isAguardando()) {
            return redirect()->route('liberacoes.diaria-parcial')->with('error', 'Esta solicitação já foi processada.');
        }
        $operacaoId = $solicitacao->parcela->emprestimo->operacao_id;
        if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        try {
            $this->pagamentoService->rejeitarSolicitacaoPagamentoDiariaParcial($solicitacao, $user->id);
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.diaria-parcial')->with('error', 'Erro ao rejeitar: '.$e->getMessage());
        }

        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        if ($solicitacao->consultor_id) {
            $notificacaoService->criar([
                'user_id' => $solicitacao->consultor_id,
                'tipo' => 'pagamento_diaria_parcial_rejeitado',
                'titulo' => 'Pagamento parcial (diária) rejeitado',
                'mensagem' => 'Sua solicitação de pagamento parcial do empréstimo #'.$solicitacao->emprestimo_id.' foi rejeitada. O valor foi estornado no caixa.',
                'url' => route('emprestimos.show', $solicitacao->emprestimo_id),
                'dados' => ['emprestimo_id' => $solicitacao->emprestimo_id],
            ]);
        }

        return redirect()->route('liberacoes.diaria-parcial')
            ->with('success', 'Solicitação rejeitada. A entrada em caixa foi estornada.');
    }

    /**
     * Listar solicitações de negociação pendentes
     */
    public function negociacoes(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar negociações.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIdsList = $user->getOperacoesIds();
            $operacoes = ! empty($opsIdsList)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIdsList)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $operacaoId, $opsIds, true)) {
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
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (! empty($opsIds)) {
                $query->whereIn('operacao_id', $opsIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = $this->fichasContatoMapFromEmprestimos($solicitacoes->map(fn ($s) => $s->emprestimo)->filter());

        return view('liberacoes.negociacoes', compact('solicitacoes', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Aprovar solicitação de negociação
     */
    public function aprovarNegociacao(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoNegociacao::with('emprestimo')->findOrFail($id);
        if (! $solicitacao->isPendente()) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Esta solicitação já foi processada.');
        }
        if (! $user->temAlgumPapelNaOperacao($solicitacao->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
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
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Erro ao aprovar: '.$e->getMessage());
        }
    }

    /**
     * Rejeitar solicitação de negociação
     */
    public function rejeitarNegociacao(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoNegociacao::with('emprestimo')->findOrFail($id);
        if (! $solicitacao->isPendente()) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Esta solicitação já foi processada.');
        }
        if (! $user->temAlgumPapelNaOperacao($solicitacao->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado.');
        }

        try {
            $emprestimoService = app(EmprestimoService::class);
            $observacao = $request->input('observacao');

            $emprestimoService->rejeitarNegociacao($id, $user->id, $observacao);

            return redirect()->route('liberacoes.negociacoes')
                ->with('success', 'Solicitação de negociação rejeitada.');
        } catch (\Exception $e) {
            return redirect()->route('liberacoes.negociacoes')->with('error', 'Erro ao rejeitar: '.$e->getMessage());
        }
    }

    /**
     * @param  iterable<int, \App\Modules\Loans\Models\Emprestimo|null>  $emprestimos
     * @return \Illuminate\Support\Collection<string, \App\Modules\Core\Models\OperacaoDadosCliente>
     */
    private function fichasContatoMapFromEmprestimos(iterable $emprestimos): \Illuminate\Support\Collection
    {
        return FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos(collect($emprestimos)->filter())
        );
    }

    /**
     * @param  iterable<int, \App\Modules\Loans\Models\Parcela|null>  $parcelas
     * @return \Illuminate\Support\Collection<string, \App\Modules\Core\Models\OperacaoDadosCliente>
     */
    private function fichasContatoMapFromParcelas(iterable $parcelas): \Illuminate\Support\Collection
    {
        return FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromParcelas(collect($parcelas)->filter())
        );
    }
}
