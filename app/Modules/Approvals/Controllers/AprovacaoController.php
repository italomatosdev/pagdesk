<?php

namespace App\Modules\Approvals\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approvals\Services\AprovacaoService;
use App\Modules\Core\Models\Operacao;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class AprovacaoController extends Controller
{
    protected AprovacaoService $aprovacaoService;

    public function __construct(AprovacaoService $aprovacaoService)
    {
        $this->middleware('auth');
        // Apenas administradores podem acessar
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('administrador')) {
                abort(403, 'Acesso negado. Apenas administradores podem aprovar empréstimos.');
            }
            return $next($request);
        });
        $this->aprovacaoService = $aprovacaoService;
    }

    /**
     * Listar empréstimos pendentes
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $operacaoId = $request->input('operacao_id');
        
        // Validar se o usuário tem acesso à operação selecionada
        if ($operacaoId && !$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null; // Resetar se não tiver acesso
        }
        
        $pendentes = $this->aprovacaoService->listarPendentes($operacaoId, $user);
        
        // Filtrar operações disponíveis para o usuário
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        return view('aprovacoes.index', compact('pendentes', 'operacoes', 'operacaoId'));
    }

    /**
     * Aprovar empréstimo
     */
    public function aprovar(Request $request, int $emprestimoId): RedirectResponse
    {
        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        try {
            $this->aprovacaoService->aprovar(
                $emprestimoId,
                auth()->id(),
                $validated['motivo'] ?? null
            );

            return redirect()->route('aprovacoes.index')
                ->with('success', 'Empréstimo aprovado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aprovar empréstimo: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar empréstimo
     */
    public function rejeitar(Request $request, int $emprestimoId): RedirectResponse
    {
        $validated = $request->validate([
            'motivo_rejeicao' => 'required|string|max:500',
        ]);

        try {
            $this->aprovacaoService->rejeitar(
                $emprestimoId,
                auth()->id(),
                $validated['motivo_rejeicao']
            );

            return redirect()->route('aprovacoes.index')
                ->with('success', 'Empréstimo rejeitado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao rejeitar empréstimo: ' . $e->getMessage());
        }
    }
}
