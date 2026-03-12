<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Empresa;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isSuperAdmin()) {
                abort(403, 'Acesso negado. Apenas Super Admin pode acessar esta área.');
            }
            return $next($request);
        });
    }

    /**
     * Listar todos os usuários de todas as empresas
     */
    public function index(Request $request): View
    {
        $query = User::with(['empresa', 'roles', 'operacoes'])
            ->where('is_super_admin', false); // Excluir super admins da listagem

        // Filtro por empresa
        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        // Filtro por papel (role)
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filtro por busca (nome ou email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por status (ativo/inativo)
        if ($request->filled('status')) {
            if ($request->status === 'ativo') {
                $query->whereNull('deleted_at');
            } elseif ($request->status === 'inativo') {
                $query->onlyTrashed();
            }
        }

        $usuarios = $query->orderBy('created_at', 'desc')->paginate(20);

        // Buscar empresas para o filtro
        $empresas = Empresa::orderBy('nome')->get();

        // Buscar roles distintos
        $roles = Role::orderBy('name')->get();

        // Estatísticas
        $stats = [
            'total' => User::where('is_super_admin', false)->count(),
            'administradores' => User::where('is_super_admin', false)->whereHas('roles', function ($q) {
                $q->where('name', 'administrador');
            })->count(),
            'gestores' => User::where('is_super_admin', false)->whereHas('roles', function ($q) {
                $q->where('name', 'gestor');
            })->count(),
            'consultores' => User::where('is_super_admin', false)->whereHas('roles', function ($q) {
                $q->where('name', 'consultor');
            })->count(),
        ];

        return view('super-admin.usuarios.index', compact('usuarios', 'empresas', 'roles', 'stats'));
    }

    /**
     * Mostrar detalhes do usuário
     */
    public function show(int $id): View
    {
        $usuario = User::with(['empresa', 'roles', 'operacoes'])
            ->where('is_super_admin', false)
            ->findOrFail($id);

        // Buscar roles e operações para edição
        $roles = Role::orderBy('name')->get();
        $operacoes = $usuario->empresa_id 
            ? Operacao::withoutGlobalScopes()
                ->where('empresa_id', $usuario->empresa_id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
            : collect([]);

        // Buscar empresas para possível alteração
        $empresas = Empresa::where('status', 'ativa')->orderBy('nome')->get();

        return view('super-admin.usuarios.show', compact('usuario', 'roles', 'operacoes', 'empresas'));
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $usuario = User::where('is_super_admin', false)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'empresa_id' => 'required|exists:empresas,id',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'operacoes' => 'nullable|array',
            'operacoes.*' => 'integer|exists:operacoes,id',
        ]);

        try {
            // Atualizar dados básicos
            $usuario->name = $validated['name'];
            $usuario->email = $validated['email'];
            
            // Atualizar senha apenas se informada
            if (!empty($validated['password'])) {
                $usuario->password = Hash::make($validated['password']);
            }

            // Se mudou de empresa, limpar operações antigas
            if ($usuario->empresa_id != $validated['empresa_id']) {
                $usuario->operacoes()->detach();
            }
            
            $usuario->empresa_id = $validated['empresa_id'];
            $usuario->save();

            // Atualizar papéis
            $roleIds = Role::whereIn('name', $validated['roles'])->pluck('id')->toArray();
            $usuario->roles()->sync($roleIds);

            // Atualizar operações (se informadas)
            if (!empty($validated['operacoes'])) {
                // Validar que as operações pertencem à empresa
                $operacoesValidas = Operacao::withoutGlobalScopes()
                    ->where('empresa_id', $validated['empresa_id'])
                    ->whereIn('id', $validated['operacoes'])
                    ->pluck('id')
                    ->toArray();
                
                $usuario->operacoes()->sync($operacoesValidas);
            } else {
                $usuario->operacoes()->detach();
            }

            return redirect()->route('super-admin.usuarios.show', $usuario->id)
                ->with('success', 'Usuário atualizado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage())->withInput();
        }
    }
}
