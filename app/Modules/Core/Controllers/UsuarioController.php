<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UsuarioController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->middleware('auth');
        // Administradores e gestores (exceto método buscar que é usado por gestores também)
        $this->middleware(function ($request, $next) {
            // Método buscar() pode ser acessado por gestores e administradores
            if ($request->route()->getActionMethod() === 'buscar') {
                if (!auth()->user()->hasAnyRole(['administrador', 'gestor'])) {
                    abort(403, 'Acesso negado.');
                }
            } else {
                // Outros métodos: administradores e gestores
                if (!auth()->user()->hasAnyRole(['administrador', 'gestor'])) {
                    abort(403, 'Acesso negado. Apenas administradores e gestores podem gerenciar usuários.');
                }
            }
            return $next($request);
        });
        $this->permissionService = $permissionService;
    }

    /**
     * Listar usuários
     * Super Admin vê todos; administrador vê apenas usuários da própria empresa.
     */
    public function index(): View
    {
        $query = User::with(['roles', 'operacoes']);
        $user = auth()->user();

        // Apenas Super Admin vê todos os usuários do sistema
        if (!$user->isSuperAdmin()) {
            $empresaId = $user->empresa_id;
            // Se não tem empresa_id, tenta obter pela primeira operação vinculada
            if ($empresaId === null && $user->operacoes()->exists()) {
                $primeiraOperacao = $user->operacoes()->first();
                $empresaId = $primeiraOperacao?->empresa_id;
            }
            if ($empresaId !== null) {
                $query->where('empresa_id', $empresaId);
            } else {
                // Administrador sem empresa e sem operação: não listar usuários de outras empresas
                $query->whereRaw('1 = 0');
            }
        }

        $usuarios = $query->orderBy('name')->paginate(15);
        $roles = Role::all();
        return view('usuarios.index', compact('usuarios', 'roles'));
    }

    /**
     * Mostrar formulário para criar usuário (dentro da empresa do administrador)
     */
    public function create(): View
    {
        $user = auth()->user();
        $empresa = $user->empresa ?? $user->operacoes()->first()?->empresa;
        if (!$empresa) {
            abort(403, 'Sua conta não está vinculada a uma empresa. Entre em contato com o suporte.');
        }

        $roles = Role::orderBy('name')->get();
        // Gestor não pode atribuir papel de administrador
        if ($user->hasRole('gestor') && !$user->hasRole('administrador')) {
            $roles = $roles->filter(fn ($r) => $r->name !== 'administrador');
        }
        $operacoes = Operacao::where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        return view('usuarios.create', compact('empresa', 'roles', 'operacoes'));
    }

    /**
     * Criar usuário (vinculado à empresa do administrador e às operações selecionadas)
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $empresa = $user->empresa ?? $user->operacoes()->first()?->empresa;
        if (!$empresa) {
            abort(403, 'Sua conta não está vinculada a uma empresa.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'operacoes' => 'nullable|array',
            'operacoes.*' => 'integer|exists:operacoes,id',
        ]);

        // Gestor não pode criar usuário com papel de administrador
        if ($user->hasRole('gestor') && !$user->hasRole('administrador') && in_array('administrador', $validated['roles'], true)) {
            return back()->with('error', 'Gestores não podem atribuir o papel de administrador.')->withInput();
        }

        try {
            // Validar que as operações pertencem à empresa do administrador
            $operacoesIds = $validated['operacoes'] ?? [];
            if (!empty($operacoesIds)) {
                $operacoesValidas = Operacao::where('empresa_id', $empresa->id)
                    ->whereIn('id', $operacoesIds)
                    ->pluck('id')
                    ->toArray();
                $operacoesIds = array_values(array_intersect($operacoesIds, $operacoesValidas));
            }

            $usuario = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'empresa_id' => $empresa->id,
                'is_super_admin' => false,
            ]);

            $roleIds = Role::whereIn('name', $validated['roles'])->pluck('id')->toArray();
            $usuario->roles()->sync($roleIds);

            $usuario->operacoes()->sync($operacoesIds);

            return redirect()->route('usuarios.show', $usuario->id)
                ->with('success', 'Usuário criado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar usuário: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar detalhes do usuário
     */
    public function show(int $id): View
    {
        $query = User::with(['roles', 'operacao', 'operacoes']);
        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            $empresaId = $user->empresa_id ?? $user->operacoes()->first()?->empresa_id;
            if ($empresaId !== null) {
                $query->where('empresa_id', $empresaId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $usuario = $query->findOrFail($id);
        $roles = Role::all();
        // Gestor não pode atribuir papel de administrador
        if ($user->hasRole('gestor') && !$user->hasRole('administrador')) {
            $roles = $roles->filter(fn ($r) => $r->name !== 'administrador');
        }

        $operacoesQuery = Operacao::where('ativo', true);
        if (!$user->isSuperAdmin()) {
            $empresaId = $user->empresa_id ?? $user->operacoes()->first()?->empresa_id;
            if ($empresaId !== null) {
                $operacoesQuery->where('empresa_id', $empresaId);
            } else {
                $operacoesQuery->whereRaw('1 = 0');
            }
        }
        $operacoes = $operacoesQuery->orderBy('nome')->get();

        return view('usuarios.show', compact('usuario', 'roles', 'operacoes'));
    }

    /**
     * Atribuir papel ao usuário
     */
    public function atribuirPapel(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role_name' => 'required|string|exists:roles,name',
        ]);

        // Gestor não pode atribuir papel de administrador
        $user = auth()->user();
        if ($user->hasRole('gestor') && !$user->hasRole('administrador') && $validated['role_name'] === 'administrador') {
            return back()->with('error', 'Gestores não podem atribuir o papel de administrador.');
        }

        try {
            if (!$user->isSuperAdmin()) {
                $empresaId = $user->empresa_id ?? $user->operacoes()->first()?->empresa_id;
                $query = $empresaId !== null ? User::where('empresa_id', $empresaId) : User::whereRaw('1 = 0');
                $query->findOrFail($id);
            }

            $this->permissionService->atribuirPapel($id, $validated['role_name']);
            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Papel atribuído com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atribuir papel: ' . $e->getMessage());
        }
    }

    /**
     * Remover papel do usuário
     */
    public function removerPapel(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role_name' => 'required|string|exists:roles,name',
        ]);

        try {
            $user = auth()->user();
            if (!$user->isSuperAdmin()) {
                $empresaId = $user->empresa_id ?? $user->operacoes()->first()?->empresa_id;
                $query = $empresaId !== null ? User::where('empresa_id', $empresaId) : User::whereRaw('1 = 0');
                $query->findOrFail($id);
            }

            $this->permissionService->removerPapel($id, $validated['role_name']);
            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Papel removido com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao remover papel: ' . $e->getMessage());
        }
    }

    /**
     * Atualizar operações do usuário (many-to-many)
     */
    public function atualizarOperacoes(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'operacoes' => 'nullable|array',
            'operacoes.*' => 'integer|exists:operacoes,id',
        ]);

        try {
            $user = auth()->user();
            $empresaId = $user->empresa_id ?? $user->operacoes()->first()?->empresa_id;

            $query = User::query();
            if (!$user->isSuperAdmin()) {
                if ($empresaId !== null) {
                    $query->where('empresa_id', $empresaId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $usuario = $query->findOrFail($id);
            $operacoesIds = $validated['operacoes'] ?? [];

            if (!$user->isSuperAdmin() && $empresaId !== null && !empty($operacoesIds)) {
                $operacoesValidas = Operacao::where('empresa_id', $empresaId)
                    ->whereIn('id', $operacoesIds)
                    ->pluck('id')
                    ->toArray();
                $operacoesIds = array_intersect($operacoesIds, $operacoesValidas);
            }
            
            // Sincronizar operações (adiciona novas, remove as que não estão na lista)
            $usuario->operacoes()->sync($operacoesIds);

            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Operações atualizadas com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar operações: ' . $e->getMessage());
        }
    }

    /**
     * Buscar usuários (consultores e gestores) para Select2
     * Usado no filtro de movimentações de caixa
     */
    public function buscar(Request $request)
    {
        $termo = $request->input('q', '');
        
        if (strlen($termo) < 2) {
            return response()->json(['results' => []]);
        }

        $user = auth()->user();
        
        // Buscar apenas consultores e gestores (não administradores)
        $query = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['consultor', 'gestor']);
        });

        // Filtrar por empresa do usuário logado (exceto super admin que vê todos)
        if (!$user->isSuperAdmin()) {
            $empresaId = $user->empresa_id ?? $user->operacoes()->first()?->empresa_id;
            if ($empresaId !== null) {
                $query->where('empresa_id', $empresaId);
            } else {
                return response()->json(['results' => []]);
            }
        }

        // Filtrar por operações do usuário logado (exceto administradores)
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereHas('operacoes', function($q) use ($operacoesIds) {
                    $q->whereIn('operacoes.id', $operacoesIds);
                });
            } else {
                // Se não tem operações vinculadas, retorna vazio
                return response()->json(['results' => []]);
            }
        }

        // Buscar por nome ou email
        $query->where(function($q) use ($termo) {
            $q->where('name', 'like', "%{$termo}%")
              ->orWhere('email', 'like', "%{$termo}%");
        });

        $usuarios = $query->with('roles')
            ->orderBy('name')
            ->limit(20)
            ->get();

        $results = $usuarios->map(function($usuario) {
            $roles = $usuario->roles->pluck('name')->map(fn($role) => ucfirst($role))->implode(', ');
            return [
                'id' => $usuario->id,
                'text' => $usuario->name . ' - ' . $usuario->email . ' (' . $roles . ')'
            ];
        });

        return response()->json(['results' => $results]);
    }
}
