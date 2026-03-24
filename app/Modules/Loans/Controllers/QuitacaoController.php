<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\SolicitacaoQuitacao;
use App\Modules\Loans\Services\QuitacaoService;
use App\Support\ClienteNomeExibicao;
use App\Support\FichaContatoLookup;
use App\Support\OperacaoPreferida;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class QuitacaoController extends Controller
{
    public function __construct(
        protected QuitacaoService $quitacaoService
    ) {
        $this->middleware('auth');
    }

    /**
     * Tela para quitar o empréstimo por completo (valor total ou com desconto).
     */
    public function quitar(int $id): View|RedirectResponse
    {
        $emprestimo = Emprestimo::with(['cliente', 'operacao', 'parcelas'])->findOrFail($id);

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            abort(403, 'Você não tem permissão para quitar este empréstimo.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        if (!$emprestimo->isAtivo()) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'Apenas empréstimos ativos podem ser quitados por esta tela.');
        }

        $parcelasAbertas = $this->quitacaoService->getParcelasEmAberto($emprestimo);
        if ($parcelasAbertas->isEmpty()) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('info', 'Este empréstimo não possui parcelas em aberto.');
        }

        $saldoDevedor = $this->quitacaoService->getSaldoDevedor($emprestimo);

        return view('quitacao.quitar', [
            'emprestimo' => $emprestimo,
            'saldoDevedor' => $saldoDevedor,
            'parcelasAbertas' => $parcelasAbertas,
            'nomeClienteExibicao' => ClienteNomeExibicao::forEmprestimo($emprestimo),
        ]);
    }

    /**
     * Processa a quitação: valor total = executa direto; com desconto = cria solicitação para aprovação.
     */
    public function store(Request $request): RedirectResponse
    {
        $emprestimo = Emprestimo::findOrFail($request->input('emprestimo_id'));

        $user = auth()->user();
        if (!$user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            abort(403, 'Você não tem permissão para quitar este empréstimo.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        $saldoDevedor = $this->quitacaoService->getSaldoDevedor($emprestimo);
        $rawValor = $request->input('valor', 0);
        $valorSolicitado = is_string($rawValor) && str_contains($rawValor, ',')
            ? (float) str_replace(['.', ','], ['', '.'], $rawValor)
            : (float) $rawValor;

        $temDesconto = $valorSolicitado < $saldoDevedor;
        $rules = [
            'emprestimo_id' => 'required|exists:emprestimos,id',
            'valor' => 'required|numeric|min:0',
            'data_pagamento' => 'required|date',
            'metodo' => 'required|in:dinheiro,pix,transferencia,outro',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'observacoes' => 'nullable|string|max:1000',
        ];
        if ($temDesconto) {
            $rules['motivo_desconto'] = 'required|string|min:10|max:1000';
        }

        $validated = $request->validate($rules, [
            'motivo_desconto.required' => 'Ao informar valor menor que o saldo devedor, o motivo do desconto é obrigatório.',
            'motivo_desconto.min' => 'O motivo do desconto deve ter no mínimo 10 caracteres.',
        ]);

        if ($valorSolicitado <= 0) {
            return back()->withInput()->with('error', 'Informe um valor maior que zero.');
        }
        if ($valorSolicitado > $saldoDevedor) {
            return back()->withInput()->with('error', 'O valor não pode ser maior que o saldo devedor (R$ ' . number_format($saldoDevedor, 2, ',', '.') . ').');
        }

        $comprovantePath = null;
        if ($request->hasFile('comprovante')) {
            try {
                $file = $request->file('comprovante');
                $comprovantePath = $file->store('comprovantes', 'public');
                if (!$comprovantePath || !is_string($comprovantePath)) {
                    \Log::warning('Quitação: store() do comprovante retornou valor inválido', ['retorno' => $comprovantePath]);
                    $comprovantePath = null;
                }
            } catch (\Exception $e) {
                \Log::error('Erro ao fazer upload do comprovante (quitação): ' . $e->getMessage());
                return back()->withInput()->with('error', 'Erro ao enviar comprovante. Tente novamente.');
            }
        }

        $payload = [
            'valor_solicitado' => $valorSolicitado,
            'valor' => $valorSolicitado,
            'data_pagamento' => $validated['data_pagamento'],
            'metodo' => $validated['metodo'],
            'consultor_id' => $user->id,
            'comprovante_path' => $comprovantePath ? (string) $comprovantePath : null,
            'observacoes' => $validated['observacoes'] ?? null,
            'motivo_desconto' => $validated['motivo_desconto'] ?? null,
        ];

        try {
            if ($temDesconto) {
                $this->quitacaoService->solicitarQuitacaoComDesconto($emprestimo, $payload);
                return redirect()->route('emprestimos.show', $emprestimo->id)
                    ->with('success', 'Solicitação de quitação com desconto enviada. Aguarde a aprovação do gestor ou administrador.');
            }

            $this->quitacaoService->executarQuitacao($emprestimo, $payload);
            $msg = $comprovantePath
                ? 'Empréstimo quitado com sucesso. Comprovante anexado aos pagamentos das parcelas.'
                : 'Empréstimo quitado com sucesso.';
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('success', $msg);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Erro ao processar quitação: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Erro ao processar quitação. Tente novamente.');
        }
    }

    /**
     * Listagem de solicitações de quitação pendentes (gestor e administrador).
     */
    public function indexPendentes(Request $request): View
    {
        $user = auth()->user();
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Apenas gestores e administradores podem ver solicitações de quitação.');
        }

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);

        $query = SolicitacaoQuitacao::with(['emprestimo.cliente', 'emprestimo.operacao', 'solicitante'])
            ->where('status', 'pendente');
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if ($operacaoId) {
            $query->whereHas('emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }
        $solicitacoes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapByClienteOperacaoPairs(
            FichaContatoLookup::pairsFromEmprestimos($solicitacoes->map(fn ($s) => $s->emprestimo)->filter())
        );

        return view('quitacao.pendentes', compact('solicitacoes', 'operacoes', 'operacaoId', 'fichasContatoPorClienteOperacao'));
    }

    /**
     * Aprovar solicitação de quitação com desconto.
     */
    public function aprovar(int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoQuitacao::with('emprestimo')->findOrFail($id);
        if (!$user->temAlgumPapelNaOperacao($solicitacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem aprovar solicitações de quitação.');
        }

        try {
            $this->quitacaoService->aprovarSolicitacao($solicitacao, $user->id);
            return redirect()->route('quitacao.pendentes')
                ->with('success', 'Solicitação aprovada e empréstimo quitado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Rejeitar solicitação de quitação com desconto.
     */
    public function rejeitar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $solicitacao = SolicitacaoQuitacao::with('emprestimo')->findOrFail($id);
        if (!$user->temAlgumPapelNaOperacao($solicitacao->emprestimo->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem rejeitar solicitações de quitação.');
        }

        $validated = $request->validate([
            'motivo_rejeicao' => 'required|string|min:10|max:500',
        ]);

        try {
            $this->quitacaoService->rejeitarSolicitacao($solicitacao, $user->id, $validated['motivo_rejeicao']);
            return redirect()->route('quitacao.pendentes')
                ->with('success', 'Solicitação rejeitada.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }
}
