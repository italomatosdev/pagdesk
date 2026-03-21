<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\FormaPagamentoVenda;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Produto;
use App\Modules\Core\Models\Venda;
use App\Modules\Core\Services\VendaService;
use App\Support\ClienteNomeExibicao;
use App\Support\FichaContatoLookup;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendaController extends Controller
{
    protected VendaService $vendaService;

    public function __construct(VendaService $vendaService)
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
                abort(403, 'Acesso negado. Apenas administradores e gestores podem registrar vendas.');
            }
            return $next($request);
        });
        $this->vendaService = $vendaService;
    }

    /**
     * Listar vendas
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = Venda::with(['cliente', 'operacao', 'user', 'formasPagamento']);

        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereIn('operacao_id', $opsIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('operacao_id')) {
            $operacaoId = (int) $request->operacao_id;
            if ($user->isSuperAdmin() || in_array($operacaoId, $user->getOperacoesIds(), true)) {
                $query->where('operacao_id', $operacaoId);
            }
        }
        if ($request->filled('data_inicio')) {
            $query->whereDate('data_venda', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('data_venda', '<=', $request->data_fim);
        }

        // Totalizadores (respeitam os mesmos filtros da listagem)
        $subQuery = (clone $query)->select('id');
        $stats = [
            'total_vendas' => (clone $query)->count(),
            'valor_total' => FormaPagamentoVenda::whereIn('venda_id', $subQuery)->sum('valor'),
            'vendas_mes' => (clone $query)->whereMonth('data_venda', now()->month)->whereYear('data_venda', now()->year)->count(),
        ];
        $subQueryMes = (clone $query)->whereMonth('data_venda', now()->month)->whereYear('data_venda', now()->year)->select('id');
        $stats['valor_mes'] = FormaPagamentoVenda::whereIn('venda_id', $subQueryMes)->sum('valor');

        $vendas = $query->orderByDesc('data_venda')->orderByDesc('id')->paginate(20)->withQueryString();

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromVendas($vendas)
        );

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('vendas.index', compact('vendas', 'operacoes', 'stats', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Formulário de nova venda
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }
        // Produtos com estoque, vinculados a operações (filtrados por operação do usuário)
        $produtosQuery = Produto::where('estoque', '>', 0)->whereNotNull('operacao_id');
        if (!$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $produtosQuery->whereIn('operacao_id', $operacoesIds);
            } else {
                $produtosQuery->whereRaw('1 = 0');
            }
        }
        $produtos = $produtosQuery->orderBy('nome')->get();
        $formasDisponiveis = FormaPagamentoVenda::formasDisponiveis();

        // Cliente pré-selecionado (query string, ex: vindo da página do cliente)
        $clientePreSelecionado = null;
        if ($request->filled('cliente_id')) {
            $clientePreSelecionado = \App\Modules\Core\Models\Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find($request->cliente_id);
        }

        return view('vendas.create', compact('operacoes', 'produtos', 'formasDisponiveis', 'clientePreSelecionado'));
    }

    /**
     * Registrar venda
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'operacao_id' => 'required|exists:operacoes,id',
            'data_venda' => 'required|date',
            'observacoes' => 'nullable|string|max:1000',
            'valor_desconto' => 'nullable|numeric|min:0',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.descricao' => 'nullable|string|max:255',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
            'itens.*.preco_unitario_vista' => 'required|numeric|min:0',
            'itens.*.preco_unitario_crediario' => 'required|numeric|min:0',
            'formas' => 'required|array|min:1',
            'formas.*.forma' => 'required|string|in:vista,pix,cartao,crediario',
            'formas.*.valor' => 'required|numeric|min:0',
            'formas.*.descricao' => 'nullable|string|max:255',
            'formas.*.numero_parcelas' => 'nullable|integer|min:1|required_if:formas.*.forma,crediario',
            'formas.*.frequencia' => 'nullable|string|in:diaria,semanal,mensal|required_if:formas.*.forma,crediario',
            'formas.*.comprovante' => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:5120',
        ], [
            'itens.required' => 'Adicione pelo menos um item.',
            'formas.required' => 'Adicione pelo menos uma forma de pagamento.',
            'formas.*.numero_parcelas.required_if' => 'Informe o número de parcelas para o crediário.',
            'formas.*.frequencia.required_if' => 'Informe a frequência das parcelas para o crediário.',
            'formas.*.comprovante.mimes' => 'O comprovante deve ser PDF ou imagem (JPEG, PNG).',
            'formas.*.comprovante.max' => 'O comprovante não pode ter mais de 5 MB.',
        ]);

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $validated['operacao_id'], $opsIds, true)) {
                return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
            }
        }

        $itens = [];
        foreach ($validated['itens'] as $item) {
            if (($item['preco_unitario_vista'] ?? 0) <= 0 && ($item['preco_unitario_crediario'] ?? 0) <= 0) {
                continue;
            }
            $itens[] = [
                'produto_id' => $item['produto_id'] ?? null,
                'descricao' => $item['descricao'] ?? null,
                'quantidade' => $item['quantidade'],
                'preco_unitario_vista' => $item['preco_unitario_vista'],
                'preco_unitario_crediario' => $item['preco_unitario_crediario'],
            ];
        }
        if (empty($itens)) {
            return back()->withErrors(['itens' => 'Adicione pelo menos um item com preço.'])->withInput();
        }

        $formas = [];
        $formasFiles = $request->file('formas') ?? [];
        foreach ($validated['formas'] as $i => $f) {
            if (($f['valor'] ?? 0) <= 0) {
                continue;
            }
            $comprovantePath = null;
            if (isset($formasFiles[$i]['comprovante']) && $formasFiles[$i]['comprovante']->isValid()) {
                $comprovantePath = $formasFiles[$i]['comprovante']->store('comprovantes_venda', 'public');
            }
            $formas[] = [
                'forma' => $f['forma'],
                'valor' => $f['valor'],
                'descricao' => isset($f['descricao']) ? trim($f['descricao']) : null,
                'comprovante_path' => $comprovantePath,
                'numero_parcelas' => $f['forma'] === 'crediario' ? ($f['numero_parcelas'] ?? null) : null,
                'frequencia' => $f['forma'] === 'crediario' ? ($f['frequencia'] ?? 'mensal') : null,
            ];
        }
        if (empty($formas)) {
            return back()->withErrors(['formas' => 'Adicione pelo menos uma forma de pagamento com valor.'])->withInput();
        }

        try {
            $venda = $this->vendaService->registrar([
                'cliente_id' => $validated['cliente_id'],
                'operacao_id' => $validated['operacao_id'],
                'data_venda' => $validated['data_venda'],
                'observacoes' => $validated['observacoes'] ?? null,
                'valor_desconto' => $validated['valor_desconto'] ?? 0,
                'itens' => $itens,
                'formas' => $formas,
            ]);

            return redirect()->route('vendas.show', $venda->id)
                ->with('success', 'Venda registrada com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao registrar venda: ' . $e->getMessage());
            return back()->with('error', 'Erro ao registrar venda: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Exibir venda
     */
    public function show(int $id): View
    {
        $venda = Venda::with([
            'cliente',
            'operacao',
            'user',
            'itens.produto',
            'formasPagamento.emprestimo',
        ])->findOrFail($id);

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $venda->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado a esta venda.');
            }
        }

        $nomeClienteExibicao = $venda->cliente
            ? ClienteNomeExibicao::forClienteOperacao($venda->cliente, (int) $venda->operacao_id)
            : 'Cliente';

        return view('vendas.show', compact('venda', 'nomeClienteExibicao'));
    }

    /**
     * Download do comprovante de uma forma de pagamento da venda
     */
    public function comprovante(int $venda, int $forma): StreamedResponse|\Illuminate\Http\Response
    {
        $vendaModel = Venda::findOrFail($venda);
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $vendaModel->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado.');
            }
        }
        $formaPagamento = $vendaModel->formasPagamento()->where('id', $forma)->firstOrFail();
        if (!$formaPagamento->comprovante_path) {
            abort(404, 'Comprovante não encontrado.');
        }
        $path = Storage::disk('public')->path($formaPagamento->comprovante_path);
        if (!file_exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }
        $nome = basename($formaPagamento->comprovante_path);
        return response()->file($path, ['Content-Disposition' => 'inline; filename="' . $nome . '"']);
    }
}
