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

        // Filtro por papel na operação (operacao_user.role)
        if ($request->filled('role') && in_array($request->role, ['consultor', 'gestor', 'administrador'], true)) {
            $query->whereHas('operacoes', function ($q) use ($request) {
                $q->where('operacao_user.role', $request->role);
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

        // Filtro por status da conta (ativo/bloqueado)
        if ($request->filled('ativo')) {
            if ($request->ativo === '1') {
                $query->where('ativo', true);
            } elseif ($request->ativo === '0') {
                $query->where('ativo', false);
            }
        }

        $usuarios = $query->orderBy('created_at', 'desc')->paginate(20);

        // Buscar empresas para o filtro
        $empresas = Empresa::orderBy('nome')->get();

        // Buscar roles distintos
        $roles = Role::orderBy('name')->get();

        // Estatísticas (usuários com pelo menos uma operação com esse papel; total inclui bloqueados)
        $stats = [
            'total' => User::where('is_super_admin', false)->count(),
            'ativos' => User::where('is_super_admin', false)->where('ativo', true)->count(),
            'bloqueados' => User::where('is_super_admin', false)->where('ativo', false)->count(),
            'administradores' => User::where('is_super_admin', false)->whereHas('operacoes', fn ($q) => $q->where('operacao_user.role', 'administrador'))->count(),
            'gestores' => User::where('is_super_admin', false)->whereHas('operacoes', fn ($q) => $q->where('operacao_user.role', 'gestor'))->count(),
            'consultores' => User::where('is_super_admin', false)->whereHas('operacoes', fn ($q) => $q->where('operacao_user.role', 'consultor'))->count(),
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
            'operacoes' => 'nullable|array',
            'operacoes.*' => 'integer|exists:operacoes,id',
            'operacao_role' => 'nullable|array',
            'operacao_role.*' => 'in:consultor,gestor,administrador',
            'ativo' => 'nullable|boolean',
            'motivo_bloqueio' => 'nullable|string|max:500',
        ]);

        try {
            $usuario->name = $validated['name'];
            $usuario->email = $validated['email'];

            if (!empty($validated['password'])) {
                $usuario->password = Hash::make($validated['password']);
            }

            if ($usuario->empresa_id != $validated['empresa_id']) {
                $usuario->operacoes()->detach();
            }

            $usuario->empresa_id = $validated['empresa_id'];
            $usuario->ativo = $request->boolean('ativo');
            $usuario->motivo_bloqueio = $request->filled('motivo_bloqueio') ? $request->input('motivo_bloqueio') : null;
            $usuario->save();

            $operacoesIds = $validated['operacoes'] ?? [];
            $operacaoRole = $request->input('operacao_role', []);

            if (!empty($operacoesIds)) {
                $operacoesValidas = Operacao::withoutGlobalScopes()
                    ->where('empresa_id', $validated['empresa_id'])
                    ->whereIn('id', $operacoesIds)
                    ->pluck('id')
                    ->toArray();

                $sync = [];
                foreach ($operacoesValidas as $opId) {
                    $sync[$opId] = ['role' => $operacaoRole[$opId] ?? 'consultor'];
                }
                $usuario->operacoes()->sync($sync);

                $papeisUnicos = array_unique(array_column($sync, 'role'));
                $roleIds = Role::whereIn('name', $papeisUnicos)->pluck('id')->toArray();
                $usuario->roles()->sync($roleIds);
            } else {
                $usuario->operacoes()->detach();
                $usuario->roles()->sync([]);
            }

            return redirect()->route('super-admin.usuarios.show', $usuario->id)
                ->with('success', 'Usuário atualizado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar usuário: ' . $e->getMessage())->withInput();
        }
    }
}
