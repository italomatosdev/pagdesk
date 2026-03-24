<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
                abort(403, 'Acesso negado. Apenas administradores e gestores podem gerenciar usuários.');
            }

            return $next($request);
        });
        $this->permissionService = $permissionService;
    }

    /**
     * Listar usuários
     * Super Admin vê todos; administrador/gestor vê apenas usuários das mesmas operações.
     */
    public function index(): View
    {
        $query = User::with(['roles', 'operacoes']);
        $user = auth()->user();

        // Apenas Super Admin vê todos os usuários do sistema
        if (! $user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('operacoes', fn ($q) => $q->whereIn('operacoes.id', $operacoesIds));
            }
        }

        $usuarios = $query->orderBy('name')->paginate(15);
        $roles = Role::all();

        return view('usuarios.index', compact('usuarios', 'roles'));
    }

    /**
     * Mostrar formulário para criar usuário (nas operações do administrador/gestor)
     */
    public function create(): View
    {
        $user = auth()->user();
        $operacoesIds = $user->getOperacoesIds();
        if (empty($operacoesIds)) {
            abort(403, 'Sua conta não está vinculada a nenhuma operação. Entre em contato com o suporte.');
        }

        $empresa = $user->operacoes()->first()?->empresa;
        if (! $empresa) {
            abort(403, 'Sua conta não está vinculada a uma empresa. Entre em contato com o suporte.');
        }

        $roles = Role::orderBy('name')->get();
        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador']))) {
            $roles = $roles->filter(fn ($r) => $r->name !== 'administrador');
        }
        $operacoes = Operacao::whereIn('id', $operacoesIds)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();

        return view('usuarios.create', compact('empresa', 'roles', 'operacoes'));
    }

    /**
     * Criar usuário (vinculado às operações do administrador/gestor)
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $operacoesPermitidas = $user->getOperacoesIds();
        if (empty($operacoesPermitidas)) {
            abort(403, 'Sua conta não está vinculada a nenhuma operação.');
        }

        $empresa = $user->operacoes()->first()?->empresa;
        if (! $empresa) {
            abort(403, 'Sua conta não está vinculada a uma empresa.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'operacoes' => 'nullable|array',
            'operacoes.*' => 'integer|exists:operacoes,id',
            'operacao_role' => 'nullable|array',
            'operacao_role.*' => 'in:consultor,gestor,administrador',
        ]);

        $operacoesIds = $validated['operacoes'] ?? [];
        $operacaoRole = $request->input('operacao_role', []);
        if (! empty($operacoesIds)) {
            $operacoesIds = array_values(array_intersect($operacoesIds, $operacoesPermitidas));
        }

        $temAdministrador = ! empty($operacoesIds) && in_array('administrador', array_map(fn ($id) => $operacaoRole[$id] ?? 'consultor', $operacoesIds), true);
        if ($temAdministrador && empty($user->getOperacoesIdsOndeTemPapel(['administrador']))) {
            return back()->with('error', 'Apenas administradores podem atribuir o papel de administrador em uma operação.')->withInput();
        }

        try {
            $usuario = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'empresa_id' => $empresa->id,
                'is_super_admin' => false,
            ]);

            $sync = [];
            foreach ($operacoesIds as $opId) {
                $sync[$opId] = ['role' => $operacaoRole[$opId] ?? 'consultor'];
            }
            $usuario->operacoes()->sync($sync);

            $papeisUnicos = array_unique(array_column($sync, 'role'));
            $roleIds = Role::whereIn('name', $papeisUnicos)->pluck('id')->toArray();
            $usuario->roles()->sync($roleIds);

            return redirect()->route('usuarios.show', $usuario->id)
                ->with('success', 'Usuário criado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar usuário: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar detalhes do usuário (apenas se pertencer às mesmas operações)
     */
    public function show(int $id): View
    {
        $query = User::with(['roles', 'operacao', 'operacoes']);
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('operacoes', fn ($q) => $q->whereIn('operacoes.id', $operacoesIds));
            }
        }

        $usuario = $query->findOrFail($id);
        $roles = Role::all();
        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador']))) {
            $roles = $roles->filter(fn ($r) => $r->name !== 'administrador');
        }

        $operacoesQuery = Operacao::where('ativo', true);
        if (! $user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds)) {
                $operacoesQuery->whereRaw('1 = 0');
            } else {
                $operacoesQuery->whereIn('id', $operacoesIds);
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

        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['administrador'])) && $validated['role_name'] === 'administrador') {
            return back()->with('error', 'Apenas administradores podem atribuir o papel de administrador.');
        }

        try {
            if (! $user->isSuperAdmin()) {
                $operacoesIds = $user->getOperacoesIds();
                $query = empty($operacoesIds)
                    ? User::whereRaw('1 = 0')
                    : User::whereHas('operacoes', fn ($q) => $q->whereIn('operacoes.id', $operacoesIds));
                $query->findOrFail($id);
            }

            $this->permissionService->atribuirPapel($id, $validated['role_name']);

            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Papel atribuído com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atribuir papel: '.$e->getMessage());
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
            if (! $user->isSuperAdmin()) {
                $operacoesIds = $user->getOperacoesIds();
                $query = empty($operacoesIds)
                    ? User::whereRaw('1 = 0')
                    : User::whereHas('operacoes', fn ($q) => $q->whereIn('operacoes.id', $operacoesIds));
                $query->findOrFail($id);
            }

            $this->permissionService->removerPapel($id, $validated['role_name']);

            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Papel removido com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao remover papel: '.$e->getMessage());
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
            'operacao_role' => 'nullable|array',
            'operacao_role.*' => 'in:consultor,gestor,administrador',
        ]);

        try {
            $user = auth()->user();
            $operacoesPermitidas = $user->getOperacoesIds();

            $query = User::query();
            if (! $user->isSuperAdmin()) {
                if (empty($operacoesPermitidas)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('operacoes', fn ($q) => $q->whereIn('operacoes.id', $operacoesPermitidas));
                }
            }

            $usuario = $query->findOrFail($id);
            $operacoesIds = $validated['operacoes'] ?? [];
            $operacaoRole = $request->input('operacao_role', []);

            if (! $user->isSuperAdmin() && ! empty($operacoesIds)) {
                $operacoesIds = array_values(array_intersect($operacoesIds, $operacoesPermitidas));
            }

            $temAdministrador = ! empty($operacoesIds) && in_array('administrador', array_map(fn ($opId) => $operacaoRole[$opId] ?? 'consultor', $operacoesIds), true);
            if ($temAdministrador && empty($user->getOperacoesIdsOndeTemPapel(['administrador']))) {
                return back()->with('error', 'Apenas administradores podem atribuir o papel de administrador em uma operação.');
            }

            $sync = [];
            foreach ($operacoesIds as $opId) {
                $sync[$opId] = ['role' => $operacaoRole[$opId] ?? 'consultor'];
            }
            $usuario->operacoes()->sync($sync);

            $papeisUnicos = array_unique(array_column($sync, 'role'));
            $roleIds = Role::whereIn('name', $papeisUnicos)->pluck('id')->toArray();
            $usuario->roles()->sync($roleIds);

            return redirect()->route('usuarios.show', $id)
                ->with('success', 'Operações e papéis atualizados com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao atualizar operações: '.$e->getMessage());
        }
    }

    /**
     * Buscar usuários para Select2 (filtros de caixa / prestações).
     *
     * Sem `operacao_id`: consultores e gestores em qualquer operação à qual o logado tenha acesso (comportamento legado).
     * Com `operacao_id`: qualquer usuário vinculado àquela operação (qualquer papel em `operacao_user`), para alinhar ao fechamento de caixa por operação.
     */
    public function buscar(Request $request)
    {
        $termo = $request->input('q', '');

        if (strlen($termo) < 2) {
            return response()->json(['results' => [], 'total_count' => 0]);
        }

        $user = auth()->user();

        $operacaoIdRaw = $request->input('operacao_id');
        $operacaoId = $operacaoIdRaw !== null && $operacaoIdRaw !== '' ? (int) $operacaoIdRaw : null;
        $operacaoValida = null;

        if ($operacaoId !== null && $operacaoId > 0) {
            if ($user->isSuperAdmin()) {
                $operacaoValida = Operacao::where('id', $operacaoId)->where('ativo', true)->exists()
                    ? $operacaoId
                    : null;
            } else {
                $opsIds = $user->getOperacoesIds();
                $operacaoValida = (! empty($opsIds) && in_array($operacaoId, $opsIds, true))
                    ? $operacaoId
                    : null;
            }
        }

        if ($request->filled('operacao_id') && ($operacaoValida === null || $operacaoValida < 1)) {
            return response()->json([
                'results' => [],
                'total_count' => 0,
                'error' => 'Operação inválida ou sem permissão.',
            ]);
        }

        $query = User::query();

        if ($operacaoValida !== null && $operacaoValida > 0) {
            $query->whereHas('operacoes', function ($q) use ($operacaoValida) {
                $q->where('operacoes.id', $operacaoValida);
            });
        } else {
            $query->whereHas('operacoes', function ($q) {
                $q->whereIn('operacao_user.role', ['consultor', 'gestor']);
            });

            if (! $user->isSuperAdmin()) {
                $operacoesIds = $user->getOperacoesIds();
                if (empty($operacoesIds)) {
                    return response()->json(['results' => [], 'total_count' => 0]);
                }
                $query->whereHas('operacoes', function ($q) use ($operacoesIds) {
                    $q->whereIn('operacoes.id', $operacoesIds);
                });
            }
        }

        $query->where(function ($q) use ($termo) {
            $q->where('name', 'like', "%{$termo}%")
                ->orWhere('email', 'like', "%{$termo}%");
        });

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $totalCount = (clone $query)->count();

        $usuarios = $query->with('operacoes')
            ->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $authId = auth()->id();
        $results = $usuarios->map(function ($usuario) use ($operacaoValida, $authId) {
            if ($operacaoValida !== null && $operacaoValida > 0) {
                $papeis = $usuario->operacoes->where('id', $operacaoValida)
                    ->pluck('pivot.role')->filter()->unique()->map(fn ($r) => ucfirst((string) $r))->implode(', ');
            } else {
                $papeis = $usuario->operacoes->pluck('pivot.role')->filter()->unique()->map(fn ($r) => ucfirst((string) $r))->implode(', ');
            }
            $texto = $usuario->name.' - '.$usuario->email.($papeis !== '' ? ' ('.$papeis.')' : '');
            if ((int) $usuario->id === (int) $authId) {
                $texto .= ' (Você)';
            }

            return [
                'id' => $usuario->id,
                'text' => $texto,
            ];
        });

        return response()->json([
            'results' => $results,
            'total_count' => $totalCount,
        ]);
    }
}
