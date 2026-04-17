<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Produto;
use App\Modules\Core\Models\ProdutoAnexo;
use App\Modules\Core\Traits\Auditable;
use App\Support\OperacaoPreferida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProdutoController extends Controller
{
    use Auditable;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->podeAcessarCatalogoProdutos()) {
                abort(403, 'Acesso negado ao catálogo de produtos.');
            }

            return $next($request);
        });
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->temPapelGestaoEmAlgumaOperacao()) {
                abort(403, 'Acesso negado. Apenas administradores e gestores podem cadastrar ou editar produtos.');
            }

            return $next($request);
        })->except(['index', 'show']);
    }

    /**
     * Listar produtos da empresa
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = Produto::with('operacao');

        // Filtro "sem operação": só gestores/admins (consultor nunca vê cadastro solto)
        $semOperacao = $request->boolean('sem_operacao');
        if ($semOperacao && ! $user->temPapelGestaoEmAlgumaOperacao()) {
            $semOperacao = false;
        }
        if ($semOperacao) {
            $query->whereNull('operacao_id');
        } else {
            if (! $user->isSuperAdmin()) {
                $operacoesIds = $user->getOperacoesIdsParaModuloVendas();
                if (! empty($operacoesIds)) {
                    $query->whereIn('operacao_id', $operacoesIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if ($request->filled('search')) {
            $termo = $request->search;
            $query->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                    ->orWhere('codigo', 'like', "%{$termo}%");
            });
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIdsParaModuloVendas();
            $operacoes = ! empty($opsIds)
                ? Operacao::whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }
        $operacaoId = null;
        if (! $semOperacao) {
            $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
            $idsFiltro = $user->isSuperAdmin() ? null : $user->getOperacoesIdsParaModuloVendas();
            if ($operacaoId !== null && ($user->isSuperAdmin() || in_array($operacaoId, $idsFiltro ?? [], true))) {
                $query->where('operacao_id', $operacaoId);
            }
        }
        if ($request->filled('ativo')) {
            if ($request->ativo === '1') {
                $query->where('ativo', true);
            } elseif ($request->ativo === '0') {
                $query->where('ativo', false);
            }
        }
        if ($request->filled('estoque')) {
            if ($request->estoque === 'com') {
                $query->where('estoque', '>', 0);
            } elseif ($request->estoque === 'sem') {
                $query->where('estoque', '<=', 0);
            }
        }

        // Totalizadores (respeitam os mesmos filtros da listagem)
        $stats = [
            'total' => (clone $query)->count(),
            'ativos' => (clone $query)->where('ativo', true)->count(),
            'inativos' => (clone $query)->where('ativo', false)->count(),
            'sem_estoque' => (clone $query)->whereRaw('COALESCE(estoque, 0) <= 0')->count(),
            'com_estoque' => (clone $query)->where('estoque', '>', 0)->count(),
            'total_unidades' => (clone $query)->sum(DB::raw('COALESCE(estoque, 0)')),
        ];
        $stats['valor_estoque'] = (clone $query)->selectRaw('SUM(COALESCE(estoque, 0) * preco_venda) as total')->value('total') ?? 0;

        $produtos = $query->orderBy('nome')->paginate(20)->withQueryString();

        // Contagem de produtos sem operação (para exibir aviso) — só relevante para gestão
        $produtosSemOperacaoCount = $user->temPapelGestaoEmAlgumaOperacao()
            ? Produto::whereNull('operacao_id')->count()
            : 0;

        $podeGerenciarProdutos = $user->temPapelGestaoEmAlgumaOperacao();

        return view('produtos.index', compact('produtos', 'stats', 'operacoes', 'produtosSemOperacaoCount', 'semOperacao', 'operacaoId', 'podeGerenciarProdutos'));
    }

    /**
     * Formulário de novo produto
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
            $operacoes = ! empty($opsIds)
                ? Operacao::whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }
        $operacaoIdDefault = OperacaoPreferida::resolverParaFormularioOuQuery($request, $operacoes->pluck('id')->all(), $user);

        return view('produtos.create', compact('operacoes', 'operacaoIdDefault'));
    }

    /**
     * Cadastrar produto
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'operacao_id' => ['required', 'exists:operacoes,id'],
            'nome' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50',
            'preco_venda' => 'required|numeric|min:0',
            'unidade' => 'nullable|string|max:20',
            'estoque' => 'required|numeric|min:0',
            'ativo' => 'boolean',
        ], [
            'operacao_id.required' => 'O produto deve estar vinculado a uma operação.',
            'operacao_id.exists' => 'A operação selecionada não é válida.',
        ]);

        $user = auth()->user();
        if (! $user->empresa_id) {
            return back()->with('error', 'Usuário sem empresa vinculada.')->withInput();
        }
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
            if (empty($opsIds) || ! in_array((int) $validated['operacao_id'], $opsIds, true)) {
                return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
            }
        }

        $validated['empresa_id'] = $user->empresa_id;
        $validated['estoque'] = (float) $validated['estoque'];
        $validated['ativo'] = $request->boolean('ativo', true);

        $produto = Produto::create($validated);
        self::auditar('criar_produto', $produto, null, $produto->toArray());

        return redirect()->route('produtos.index')
            ->with('success', 'Produto cadastrado com sucesso.');
    }

    /**
     * Exibir produto (detalhes + fotos e anexos)
     */
    public function show(int $id): View
    {
        $produto = Produto::with('anexos')->findOrFail($id);
        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIdsParaModuloVendas();
            if ($produto->operacao_id === null || empty($opsIds) || ! in_array((int) $produto->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado a este produto.');
            }
        }

        $podeGerenciarProdutos = $user->temPapelGestaoEmAlgumaOperacao();

        return view('produtos.show', compact('produto', 'podeGerenciarProdutos'));
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): View
    {
        $user = auth()->user();
        $produto = Produto::with('anexos')->findOrFail($id);
        if (! $user->isSuperAdmin()) {
            $gestaoIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
            if ($produto->operacao_id === null || empty($gestaoIds) || ! in_array((int) $produto->operacao_id, $gestaoIds, true)) {
                abort(403, 'Acesso negado a este produto.');
            }
        }
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
            $operacoes = ! empty($opsIds)
                ? Operacao::whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('produtos.edit', compact('produto', 'operacoes'));
    }

    /**
     * Atualizar produto
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        try {
            $user = auth()->user();
            $produto = Produto::findOrFail($id);
            if (! $user->isSuperAdmin()) {
                $gestaoIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
                if ($produto->operacao_id === null || empty($gestaoIds) || ! in_array((int) $produto->operacao_id, $gestaoIds, true)) {
                    abort(403, 'Acesso negado a este produto.');
                }
            }

            $validated = $request->validate([
                'operacao_id' => ['required', 'exists:operacoes,id'],
                'nome' => 'required|string|max:255',
                'codigo' => 'nullable|string|max:50',
                'preco_venda' => 'required|numeric|min:0',
                'unidade' => 'nullable|string|max:20',
                'estoque' => 'required|numeric|min:0',
                'ativo' => 'nullable|boolean',
                'anexos' => 'nullable|array',
                'anexos.*' => 'file|max:5120|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
            ], [
                'operacao_id.required' => 'O produto deve estar vinculado a uma operação.',
                'operacao_id.exists' => 'A operação selecionada não é válida.',
            ]);

            if (! $user->isSuperAdmin()) {
                $gestaoIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
                if (empty($gestaoIds) || ! in_array((int) $validated['operacao_id'], $gestaoIds, true)) {
                    return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
                }
            }

            $validated['estoque'] = (float) $validated['estoque'];
            $validated['ativo'] = $request->boolean('ativo', true);
            unset($validated['anexos']);

            $oldValues = $produto->toArray();
            $produto->update($validated);

            if ($request->hasFile('anexos')) {
                $ordem = $produto->anexos()->max('ordem') ?? 0;
                foreach ($request->file('anexos') as $arquivo) {
                    if (! $arquivo->isValid()) {
                        continue;
                    }
                    $caminho = $arquivo->store('produtos/'.$produto->id, 'public');
                    $ext = strtolower($arquivo->getClientOriginalExtension());
                    $tipo = ProdutoAnexo::determinarTipo($ext);
                    $ordem++;
                    ProdutoAnexo::create([
                        'produto_id' => $produto->id,
                        'nome_arquivo' => $arquivo->getClientOriginalName(),
                        'caminho' => $caminho,
                        'tipo' => $tipo,
                        'ordem' => $ordem,
                        'tamanho' => $arquivo->getSize(),
                    ]);
                }
            }

            $produto->refresh();
            self::auditar('atualizar_produto', $produto, $oldValues, $produto->toArray());

            return redirect()->route('produtos.edit', $produto->id)
                ->with('success', 'Produto atualizado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('ProdutoController::update falhou', ['id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return back()->with('error', 'Erro ao salvar: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Remover anexo do produto
     */
    public function destroyAnexo(int $id, int $anexoId): RedirectResponse
    {
        $produto = Produto::findOrFail($id);
        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            $gestaoIds = $user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']);
            if ($produto->operacao_id === null || empty($gestaoIds) || ! in_array((int) $produto->operacao_id, $gestaoIds, true)) {
                abort(403, 'Acesso negado a este produto.');
            }
        }
        $anexo = $produto->anexos()->findOrFail($anexoId);
        $anexo->delete();

        return back()->with('success', 'Anexo removido.');
    }
}
