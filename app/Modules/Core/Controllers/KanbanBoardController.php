<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\KanbanService;
use App\Modules\Core\Models\Operacao;
use App\Support\OperacaoPreferida;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KanbanBoardController extends Controller
{
    protected KanbanService $kanbanService;

    public function __construct(KanbanService $kanbanService)
    {
        $this->middleware('auth');
        $this->kanbanService = $kanbanService;
    }

    /**
     * Exibir o Kanban Board
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode acessar o Kanban
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar o Painel de Pendências.');
        }
        
        // Operações disponíveis no select: apenas as do usuário (Super Admin não acessa Kanban)
        $operacoesIds = $user->getOperacoesIds();
        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);

        // Buscar pendências
        $pendencias = $this->kanbanService->buscarPendencias($operacaoId, $user);
        $contadores = $this->kanbanService->contarPendencias($operacaoId, $user);

        return view('kanban.index', compact(
            'pendencias',
            'contadores',
            'operacoes',
            'operacaoId'
        ));
    }
}
