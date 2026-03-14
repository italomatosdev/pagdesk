<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperacaoDocumentoObrigatorio;
use App\Modules\Core\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar todas as operações (todas as empresas)
     */
    public function index(Request $request): View
    {
        $query = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with(['empresa']);

        // Filtros
        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('codigo', 'like', "%{$busca}%")
                  ->orWhere('descricao', 'like', "%{$busca}%")
                  ->orWhereHas('empresa', function ($qe) use ($busca) {
                      $qe->where('nome', 'like', "%{$busca}%");
                  });
            });
        }

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->input('empresa_id'));
        }

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->input('ativo') === '1');
        }

        $operacoes = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Empresas para filtro
        $empresas = Empresa::orderBy('nome')->get();

        // Estatísticas
        $stats = [
            'total' => Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->count(),
            'ativas' => Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->where('ativo', true)->count(),
            'inativas' => Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->where('ativo', false)->count(),
        ];

        return view('super-admin.operacoes.index', compact('operacoes', 'empresas', 'stats'));
    }

    /**
     * Mostrar detalhes de uma operação
     */
    public function show(int $id): View
    {
        $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with(['empresa', 'emprestimos', 'usuarios'])
            ->findOrFail($id);

        return view('super-admin.operacoes.show', compact('operacao'));
    }

    /**
     * Mostrar formulário de edição
     */
    public function edit(int $id): View
    {
        $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->with(['empresa', 'documentosObrigatorios'])
            ->findOrFail($id);
        
        $empresas = Empresa::orderBy('nome')->get();
        $tiposDocumento = OperacaoDocumentoObrigatorio::tiposDisponiveis();

        return view('super-admin.operacoes.edit', compact('operacao', 'empresas', 'tiposDocumento'));
    }

    /**
     * Atualizar operação
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
            ->findOrFail($id);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($operacao) {
                    if ($value) {
                        $exists = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                            ->where('id', '!=', $operacao->id)
                            ->where('codigo', $value)
                            ->exists();
                        if ($exists) {
                            $fail('Este código já está em uso.');
                        }
                    }
                },
            ],
            'descricao' => 'nullable|string',
            'empresa_id' => 'nullable|exists:empresas,id',
            'ativo' => 'nullable|boolean',
            'valor_aprovacao_automatica' => 'nullable|numeric|min:0',
            'requer_aprovacao' => 'nullable|boolean',
            'requer_liberacao' => 'nullable|boolean',
            'requer_autorizacao_pagamento_produto' => 'nullable|boolean',
            'permite_emprestimo_retroativo' => 'nullable|boolean',
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
            $validated['permite_emprestimo_retroativo'] = $request->has('permite_emprestimo_retroativo') && $request->permite_emprestimo_retroativo == '1';
            $validated['ativo'] = $request->has('ativo') && $request->ativo == '1';

            unset($validated['documentos_obrigatorios']);
            $operacao->update($validated);
            $operacao->syncDocumentosObrigatorios($request->input('documentos_obrigatorios', []));

            return redirect()->route('super-admin.operacoes.show', $operacao->id)
                ->with('success', 'Operação atualizada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar operação: ' . $e->getMessage())->withInput();
        }
    }
}
