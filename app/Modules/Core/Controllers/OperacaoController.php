<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDocumentoObrigatorio;
use App\Modules\Core\Services\OperacaoService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class OperacaoController extends Controller
{
    protected OperacaoService $operacaoService;

    public function __construct(OperacaoService $operacaoService)
    {
        $this->middleware('auth');
        // Administradores e gestores
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasAnyRole(['administrador', 'gestor'])) {
                abort(403, 'Acesso negado. Apenas administradores e gestores podem gerenciar operações.');
            }
            return $next($request);
        });
        $this->operacaoService = $operacaoService;
    }

    /**
     * Listar operações
     * Super Admin: todas. Admin/gestor: apenas as operações às quais está vinculado.
     */
    public function index(): View
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->orderBy('nome')
                ->paginate(15);
        } else {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                $operacoes = Operacao::whereRaw('1 = 0')->orderBy('nome')->paginate(15);
            } else {
                $operacoes = Operacao::whereIn('id', $operacoesIds)->orderBy('nome')->paginate(15);
            }
        }
        return view('operacoes.index', compact('operacoes'));
    }

    /**
     * Mostrar formulário de criação (apenas Super Admin)
     */
    public function create(): View
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Apenas o Super Admin pode criar operações. Criação desabilitada momentaneamente para administradores e gestores.');
        }
        $tiposDocumento = OperacaoDocumentoObrigatorio::tiposDisponiveis();
        return view('operacoes.create', compact('tiposDocumento'));
    }

    /**
     * Criar operação (apenas Super Admin)
     */
    public function store(Request $request): RedirectResponse
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Apenas o Super Admin pode criar operações.');
        }
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50|unique:operacoes,codigo',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
            'valor_aprovacao_automatica' => 'nullable|numeric|min:0',
            'requer_aprovacao' => 'boolean',
            'requer_liberacao' => 'boolean',
            'requer_autorizacao_pagamento_produto' => 'boolean',
            'permite_emprestimo_retroativo' => 'boolean',
            'consultores_veem_apenas_proprios_emprestimos' => 'boolean',
            'taxa_juros_atraso' => 'nullable|numeric|min:0|max:100',
            'tipo_calculo_juros' => 'nullable|in:por_dia,por_mes',
            'documentos_obrigatorios' => 'nullable|array',
            'documentos_obrigatorios.*' => 'string|in:documento_cliente,selfie_documento',
        ]);

        try {
            // Normalizar checkboxes (se não vierem no request, são false)
            $validated['requer_aprovacao'] = $request->has('requer_aprovacao') ? (bool) $request->input('requer_aprovacao') : false;
            $validated['requer_liberacao'] = $request->has('requer_liberacao') ? (bool) $request->input('requer_liberacao') : false;
            $validated['requer_autorizacao_pagamento_produto'] = $request->has('requer_autorizacao_pagamento_produto') ? (bool) $request->input('requer_autorizacao_pagamento_produto') : false;
            $validated['permite_emprestimo_retroativo'] = $request->has('permite_emprestimo_retroativo');
            $validated['consultores_veem_apenas_proprios_emprestimos'] = $request->boolean('consultores_veem_apenas_proprios_emprestimos');

            unset($validated['documentos_obrigatorios']);
            $operacao = $this->operacaoService->criar($validated);
            $operacao->syncDocumentosObrigatorios($request->input('documentos_obrigatorios', []));
            return redirect()->route('operacoes.index')
                ->with('success', 'Operação criada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar operação: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar detalhes (apenas operações do usuário, exceto Super Admin)
     */
    public function show(int $id): View
    {
        $user = auth()->user();
        $operacao = Operacao::withCount(['operationClients', 'emprestimos'])
            ->with(['usuarios.roles'])
            ->findOrFail($id);
        if (!$user->isSuperAdmin()) {
            $ids = $user->getOperacoesIds();
            if (empty($ids) || !in_array((int) $id, $ids, true)) {
                abort(403, 'Você não tem acesso a esta operação.');
            }
        }
        return view('operacoes.show', compact('operacao'));
    }

    /**
     * Mostrar formulário de edição (apenas operações do usuário, exceto Super Admin)
     */
    public function edit(int $id): View
    {
        $user = auth()->user();
        $operacao = Operacao::with('documentosObrigatorios')->findOrFail($id);
        if (!$user->isSuperAdmin()) {
            $ids = $user->getOperacoesIds();
            if (empty($ids) || !in_array((int) $id, $ids, true)) {
                abort(403, 'Você não tem acesso a esta operação.');
            }
        }
        $tiposDocumento = OperacaoDocumentoObrigatorio::tiposDisponiveis();
        return view('operacoes.edit', compact('operacao', 'tiposDocumento'));
    }

    /**
     * Atualizar operação (apenas operações do usuário, exceto Super Admin)
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $ids = $user->getOperacoesIds();
            if (empty($ids) || !in_array((int) $id, $ids, true)) {
                abort(403, 'Você não tem acesso a esta operação.');
            }
        }
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50|unique:operacoes,codigo,' . $id,
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
            'valor_aprovacao_automatica' => 'nullable|numeric|min:0',
            'requer_aprovacao' => 'boolean',
            'requer_liberacao' => 'boolean',
            'requer_autorizacao_pagamento_produto' => 'boolean',
            'permite_emprestimo_retroativo' => 'boolean',
            'consultores_veem_apenas_proprios_emprestimos' => 'boolean',
            'taxa_juros_atraso' => 'nullable|numeric|min:0|max:100',
            'tipo_calculo_juros' => 'nullable|in:por_dia,por_mes',
            'documentos_obrigatorios' => 'nullable|array',
            'documentos_obrigatorios.*' => 'string|in:documento_cliente,selfie_documento',
        ]);

        try {
            // Normalizar checkboxes
            $validated['requer_aprovacao'] = $request->has('requer_aprovacao') ? (bool) $request->input('requer_aprovacao') : false;
            $validated['requer_liberacao'] = $request->has('requer_liberacao') ? (bool) $request->input('requer_liberacao') : false;
            $validated['requer_autorizacao_pagamento_produto'] = $request->has('requer_autorizacao_pagamento_produto') ? (bool) $request->input('requer_autorizacao_pagamento_produto') : false;
            $validated['permite_emprestimo_retroativo'] = $request->has('permite_emprestimo_retroativo');
            $validated['consultores_veem_apenas_proprios_emprestimos'] = $request->boolean('consultores_veem_apenas_proprios_emprestimos');

            unset($validated['documentos_obrigatorios']);
            $operacao = $this->operacaoService->atualizar($id, $validated);
            $operacao->syncDocumentosObrigatorios($request->input('documentos_obrigatorios', []));
            return redirect()->route('operacoes.index')
                ->with('success', 'Operação atualizada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar operação: ' . $e->getMessage())->withInput();
        }
    }
}
