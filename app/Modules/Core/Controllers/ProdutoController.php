<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Produto;
use App\Modules\Core\Models\ProdutoAnexo;
use App\Modules\Core\Traits\Auditable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProdutoController extends Controller
{
    use Auditable;
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
                abort(403, 'Acesso negado. Apenas administradores e gestores podem gerenciar produtos.');
            }
            return $next($request);
        });
    }

    /**
     * Listar produtos da empresa
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = Produto::with('operacao');

        // Filtro "sem operação": exibe apenas produtos sem operacao_id (para corrigir)
        $semOperacao = $request->boolean('sem_operacao');
        if ($semOperacao) {
            $query->whereNull('operacao_id');
        } else {
            if (!$user->isSuperAdmin()) {
                $operacoesIds = $user->getOperacoesIds();
                if (!empty($operacoesIds)) {
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
        if ($request->filled('operacao_id')) {
            $operacaoId = (int) $request->operacao_id;
            if ($user->isSuperAdmin() || in_array($operacaoId, $user->getOperacoesIds(), true)) {
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

        // Contagem de produtos sem operação (para exibir aviso)
        $produtosSemOperacaoCount = Produto::whereNull('operacao_id')->count();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        return view('produtos.index', compact('produtos', 'stats', 'operacoes', 'produtosSemOperacaoCount', 'semOperacao'));
    }

    /**
     * Formulário de novo produto
     */
    public function create(): View
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
        return view('produtos.create', compact('operacoes'));
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
        if (!$user->empresa_id) {
            return back()->with('error', 'Usuário sem empresa vinculada.')->withInput();
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $validated['operacao_id'], $opsIds, true)) {
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
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if ($produto->operacao_id === null || empty($opsIds) || !in_array((int) $produto->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado a este produto.');
            }
        }
        return view('produtos.show', compact('produto'));
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): View
    {
        $user = auth()->user();
        $produto = Produto::with('anexos')->findOrFail($id);
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if ($produto->operacao_id === null || empty($opsIds) || !in_array((int) $produto->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado a este produto.');
            }
        }
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
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
            if (!$user->isSuperAdmin()) {
                $opsIds = $user->getOperacoesIds();
                if ($produto->operacao_id === null || empty($opsIds) || !in_array((int) $produto->operacao_id, $opsIds, true)) {
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

            if (!$user->isSuperAdmin()) {
                $opsIds = $user->getOperacoesIds();
                if (empty($opsIds) || !in_array((int) $validated['operacao_id'], $opsIds, true)) {
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
                    if (!$arquivo->isValid()) {
                        continue;
                    }
                    $caminho = $arquivo->store('produtos/' . $produto->id, 'public');
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
            return back()->with('error', 'Erro ao salvar: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remover anexo do produto
     */
    public function destroyAnexo(int $id, int $anexoId): RedirectResponse
    {
        $produto = Produto::findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if ($produto->operacao_id === null || empty($opsIds) || !in_array((int) $produto->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado a este produto.');
            }
        }
        $anexo = $produto->anexos()->findOrFail($anexoId);
        $anexo->delete();
        return back()->with('success', 'Anexo removido.');
    }
}
