<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CategoriaMovimentacao;
use App\Modules\Core\Models\Operacao;
use App\Support\OperacaoPreferida;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CategoriaMovimentacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (auth()->user()->isSuperAdmin()) {
                abort(403, 'Super Admin não pode acessar categorias de movimentação.');
            }
            if (empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
                abort(403, 'Apenas gestores e administradores podem gerenciar categorias.');
            }
            return $next($request);
        });
    }

    /**
     * Listar categorias das operações do usuário (operacao_id em getOperacoesIds() ou null = compartilhada).
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();

        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);

        $query = CategoriaMovimentacao::with('operacao')->orderBy('ordem')->orderBy('nome');
        $tipo = $request->input('tipo');
        if (in_array($tipo, ['entrada', 'despesa'], true)) {
            $query->where('tipo', $tipo);
        }

        if (!empty($operacoesIds)) {
            $query->where(function ($q) use ($operacoesIds, $operacaoId) {
                if ($operacaoId) {
                    $q->where('operacao_id', $operacaoId);
                } else {
                    $q->whereIn('operacao_id', $operacoesIds)->orWhereNull('operacao_id');
                }
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $categorias = $query->paginate(20)->withQueryString();
        return view('caixa.categorias.index', compact('categorias', 'tipo', 'operacoes', 'operacaoId'));
    }

    /**
     * Formulário de criação
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);
        $operacaoIdDefault = OperacaoPreferida::resolverParaFormularioOuQuery($request, $operacoes->pluck('id')->all(), $user);

        return view('caixa.categorias.create', compact('operacoes', 'operacaoIdDefault'));
    }

    /**
     * Salvar nova categoria
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:entrada,despesa',
            'operacao_id' => 'required|exists:operacoes,id',
            'ativo' => 'boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);
        if (empty($operacoesIds) || !in_array((int) $validated['operacao_id'], $operacoesIds, true)) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }
        $validated['ativo'] = $request->boolean('ativo');
        $validated['ordem'] = (int) ($validated['ordem'] ?? 0);
        $validated['empresa_id'] = $user->empresa_id;
        CategoriaMovimentacao::create($validated);
        return redirect()->route('caixa.categorias.index')->with('success', 'Categoria criada com sucesso.');
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): View
    {
        $user = auth()->user();
        $opsIds = $user->getOperacoesIds();
        $categoria = CategoriaMovimentacao::with('operacao')->findOrFail($id);
        $podeEditar = !empty($opsIds) && ($categoria->operacao_id === null || in_array((int) $categoria->operacao_id, $opsIds, true));
        if (!$podeEditar) {
            abort(403, 'Você não tem acesso a esta categoria.');
        }
        $operacoes = !empty($opsIds)
            ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
            : collect([]);
        return view('caixa.categorias.edit', compact('categoria', 'operacoes'));
    }

    /**
     * Atualizar categoria
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $opsIds = $user->getOperacoesIds();
        $categoria = CategoriaMovimentacao::findOrFail($id);
        if (empty($opsIds) || ($categoria->operacao_id !== null && !in_array((int) $categoria->operacao_id, $opsIds, true))) {
            abort(403, 'Você não tem acesso a esta categoria.');
        }
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:entrada,despesa',
            'operacao_id' => 'nullable|exists:operacoes,id',
            'ativo' => 'boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);
        $operacaoId = isset($validated['operacao_id']) && $validated['operacao_id'] !== '' ? (int) $validated['operacao_id'] : null;
        if ($operacaoId !== null && !in_array((int) $operacaoId, $opsIds, true)) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }
        $categoria->ativo = $request->boolean('ativo');
        $categoria->ordem = (int) ($validated['ordem'] ?? 0);
        $categoria->nome = $validated['nome'];
        $categoria->tipo = $validated['tipo'];
        $categoria->operacao_id = $operacaoId ?: null;
        $categoria->save();
        return redirect()->route('caixa.categorias.index')->with('success', 'Categoria atualizada com sucesso.');
    }

    /**
     * Excluir categoria (soft delete)
     */
    public function destroy(int $id): RedirectResponse
    {
        $categoria = CategoriaMovimentacao::findOrFail($id);
        $opsIds = auth()->user()->getOperacoesIds();
        $podeExcluir = !empty($opsIds) && ($categoria->operacao_id === null || in_array((int) $categoria->operacao_id, $opsIds, true));
        if (!$podeExcluir) {
            abort(403, 'Você não tem acesso a esta categoria.');
        }
        $categoria->delete();
        return redirect()->route('caixa.categorias.index')->with('success', 'Categoria excluída com sucesso.');
    }

    /**
     * Criar categoria via AJAX (para modal inline na movimentação)
     */
    public function storeAjax(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:100',
                'tipo' => 'required|in:entrada,despesa',
                'operacao_id' => 'required|exists:operacoes,id',
            ]);
            if (empty($operacoesIds) || !in_array((int) $validated['operacao_id'], $operacoesIds, true)) {
                return response()->json(['success' => false, 'message' => 'Você não tem acesso a esta operação.'], 403);
            }

            $categoria = CategoriaMovimentacao::create([
                'nome' => $validated['nome'],
                'tipo' => $validated['tipo'],
                'ativo' => true,
                'ordem' => 0,
                'empresa_id' => $user->empresa_id,
                'operacao_id' => $validated['operacao_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso.',
                'categoria' => [
                    'id' => $categoria->id,
                    'nome' => $categoria->nome,
                    'tipo' => $categoria->tipo,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar categoria: ' . $e->getMessage()
            ], 500);
        }
    }
}
