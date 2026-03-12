<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Listar todos os logs de auditoria
     */
    public function index(Request $request): View
    {
        $query = Auditoria::with(['user']);

        // Filtros
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->filled('model_type')) {
            // Buscar pelo nome completo da classe que termina com o basename
            $modelTypeFilter = $request->input('model_type');
            $query->where('model_type', 'like', '%\\' . $modelTypeFilter);
        }

        if ($request->filled('model_id')) {
            $query->where('model_id', $request->input('model_id'));
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->input('data_inicio'));
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->input('data_fim'));
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->input('ip_address') . '%');
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        // Usuários para filtro
        $usuarios = User::orderBy('name')->get();

        // Ações únicas para filtro
        $acoes = Auditoria::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Modelos únicos para filtro (mostrar apenas basename, mas buscar pelo nome completo)
        $modelosCompletos = Auditoria::select('model_type')
            ->whereNotNull('model_type')
            ->distinct()
            ->orderBy('model_type')
            ->pluck('model_type');
        
        $modelos = $modelosCompletos->map(function ($model) {
            return class_basename($model);
        })->unique()->sort()->values();

        // Estatísticas
        $stats = [
            'total' => Auditoria::count(),
            'hoje' => Auditoria::whereDate('created_at', today())->count(),
            'semana' => Auditoria::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'mes' => Auditoria::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return view('super-admin.auditoria.index', compact('logs', 'usuarios', 'acoes', 'modelos', 'stats'));
    }

    /**
     * Mostrar detalhes de um log
     */
    public function show(int $id): View
    {
        $log = Auditoria::with(['user'])->findOrFail($id);

        // Tentar carregar o modelo relacionado se existir
        $modelo = null;
        if ($log->model_type && $log->model_id) {
            try {
                $modelo = $log->model_type::find($log->model_id);
            } catch (\Exception $e) {
                // Modelo pode não existir mais ou classe não encontrada
            }
        }

        return view('super-admin.auditoria.show', compact('log', 'modelo'));
    }
}
