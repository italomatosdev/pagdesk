<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\KanbanService;
use App\Modules\Core\Models\Operacao;
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
        
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;

        // Validar se o usuário tem acesso à operação selecionada
        if ($operacaoId && !$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null;
        }

        // Buscar operações disponíveis
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

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
