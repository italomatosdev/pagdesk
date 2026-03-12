<?php

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Models\Notificacao;
use App\Modules\Core\Services\NotificacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class NotificacaoController
{
    protected NotificacaoService $notificacaoService;

    public function __construct(NotificacaoService $notificacaoService)
    {
        $this->notificacaoService = $notificacaoService;
    }

    /**
     * Listar notificações do usuário autenticado
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'notificacoes' => [],
                    'nao_lidas' => 0,
                ], 401);
            }

            $limit = $request->input('limit', 20);
            $apenasNaoLidas = $request->boolean('apenas_nao_lidas', false);

            $notificacoes = $this->notificacaoService->listar($userId, $limit, $apenasNaoLidas);
            $naoLidas = $this->notificacaoService->contarNaoLidas($userId);

            // Garantir que os accessors sejam incluídos no JSON
            $notificacoesArray = $notificacoes->map(function ($notificacao) {
                return [
                    'id' => $notificacao->id,
                    'tipo' => $notificacao->tipo,
                    'titulo' => $notificacao->titulo,
                    'mensagem' => $notificacao->mensagem,
                    'url' => $notificacao->url,
                    'lida' => $notificacao->lida,
                    'icone' => $notificacao->icone,
                    'cor' => $notificacao->cor,
                    'tempo_relativo' => $notificacao->tempo_relativo,
                    'created_at' => $notificacao->created_at,
                ];
            });

            return response()->json([
                'notificacoes' => $notificacoesArray,
                'nao_lidas' => $naoLidas,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar notificações: ' . $e->getMessage());
            return response()->json([
                'notificacoes' => [],
                'nao_lidas' => 0,
                'error' => 'Erro ao carregar notificações',
            ], 500);
        }
    }

    /**
     * Marcar notificação como lida
     *
     * @param int $id
     * @return JsonResponse
     */
    public function marcarComoLida(int $id): JsonResponse
    {
        $userId = Auth::id();
        $sucesso = $this->notificacaoService->marcarComoLida($id, $userId);

        if ($sucesso) {
            $naoLidas = $this->notificacaoService->contarNaoLidas($userId);
            return response()->json([
                'success' => true,
                'nao_lidas' => $naoLidas,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Notificação não encontrada.',
        ], 404);
    }

    /**
     * Marcar todas as notificações como lidas
     *
     * @return JsonResponse
     */
    public function marcarTodasComoLidas(): JsonResponse
    {
        $userId = Auth::id();
        $this->notificacaoService->marcarTodasComoLidas($userId);

        return response()->json([
            'success' => true,
            'nao_lidas' => 0,
        ]);
    }

    /**
     * Contar notificações não lidas
     *
     * @return JsonResponse
     */
    public function contarNaoLidas(): JsonResponse
    {
        $userId = Auth::id();
        $naoLidas = $this->notificacaoService->contarNaoLidas($userId);

        return response()->json([
            'nao_lidas' => $naoLidas,
        ]);
    }

    /**
     * Listar todas as notificações (página web)
     *
     * @param Request $request
     * @return View
     */
    public function listarTodas(Request $request): View
    {
        $userId = Auth::id();
        $filtro = $request->input('filtro', 'todas'); // todas, lidas, nao_lidas
        $perPage = $request->input('per_page', 20);

        $notificacoes = $this->notificacaoService->listarComPaginacao($userId, $perPage, $filtro);
        $naoLidas = $this->notificacaoService->contarNaoLidas($userId);
        
        // Contar total de lidas
        $totalLidas = Notificacao::where('user_id', $userId)
            ->where('lida', true)
            ->count();

        return view('notificacoes.index', compact('notificacoes', 'naoLidas', 'totalLidas', 'filtro'));
    }

    /**
     * Marcar notificação como lida (via web)
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function marcarLida(int $id): RedirectResponse
    {
        $userId = Auth::id();
        $sucesso = $this->notificacaoService->marcarComoLida($id, $userId);

        if ($sucesso) {
            return redirect()->route('notificacoes.index')
                ->with('success', 'Notificação marcada como lida.');
        }

        return redirect()->route('notificacoes.index')
            ->with('error', 'Notificação não encontrada.');
    }

    /**
     * Marcar todas as notificações como lidas (via web)
     *
     * @return RedirectResponse
     */
    public function marcarTodasLidas(): RedirectResponse
    {
        $userId = Auth::id();
        $this->notificacaoService->marcarTodasComoLidas($userId);

        return redirect()->route('notificacoes.index')
            ->with('success', 'Todas as notificações foram marcadas como lidas.');
    }
}
