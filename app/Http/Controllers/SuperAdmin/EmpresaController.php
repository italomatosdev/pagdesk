<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Empresa;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Services\EmpresaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EmpresaController extends Controller
{
    protected EmpresaService $empresaService;

    public function __construct(EmpresaService $empresaService)
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isSuperAdmin()) {
                abort(403, 'Acesso negado. Apenas Super Admin pode acessar esta área.');
            }
            return $next($request);
        });
        $this->empresaService = $empresaService;
    }

    /**
     * Listar todas as empresas
     */
    public function index(Request $request): View
    {
        $query = Empresa::query();

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plano')) {
            $query->where('plano', $request->plano);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('razao_social', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        $empresas = $query->withCount(['operacoes', 'usuarios', 'clientes', 'emprestimos'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        // Ajustar contagem de clientes para incluir vinculados
        foreach ($empresas as $empresa) {
            $empresa->clientes_count = $empresa->todosClientes()->count();
        }

        // Estatísticas
        $stats = [
            'total' => Empresa::count(),
            'ativas' => Empresa::where('status', 'ativa')->count(),
            'suspensas' => Empresa::where('status', 'suspensa')->count(),
            'canceladas' => Empresa::where('status', 'cancelada')->count(),
        ];

        return view('super-admin.empresas.index', compact('empresas', 'stats'));
    }

    /**
     * Mostrar formulário de criação
     */
    public function create(): View
    {
        return view('super-admin.empresas.create');
    }

    /**
     * Criar nova empresa
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:14|unique:empresas,cnpj',
            'email_contato' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'status' => 'required|in:ativa,suspensa,cancelada',
            'plano' => 'required|in:basico,profissional,enterprise',
            'data_ativacao' => 'nullable|date',
            'data_expiracao' => 'nullable|date|after:data_ativacao',
            'permite_multiplas_operacoes' => 'nullable|boolean',
        ]);

        // Montar configurações
        // Quando checkbox não está marcado, ele não é enviado no POST, então usamos false como padrão
        $configuracoes = [
            'operacoes' => [
                'permite_multiplas_operacoes' => $request->has('permite_multiplas_operacoes') && $request->permite_multiplas_operacoes == '1',
            ],
        ];

        $validated['configuracoes'] = $configuracoes;

        try {
            $empresa = $this->empresaService->criar($validated);
            return redirect()->route('super-admin.empresas.show', $empresa->id)
                ->with('success', 'Empresa criada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar empresa: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar detalhes da empresa
     */
    public function show(int $id): View
    {
        $empresa = Empresa::withCount(['operacoes', 'usuarios', 'clientes', 'emprestimos'])
            ->findOrFail($id);
        
        // Ajustar contagem de clientes para incluir vinculados
        $empresa->clientes_count = $empresa->todosClientes()->count();

        $estatisticas = $this->empresaService->obterEstatisticas($id);

        return view('super-admin.empresas.show', compact('empresa', 'estatisticas'));
    }

    /**
     * Mostrar formulário de edição
     */
    public function edit(int $id): View
    {
        $empresa = Empresa::findOrFail($id);
        return view('super-admin.empresas.edit', compact('empresa'));
    }

    /**
     * Atualizar empresa
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:14|unique:empresas,cnpj,' . $id,
            'email_contato' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'status' => 'required|in:ativa,suspensa,cancelada',
            'plano' => 'required|in:basico,profissional,enterprise',
            'data_ativacao' => 'nullable|date',
            'data_expiracao' => 'nullable|date|after:data_ativacao',
            'permite_multiplas_operacoes' => 'nullable|boolean',
        ]);

        // Montar configurações
        // Quando checkbox não está marcado, ele não é enviado no POST, então usamos false como padrão
        $configuracoes = [
            'operacoes' => [
                'permite_multiplas_operacoes' => $request->has('permite_multiplas_operacoes') && $request->permite_multiplas_operacoes == '1',
            ],
        ];

        $validated['configuracoes'] = $configuracoes;

        try {
            $empresa = $this->empresaService->atualizar($id, $validated);
            return redirect()->route('super-admin.empresas.show', $empresa->id)
                ->with('success', 'Empresa atualizada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar empresa: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Suspender empresa
     */
    public function suspender(int $id): RedirectResponse
    {
        try {
            $this->empresaService->suspender($id);
            return back()->with('success', 'Empresa suspensa com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao suspender empresa: ' . $e->getMessage());
        }
    }

    /**
     * Ativar empresa
     */
    public function ativar(int $id): RedirectResponse
    {
        try {
            $this->empresaService->ativar($id);
            return back()->with('success', 'Empresa ativada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao ativar empresa: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar empresa
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->empresaService->cancelar($id);
            return redirect()->route('super-admin.empresas.index')
                ->with('success', 'Empresa cancelada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao cancelar empresa: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar formulário para criar usuário para a empresa
     */
    public function createUsuario(int $id): View
    {
        $empresa = Empresa::findOrFail($id);
        $roles = Role::all();
        $operacoes = Operacao::where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();
        
        return view('super-admin.empresas.usuarios.create', compact('empresa', 'roles', 'operacoes'));
    }

    /**
     * Criar usuário para a empresa
     */
    public function storeUsuario(Request $request, int $id): RedirectResponse
    {
        $empresa = Empresa::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'operacoes' => 'nullable|array',
            'operacoes.*' => 'integer|exists:operacoes,id',
        ]);

        try {
            // Criar usuário
            $usuario = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'empresa_id' => $empresa->id,
                'is_super_admin' => false,
            ]);

            // Atribuir papéis (buscar IDs dos papéis pelos nomes)
            $roleIds = Role::whereIn('name', $validated['roles'])->pluck('id')->toArray();
            $usuario->roles()->sync($roleIds);

            // Atribuir operações (se informadas)
            if (!empty($validated['operacoes'])) {
                // Validar que as operações pertencem à empresa
                $operacoesValidas = Operacao::where('empresa_id', $empresa->id)
                    ->whereIn('id', $validated['operacoes'])
                    ->pluck('id')
                    ->toArray();
                
                $usuario->operacoes()->sync($operacoesValidas);
            }

            return redirect()->route('super-admin.empresas.show', $empresa->id)
                ->with('success', 'Usuário criado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar usuário: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar formulário para criar operação para a empresa
     */
    public function createOperacao(int $id): View
    {
        $empresa = Empresa::findOrFail($id);
        return view('super-admin.empresas.operacoes.create', compact('empresa'));
    }

    /**
     * Criar operação para a empresa
     */
    public function storeOperacao(Request $request, int $id): RedirectResponse
    {
        $empresa = Empresa::findOrFail($id);
        
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($empresa) {
                    if ($value) {
                        $exists = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                            ->where('empresa_id', $empresa->id)
                            ->where('codigo', $value)
                            ->exists();
                        if ($exists) {
                            $fail('Este código já está em uso para esta empresa.');
                        }
                    }
                },
            ],
            'descricao' => 'nullable|string',
            'ativo' => 'nullable|boolean',
            'valor_aprovacao_automatica' => 'nullable|numeric|min:0',
            'requer_aprovacao' => 'nullable|boolean',
            'requer_liberacao' => 'nullable|boolean',
            'requer_autorizacao_pagamento_produto' => 'nullable|boolean',
            'taxa_juros_atraso' => 'nullable|numeric|min:0|max:100',
            'tipo_calculo_juros' => 'nullable|in:por_dia,por_mes',
            'documentos_obrigatorios' => 'nullable|array',
            'documentos_obrigatorios.*' => 'string|in:documento_cliente,selfie_documento',
        ]);

        try {
            // Normalizar checkboxes
            $requerAprovacao = $request->has('requer_aprovacao') ? (bool) $request->input('requer_aprovacao') : true;
            $requerLiberacao = $request->has('requer_liberacao') ? (bool) $request->input('requer_liberacao') : true;
            $requerAutorizacaoProduto = $request->has('requer_autorizacao_pagamento_produto') ? (bool) $request->input('requer_autorizacao_pagamento_produto') : false;
            
            // Criar operação vinculada à empresa
            $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                'nome' => $validated['nome'],
                'codigo' => $validated['codigo'] ?? null,
                'descricao' => $validated['descricao'] ?? null,
                'ativo' => $request->has('ativo') && $request->ativo == '1',
                'valor_aprovacao_automatica' => $validated['valor_aprovacao_automatica'] ?? 0,
                'requer_aprovacao' => $requerAprovacao,
                'requer_liberacao' => $requerLiberacao,
                'requer_autorizacao_pagamento_produto' => $requerAutorizacaoProduto,
                'taxa_juros_atraso' => $validated['taxa_juros_atraso'] ?? 0,
                'tipo_calculo_juros' => $validated['tipo_calculo_juros'] ?? 'por_dia',
                'empresa_id' => $empresa->id,
            ]);
            $operacao->syncDocumentosObrigatorios($request->input('documentos_obrigatorios', []));

            return redirect()->route('super-admin.empresas.show', $empresa->id)
                ->with('success', 'Operação criada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar operação: ' . $e->getMessage())->withInput();
        }
    }
}
