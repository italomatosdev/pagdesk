<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\EmprestimoCheque;
use App\Modules\Loans\Services\ChequeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChequeController extends Controller
{
    protected ChequeService $chequeService;

    public function __construct(ChequeService $chequeService)
    {
        $this->middleware('auth');
        $this->chequeService = $chequeService;
    }

    /**
     * Listar cheques (visão geral)
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = EmprestimoCheque::with(['emprestimo.cliente', 'emprestimo.operacao'])
            ->orderBy('data_vencimento')
            ->orderBy('id');

        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $operacaoId = $request->input('operacao_id');
        if ($operacaoId !== null && $operacaoId !== '') {
            $operacaoId = (int) $operacaoId;
            if ($user->isSuperAdmin() || in_array($operacaoId, $user->getOperacoesIds(), true)) {
                $query->whereHas('emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
            }
        }

        $filtros = [
            'status' => $request->input('status'),
            'operacao_id' => $operacaoId,
            'data_vencimento_de' => $request->input('data_vencimento_de'),
            'data_vencimento_ate' => $request->input('data_vencimento_ate'),
        ];

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (!empty($filtros['data_vencimento_de'])) {
            $query->whereDate('data_vencimento', '>=', $filtros['data_vencimento_de']);
        }

        if (!empty($filtros['data_vencimento_ate'])) {
            $query->whereDate('data_vencimento', '<=', $filtros['data_vencimento_ate']);
        }

        $totais = (clone $query)->reorder()->selectRaw('COUNT(*) as total, COALESCE(SUM(valor_cheque), 0) as valor_bruto, COALESCE(SUM(valor_liquido), 0) as valor_liquido')->first();
        $cheques = $query->paginate(20)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        $titulo = 'Cheques';

        return view('cheques.index', compact('cheques', 'filtros', 'titulo', 'totais', 'operacoes'));
    }

    /**
     * Listar cheques com vencimento hoje (ou pela data escolhida no filtro)
     */
    public function hoje(Request $request): View
    {
        $user = auth()->user();
        $hoje = Carbon::today();
        $dataDe = $request->input('data_vencimento_de');
        $dataAte = $request->input('data_vencimento_ate');

        $query = EmprestimoCheque::with(['emprestimo.cliente', 'emprestimo.operacao'])
            ->orderBy('data_vencimento')
            ->orderBy('id');

        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (!empty($opsIds)) {
                $query->whereHas('emprestimo', fn ($q) => $q->whereIn('operacao_id', $opsIds));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $operacaoId = $request->input('operacao_id');
        if ($operacaoId !== null && $operacaoId !== '') {
            $operacaoId = (int) $operacaoId;
            if ($user->isSuperAdmin() || in_array($operacaoId, $user->getOperacoesIds(), true)) {
                $query->whereHas('emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
            }
        }

        // Se o usuário informou filtro de data, usa o intervalo; senão filtra só por "hoje"
        if (!empty($dataDe) || !empty($dataAte)) {
            if (!empty($dataDe)) {
                $query->whereDate('data_vencimento', '>=', $dataDe);
            }
            if (!empty($dataAte)) {
                $query->whereDate('data_vencimento', '<=', $dataAte);
            }
            $filtros = [
                'status' => $request->input('status'),
                'operacao_id' => $operacaoId,
                'data_vencimento_de' => $dataDe,
                'data_vencimento_ate' => $dataAte,
            ];
        } else {
            $query->whereDate('data_vencimento', $hoje);
            $filtros = [
                'status' => $request->input('status'),
                'operacao_id' => $operacaoId,
                'data_vencimento_de' => $hoje->format('Y-m-d'),
                'data_vencimento_ate' => $hoje->format('Y-m-d'),
            ];
        }

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        $totais = (clone $query)->reorder()->selectRaw('COUNT(*) as total, COALESCE(SUM(valor_cheque), 0) as valor_bruto, COALESCE(SUM(valor_liquido), 0) as valor_liquido')->first();
        $cheques = $query->paginate(20)->withQueryString();

        if ($user->isSuperAdmin()) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $opsIds = $user->getOperacoesIds();
            $operacoes = !empty($opsIds)
                ? Operacao::where('ativo', true)->whereIn('id', $opsIds)->orderBy('nome')->get()
                : collect([]);
        }

        $titulo = 'Cheques de Hoje';

        return view('cheques.index', compact('cheques', 'filtros', 'titulo', 'totais', 'operacoes'));
    }

    /**
     * Adicionar cheque a um empréstimo
     */
    public function store(Request $request, int $emprestimoId): RedirectResponse
    {
        $emprestimo = Emprestimo::findOrFail($emprestimoId);

        // Verificar se é tipo troca_cheque
        if (!$emprestimo->isTrocaCheque()) {
            return back()->with('error', 'Apenas empréstimos do tipo troca de cheque podem ter cheques cadastrados.');
        }

        // Verificar se empréstimo está finalizado
        if ($emprestimo->isFinalizado()) {
            return back()->with('error', 'Não é possível adicionar cheques a empréstimos finalizados.');
        }

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para adicionar cheques a este empréstimo.');
        }
        if ($user->hasAnyRole(['administrador', 'gestor']) && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'banco' => 'required|string|max:100',
            'agencia' => 'required|string|max:20',
            'conta' => 'required|string|max:20',
            'numero_cheque' => 'required|string|max:50',
            'data_vencimento' => 'required|date|after:today',
            'valor_cheque' => 'required|numeric|min:0.01',
            'taxa_juros' => 'nullable|numeric|min:0|max:100',
            'portador' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $cheque = $this->chequeService->criar($emprestimoId, $validated);

            return back()->with('success', 'Cheque cadastrado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao criar cheque: ' . $e->getMessage());
            return back()->with('error', 'Erro ao cadastrar cheque: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Atualizar cheque
     */
    public function update(Request $request, int $chequeId): RedirectResponse
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para editar este cheque.');
        }
        if ($user->hasAnyRole(['administrador', 'gestor']) && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'banco' => 'sometimes|required|string|max:100',
            'agencia' => 'sometimes|required|string|max:20',
            'conta' => 'sometimes|required|string|max:20',
            'numero_cheque' => 'sometimes|required|string|max:50',
            'data_vencimento' => 'sometimes|required|date|after:today',
            'valor_cheque' => 'sometimes|required|numeric|min:0.01',
            'taxa_juros' => 'nullable|numeric|min:0|max:100',
            'portador' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->chequeService->atualizar($chequeId, $validated);

            return back()->with('success', 'Cheque atualizado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao atualizar cheque: ' . $e->getMessage());
            return back()->with('error', 'Erro ao atualizar cheque: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Deletar cheque
     */
    public function destroy(int $chequeId): RedirectResponse
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para excluir este cheque.');
        }
        if ($user->hasAnyRole(['administrador', 'gestor']) && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        try {
            $this->chequeService->deletar($chequeId);

            return back()->with('success', 'Cheque excluído com sucesso!');
        } catch (\Exception $e) {
            \Log::error('Erro ao deletar cheque: ' . $e->getMessage());
            return back()->with('error', 'Erro ao excluir cheque: ' . $e->getMessage());
        }
    }

    /**
     * Marcar cheque como depositado
     */
    public function depositar(Request $request, int $chequeId)
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            return back()->with('error', 'Apenas administradores e gestores podem marcar cheques como depositados.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->chequeService->depositar($chequeId, $validated['observacoes'] ?? null);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Cheque marcado como depositado!']);
            }

            return back()->with('success', 'Cheque marcado como depositado!');
        } catch (\Exception $e) {
            \Log::error('Erro ao depositar cheque: ' . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Erro ao depositar cheque: ' . $e->getMessage()], 400);
            }

            return back()->with('error', 'Erro ao depositar cheque: ' . $e->getMessage());
        }
    }

    /**
     * Marcar cheque como compensado
     */
    public function compensar(Request $request, int $chequeId)
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            return back()->with('error', 'Apenas administradores e gestores podem marcar cheques como compensados.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->chequeService->compensar($chequeId, $validated['observacoes'] ?? null);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Cheque marcado como compensado!']);
            }

            return back()->with('success', 'Cheque marcado como compensado!');
        } catch (\Exception $e) {
            \Log::error('Erro ao compensar cheque: ' . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Erro ao compensar cheque: ' . $e->getMessage()], 400);
            }

            return back()->with('error', 'Erro ao compensar cheque: ' . $e->getMessage());
        }
    }

    /**
     * Marcar cheque como devolvido
     */
    public function devolver(Request $request, int $chequeId)
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            return back()->with('error', 'Apenas administradores e gestores podem marcar cheques como devolvidos.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'motivo_devolucao' => 'required|string|min:10|max:500',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->chequeService->devolver(
                $chequeId,
                $validated['motivo_devolucao'],
                $validated['observacoes'] ?? null
            );

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Cheque marcado como devolvido!']);
            }

            return back()->with('success', 'Cheque marcado como devolvido!');
        } catch (\Exception $e) {
            \Log::error('Erro ao devolver cheque: ' . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Erro ao devolver cheque: ' . $e->getMessage()], 400);
            }

            return back()->with('error', 'Erro ao devolver cheque: ' . $e->getMessage());
        }
    }

    /**
     * Exibir página de registrar pagamento do cheque devolvido
     */
    public function showPagar(int $chequeId): View|RedirectResponse
    {
        $cheque = EmprestimoCheque::with(['emprestimo.cliente', 'emprestimo.operacao'])->findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        if ($cheque->status !== 'devolvido') {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'Apenas cheques devolvidos podem ter o pagamento registrado.');
        }

        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'Apenas administradores e gestores podem registrar pagamento.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Acesso negado a esta operação.');
            }
        }

        return view('cheques.pagar', compact('cheque', 'emprestimo'));
    }

    /**
     * Registrar pagamento do cheque devolvido (qualquer método de pagamento)
     */
    public function pagarEmDinheiro(Request $request, int $chequeId)
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            return back()->with('error', 'Apenas administradores e gestores podem registrar pagamento em dinheiro.');
        }
        if (!$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'tipo_juros' => 'required|in:automatico,manual,fixo,nenhum',
            'taxa_juros_manual' => 'required_if:tipo_juros,manual|nullable|numeric|min:0|max:100',
            'valor_total_fixo' => 'required_if:tipo_juros,fixo|nullable|numeric|min:0.01',
            'metodo_pagamento' => 'required|in:dinheiro,pix,transferencia,cartao_debito,cartao_credito,boleto,outro',
            'data_pagamento' => 'required|date',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        if ($request->hasFile('comprovante')) {
            try {
                $validated['comprovante_path'] = $request->file('comprovante')->store('comprovantes/pagamento-cheque-devolvido', 'public');
            } catch (\Exception $e) {
                \Log::error('Erro ao fazer upload do comprovante: ' . $e->getMessage());
                return back()->with('error', 'Erro ao enviar comprovante. Tente novamente.')->withInput();
            }
        }

        try {
            $this->chequeService->pagarEmDinheiro($chequeId, $validated);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Pagamento registrado com sucesso!']);
            }

            return redirect()->route('emprestimos.show', $emprestimo->id)->with('success', 'Pagamento registrado com sucesso!');
        } catch (\Exception $e) {
            \Log::error('Erro ao registrar pagamento: ' . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Erro ao registrar pagamento: ' . $e->getMessage()], 400);
            }

            return back()->with('error', 'Erro ao registrar pagamento: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Substituir cheque devolvido por novo cheque
     */
    public function substituir(Request $request, int $chequeId)
    {
        $cheque = \App\Modules\Loans\Models\EmprestimoCheque::findOrFail($chequeId);
        $emprestimo = $cheque->emprestimo;

        // Verificar permissão
        $user = auth()->user();
        if (!$user->hasAnyRole(['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
            return back()->with('error', 'Você não tem permissão para substituir este cheque.');
        }
        if ($user->hasAnyRole(['administrador', 'gestor']) && !$user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || !in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Você não tem permissão para esta operação.');
            }
        }

        $validated = $request->validate([
            'banco' => 'required|string|max:100',
            'agencia' => 'required|string|max:20',
            'conta' => 'required|string|max:20',
            'numero_cheque' => 'required|string|max:50',
            'data_vencimento' => 'required|date|after:today',
            'valor_cheque' => 'required|numeric|min:0.01',
            'taxa_juros' => 'nullable|numeric|min:0|max:100',
            'portador' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $novoCheque = $this->chequeService->substituirPorNovoCheque($chequeId, $validated);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Cheque substituído com sucesso!', 'cheque' => $novoCheque]);
            }

            return back()->with('success', 'Cheque substituído com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Erro ao substituir cheque: ' . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Erro ao substituir cheque: ' . $e->getMessage()], 400);
            }

            return back()->with('error', 'Erro ao substituir cheque: ' . $e->getMessage());
        }
    }
}
