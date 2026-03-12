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
        // Apenas administradores
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('administrador')) {
                abort(403, 'Acesso negado. Apenas administradores podem gerenciar operações.');
            }
            return $next($request);
        });
        $this->operacaoService = $operacaoService;
    }

    /**
     * Listar operações
     */
    public function index(): View
    {
        $operacoes = Operacao::orderBy('nome')->paginate(15);
        return view('operacoes.index', compact('operacoes'));
    }

    /**
     * Mostrar formulário de criação
     */
    public function create(): View
    {
        $tiposDocumento = OperacaoDocumentoObrigatorio::tiposDisponiveis();
        return view('operacoes.create', compact('tiposDocumento'));
    }

    /**
     * Criar operação
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50|unique:operacoes,codigo',
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
            'valor_aprovacao_automatica' => 'nullable|numeric|min:0',
            'requer_aprovacao' => 'boolean',
            'requer_liberacao' => 'boolean',
            'requer_autorizacao_pagamento_produto' => 'boolean',
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
     * Mostrar detalhes
     */
    public function show(int $id): View
    {
        $operacao = Operacao::with([
            'operationClients.cliente',
            'emprestimos',
            'usuarios.roles'
        ])->findOrFail($id);
        return view('operacoes.show', compact('operacao'));
    }

    /**
     * Mostrar formulário de edição
     */
    public function edit(int $id): View
    {
        $operacao = Operacao::with('documentosObrigatorios')->findOrFail($id);
        $tiposDocumento = OperacaoDocumentoObrigatorio::tiposDisponiveis();
        return view('operacoes.edit', compact('operacao', 'tiposDocumento'));
    }

    /**
     * Atualizar operação
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50|unique:operacoes,codigo,' . $id,
            'descricao' => 'nullable|string',
            'ativo' => 'boolean',
            'valor_aprovacao_automatica' => 'nullable|numeric|min:0',
            'requer_aprovacao' => 'boolean',
            'requer_liberacao' => 'boolean',
            'requer_autorizacao_pagamento_produto' => 'boolean',
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
