<?php

namespace App\Modules\Approvals\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approvals\Services\AprovacaoService;
use App\Modules\Core\Models\Operacao;
use App\Support\ClienteRenovacaoCreditoLookup;
use App\Support\ClienteVinculosOperacoesLookup;
use App\Support\FichaContatoLookup;
use App\Support\OperacaoPreferida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AprovacaoController extends Controller
{
    protected AprovacaoService $aprovacaoService;

    public function __construct(AprovacaoService $aprovacaoService)
    {
        $this->middleware('auth');
        // Apenas quem tem papel administrador em alguma operação pode acessar
        $this->middleware(function ($request, $next) {
            if (empty(auth()->user()->getOperacoesIdsOndeTemPapel(['administrador']))) {
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

        // Operações disponíveis no filtro: Super Admin vê todas; demais só onde tem papel administrador
        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIdsAdmin = $user->getOperacoesIdsOndeTemPapel(['administrador']);
            $operacoes = ! empty($operacoesIdsAdmin)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIdsAdmin)->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId && ! $user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (empty($operacoesIds) || ! in_array((int) $operacaoId, $operacoesIds, true)) {
                $operacaoId = null;
            }
        }

        $pendentes = $this->aprovacaoService->listarPendentes($operacaoId, $user);

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($pendentes)
        );

        $cpfs = $pendentes
            ->map(fn ($e) => ClienteVinculosOperacoesLookup::cpfFromDocumento($e->cliente?->documento))
            ->filter()
            ->values()
            ->all();
        $operacoesIdsPorCpf = ClienteVinculosOperacoesLookup::operacoesIdsPorCpf($cpfs);

        $outrosVinculosPorEmprestimoId = [];
        foreach ($pendentes as $e) {
            $cpf = ClienteVinculosOperacoesLookup::cpfFromDocumento($e->cliente?->documento);
            if (! $cpf) {
                $outrosVinculosPorEmprestimoId[$e->id] = 0;

                continue;
            }
            $ops = $operacoesIdsPorCpf[$cpf] ?? [];
            $outros = array_values(array_filter($ops, fn ($opId) => (int) $opId !== (int) $e->operacao_id));
            $outrosVinculosPorEmprestimoId[$e->id] = count($outros);
        }

        $ehRenovacaoPorEmprestimoId = ClienteRenovacaoCreditoLookup::mapEhRenovacaoPorEmprestimoId($pendentes);

        return view('aprovacoes.index', compact(
            'pendentes',
            'operacoes',
            'operacaoId',
            'fichasContatoPorClienteOperacao',
            'outrosVinculosPorEmprestimoId',
            'ehRenovacaoPorEmprestimoId'
        ));
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
                $validated['motivo'] ?? null,
                auth()->user()
            );

            return redirect()->route('aprovacoes.index')
                ->with('success', 'Empréstimo aprovado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aprovar empréstimo: '.$e->getMessage());
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
                $validated['motivo_rejeicao'],
                auth()->user()
            );

            return redirect()->route('aprovacoes.index')
                ->with('success', 'Empréstimo rejeitado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao rejeitar empréstimo: '.$e->getMessage());
        }
    }
}
