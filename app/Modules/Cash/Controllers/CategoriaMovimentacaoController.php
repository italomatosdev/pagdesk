<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CategoriaMovimentacao;
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
            if (!auth()->user()->hasAnyRole(['gestor', 'administrador'])) {
                abort(403, 'Apenas gestores e administradores podem gerenciar categorias.');
            }
            return $next($request);
        });
    }

    /**
     * Listar categorias (filtro opcional por tipo)
     */
    public function index(Request $request): View
    {
        $query = CategoriaMovimentacao::query()->orderBy('ordem')->orderBy('nome');
        $tipo = $request->input('tipo');
        if (in_array($tipo, ['entrada', 'despesa'], true)) {
            $query->where('tipo', $tipo);
        }
        $categorias = $query->paginate(20)->withQueryString();
        return view('caixa.categorias.index', compact('categorias', 'tipo'));
    }

    /**
     * Formulário de criação
     */
    public function create(): View
    {
        return view('caixa.categorias.create');
    }

    /**
     * Salvar nova categoria
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:entrada,despesa',
            'ativo' => 'boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);
        $validated['ativo'] = $request->boolean('ativo');
        $validated['ordem'] = (int) ($validated['ordem'] ?? 0);
        $validated['empresa_id'] = auth()->user()->empresa_id;
        CategoriaMovimentacao::create($validated);
        return redirect()->route('caixa.categorias.index')->with('success', 'Categoria criada com sucesso.');
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): View
    {
        $categoria = CategoriaMovimentacao::findOrFail($id);
        return view('caixa.categorias.edit', compact('categoria'));
    }

    /**
     * Atualizar categoria
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $categoria = CategoriaMovimentacao::findOrFail($id);
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:entrada,despesa',
            'ativo' => 'boolean',
            'ordem' => 'nullable|integer|min:0',
        ]);
        $categoria->ativo = $request->boolean('ativo');
        $categoria->ordem = (int) ($validated['ordem'] ?? 0);
        $categoria->nome = $validated['nome'];
        $categoria->tipo = $validated['tipo'];
        $categoria->save();
        return redirect()->route('caixa.categorias.index')->with('success', 'Categoria atualizada com sucesso.');
    }

    /**
     * Excluir categoria (soft delete)
     */
    public function destroy(int $id): RedirectResponse
    {
        $categoria = CategoriaMovimentacao::findOrFail($id);
        $categoria->delete();
        return redirect()->route('caixa.categorias.index')->with('success', 'Categoria excluída com sucesso.');
    }

    /**
     * Criar categoria via AJAX (para modal inline)
     */
    public function storeAjax(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:100',
                'tipo' => 'required|in:entrada,despesa',
            ]);

            $categoria = CategoriaMovimentacao::create([
                'nome' => $validated['nome'],
                'tipo' => $validated['tipo'],
                'ativo' => true,
                'ordem' => 0,
                'empresa_id' => auth()->user()->empresa_id,
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
