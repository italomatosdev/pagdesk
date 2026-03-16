<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Models\CategoriaMovimentacao;
use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Models\Operacao;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CashController extends Controller
{
    protected CashService $cashService;

    public function __construct(CashService $cashService)
    {
        $this->middleware('auth');
        $this->cashService = $cashService;
    }

    /**
     * Listar movimentações de caixa
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode acessar o Caixa
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar o Caixa.');
        }
        
        $consultorId = $user->id;
        $apenasCaixaOperacao = false;

        if (!empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            $consultorIdInput = $request->input('consultor_id');
            if ($consultorIdInput === 'operacao') {
                $apenasCaixaOperacao = true;
                $consultorId = null;
            } elseif ($consultorIdInput !== null && $consultorIdInput !== '') {
                $consultorId = (int) $consultorIdInput;
            } else {
                $consultorId = null;
            }
        }

        $operacoesIds = $user->getOperacoesIds();
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        if ($operacaoId !== null && (empty($operacoesIds) || !in_array($operacaoId, $operacoesIds, true))) {
            $operacaoId = null;
        }

        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
        $referenciaTipo = $request->input('referencia_tipo');

        $query = \App\Modules\Cash\Models\CashLedgerEntry::with(['operacao', 'consultor', 'pagamento.parcela.emprestimo']);
        if (!empty($operacoesIds)) {
            $query->whereIn('operacao_id', $operacoesIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            $query->where('consultor_id', $consultorId);
        } elseif ($apenasCaixaOperacao) {
            $query->whereNull('consultor_id');
        } elseif ($consultorId !== null) {
            $query->where('consultor_id', $consultorId);
        }

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        if ($dataInicio) {
            $query->where('data_movimentacao', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->where('data_movimentacao', '<=', $dataFim);
        }

        if ($referenciaTipo !== null && $referenciaTipo !== '') {
            if ($referenciaTipo === 'manual') {
                $query->whereNull('referencia_tipo');
            } elseif ($referenciaTipo === 'venda') {
                $query->whereIn('referencia_tipo', ['venda', \App\Modules\Core\Models\Venda::class]);
            } else {
                $query->where('referencia_tipo', $referenciaTipo);
            }
        }

        $movimentacoes = $query->orderBy('data_movimentacao', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            $saldo = $this->cashService->calcularSaldo($user->id, $operacaoId ?? 0);
        } elseif (!empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            if ($apenasCaixaOperacao) {
                if ($operacaoId) {
                    $saldo = $this->cashService->calcularSaldoOperacao($operacaoId);
                } else {
                    $saldo = 0;
                    foreach ($operacoesIds as $opId) {
                        $saldo += $this->cashService->calcularSaldoOperacao($opId);
                    }
                }
            } elseif ($consultorId !== null) {
                $saldo = $this->cashService->calcularSaldo($consultorId, $operacaoId ?? 0);
            } else {
                if (!$operacaoId && !empty($operacoesIds)) {
                    $saldo = 0;
                    foreach ($operacoesIds as $opId) {
                        $saldo += $this->cashService->calcularSaldoTotal($opId);
                    }
                } else {
                    $saldo = $this->cashService->calcularSaldoTotal($operacaoId);
                }
            }
        } else {
            $saldo = 0;
        }

        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        if (!empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador'])) && $consultorId === null && !$apenasCaixaOperacao) {
            if (!$operacaoId && !empty($operacoesIds)) {
                $totalEntradas = 0;
                $totalSaidas = 0;
                $saldoInicial = 0;
                foreach ($operacoesIds as $opId) {
                    $totalEntradas += $this->cashService->calcularTotalEntradas(null, $opId, $dataInicio, $dataFim);
                    $totalSaidas += $this->cashService->calcularTotalSaidas(null, $opId, $dataInicio, $dataFim);
                    $saldoInicial += $this->cashService->calcularSaldoInicial(null, $opId, $dataInicio, false);
                }
            } else {
                $totalEntradas = $this->cashService->calcularTotalEntradas(null, $operacaoId, $dataInicio, $dataFim);
                $totalSaidas = $this->cashService->calcularTotalSaidas(null, $operacaoId, $dataInicio, $dataFim);
                $saldoInicial = $this->cashService->calcularSaldoInicial(null, $operacaoId, $dataInicio, false);
            }
        } elseif ($apenasCaixaOperacao) {
            if (!$operacaoId && !empty($operacoesIds)) {
                $totalEntradas = 0;
                $totalSaidas = 0;
                $saldoInicial = 0;
                foreach ($operacoesIds as $opId) {
                    $totalEntradas += $this->cashService->calcularTotalEntradas(null, $opId, $dataInicio, $dataFim, true);
                    $totalSaidas += $this->cashService->calcularTotalSaidas(null, $opId, $dataInicio, $dataFim, true);
                    $saldoInicial += $this->cashService->calcularSaldoInicial(null, $opId, $dataInicio, true);
                }
            } else {
                $totalEntradas = $this->cashService->calcularTotalEntradas(null, $operacaoId, $dataInicio, $dataFim, true);
                $totalSaidas = $this->cashService->calcularTotalSaidas(null, $operacaoId, $dataInicio, $dataFim, true);
                $saldoInicial = $this->cashService->calcularSaldoInicial(null, $operacaoId, $dataInicio, true);
            }
        } else {
            // Consultor específico ou consultor logado
            $totalEntradas = $this->cashService->calcularTotalEntradas($consultorId, $operacaoId, $dataInicio, $dataFim);
            $totalSaidas = $this->cashService->calcularTotalSaidas($consultorId, $operacaoId, $dataInicio, $dataFim);
            $saldoInicial = $this->cashService->calcularSaldoInicial($consultorId, $operacaoId, $dataInicio);
        }
        $diferencaPeriodo = $totalEntradas - $totalSaidas;

        // Valor do filtro Consultor/Caixa para o select: "", "operacao" ou id do usuário
        $consultorIdVal = $apenasCaixaOperacao ? 'operacao' : ($consultorId !== null ? (string) $consultorId : '');
        $consultorSelecionado = $consultorId ? User::find($consultorId) : null;

        // Usuários por operação (consultor, gestor, administrador) para o select
        $usuariosPorOperacao = [];
        if (!empty($operacoesIds)) {
            $usuarios = User::with('operacoes')
                ->whereHas('operacoes', function ($q) use ($operacoesIds) {
                    $q->whereIn('operacoes.id', $operacoesIds)
                        ->whereIn('operacao_user.role', ['consultor', 'gestor', 'administrador']);
                })
                ->orderBy('name')
                ->get();
            foreach ($operacoes as $op) {
                $usuariosPorOperacao[$op->id] = $usuarios->filter(fn ($u) => $u->operacoes->contains('id', $op->id))
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values()
                    ->all();
            }
        }

        // Carregar liberações referenciadas para exibir comprovante na coluna (movimentações automáticas sem comprovante próprio)
        $liberacoesById = collect();
        $liberacoesPorEmprestimoId = collect();
        if ($movimentacoes->isNotEmpty()) {
            $liberacaoIds = $movimentacoes->where('referencia_tipo', 'liberacao_emprestimo')->pluck('referencia_id')->unique()->filter()->values();
            if ($liberacaoIds->isNotEmpty()) {
                $liberacoesById = LiberacaoEmprestimo::whereIn('id', $liberacaoIds)->get()->keyBy('id');
            }
            $emprestimoIds = $movimentacoes->where('referencia_tipo', 'pagamento_cliente')->pluck('referencia_id')->unique()->filter()->values();
            if ($emprestimoIds->isNotEmpty()) {
                $liberacoesPorEmprestimoId = LiberacaoEmprestimo::whereIn('emprestimo_id', $emprestimoIds)
                    ->where('status', 'pago_ao_cliente')
                    ->get()
                    ->keyBy('emprestimo_id');
            }
        }

        return view('caixa.index', compact(
            'movimentacoes',
            'liberacoesById',
            'liberacoesPorEmprestimoId',
            'saldo',
            'operacoes',
            'operacaoId',
            'consultorId',
            'consultorIdVal',
            'consultorSelecionado',
            'usuariosPorOperacao',
            'totalEntradas',
            'totalSaidas',
            'saldoInicial',
            'diferencaPeriodo',
            'referenciaTipo'
        ));
    }

    /**
     * Mostrar formulário de criação de movimentação manual
     */
    public function create(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode criar movimentações
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar movimentações de caixa.');
        }
        
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem criar movimentações manuais.');
        }

        $operacoesIds = $user->getOperacoesIds();
        $operacoes = !empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        $usuarios = collect();
        if (!empty($operacoesIds)) {
            $usuarios = User::with(['operacoes'])
                ->whereHas('operacoes', function ($q) use ($operacoesIds) {
                    $q->whereIn('operacoes.id', $operacoesIds)
                        ->whereIn('operacao_user.role', ['consultor', 'gestor']);
                })
                ->orderBy('name')
                ->get();
        }

        // Por operação: lista de usuários que pertencem a ela (para o select responsável)
        // Sempre filtra por operação: administrador é da empresa/operação, só vê usuários daquela operação
        $usuariosPorOperacao = [];
        foreach ($operacoes as $op) {
            $usuariosPorOperacao[$op->id] = $usuarios->filter(function ($u) use ($op) {
                return in_array($op->id, $u->operacoes->pluck('id')->toArray(), true);
            })->map(function ($u) use ($op) {
                $pivot = $u->operacoes->where('id', $op->id)->first();
                $role = $pivot && $pivot->pivot ? ($pivot->pivot->role ?? '') : '';
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'roles' => $role ? ucfirst($role) : '',
                ];
            })->values()->toArray();
        }

        $categoriasPorOperacao = [];
        foreach ($operacoesIds as $opId) {
            $categorias = CategoriaMovimentacao::where('ativo', true)
                ->where(function ($q) use ($opId) {
                    $q->where('operacao_id', $opId)->orWhereNull('operacao_id');
                })
                ->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'tipo']);
            $categoriasPorOperacao[$opId] = [
                'entrada' => $categorias->where('tipo', 'entrada')->values()->toArray(),
                'despesa' => $categorias->where('tipo', 'despesa')->values()->toArray(),
            ];
        }
        return view('caixa.movimentacao.create', compact('operacoes', 'usuarios', 'usuariosPorOperacao', 'categoriasPorOperacao'));
    }

    /**
     * Criar movimentação manual
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        // Super Admin não pode criar movimentações
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar movimentações de caixa.');
        }
        
        if (empty($user->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']))) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem criar movimentações manuais.');
        }

        // Normalizar valor antes da validação
        // Se vier em BRL (ex: 1.550,50 ou 155,50): remove milhar (.), troca , por .
        // Se vier já numérico (ex: 155.50 do frontend): usa como está
        $valorInput = $request->input('valor');
        if (is_string($valorInput)) {
            $normalizado = preg_replace('/\s|R\$\s?/', '', $valorInput);
            if (str_contains($normalizado, ',')) {
                // Formato BRL: 1.550,50 ou 155,50
                $normalizado = str_replace('.', '', $normalizado);
                $normalizado = str_replace(',', '.', $normalizado);
            }
            if (preg_match('/^-?\d*\.?\d*$/', $normalizado)) {
                $request->merge(['valor' => $normalizado]);
            }
        }

        $validated = $request->validate([
            'tipo' => 'required|in:entrada,saida',
            'operacao_id' => 'required|exists:operacoes,id',
            'consultor_id' => 'nullable|exists:users,id', // Agora pode ser NULL (caixa da operação)
            'categoria_id' => 'nullable|exists:categoria_movimentacao,id',
            'valor' => 'required|numeric|min:0.01',
            'data_movimentacao' => 'required|date|before_or_equal:today',
            'descricao' => 'required|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $opsIds = $user->getOperacoesIds();
        if (empty($opsIds) || !in_array((int) $validated['operacao_id'], $opsIds, true)) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }

        if (!empty($validated['consultor_id'])) {
            $consultor = User::findOrFail($validated['consultor_id']);
            if (!$consultor->temAlgumPapelNaOperacao((int) $validated['operacao_id'], ['consultor', 'gestor'])) {
                return back()->with('error', 'O usuário selecionado deve ser um consultor ou gestor nesta operação.')->withInput();
            }
            $consultorOperacoes = $consultor->getOperacoesIds();
            if (empty(array_intersect($opsIds, $consultorOperacoes))) {
                return back()->with('error', 'O usuário selecionado não pertence às suas operações.')->withInput();
            }
        } else {
            // Se consultor_id é NULL, é movimentação do caixa da operação
            // Exigir descrição mais detalhada para auditoria
            if (strlen($validated['descricao']) < 20) {
                return back()->with('error', 'Para movimentações do caixa da operação, a descrição deve ter pelo menos 20 caracteres.')->withInput();
            }
        }

        try {
            // Upload de comprovante (se houver)
            $comprovantePath = null;
            if ($request->hasFile('comprovante')) {
                $file = $request->file('comprovante');
                $comprovantePath = $file->store('comprovantes/movimentacoes', 'public');
            }

            // Criar movimentação manual
            $dadosMovimentacao = [
                'operacao_id' => $validated['operacao_id'],
                'consultor_id' => !empty($validated['consultor_id']) ? $validated['consultor_id'] : null, // Pode ser NULL (caixa da operação)
                'tipo' => $validated['tipo'],
                'categoria_id' => !empty($validated['categoria_id']) ? $validated['categoria_id'] : null,
                'origem' => 'manual',
                'valor' => $validated['valor'],
                'descricao' => $validated['descricao'],
                'observacoes' => $validated['observacoes'] ?? null,
                'data_movimentacao' => $validated['data_movimentacao'],
            ];

            if ($comprovantePath) {
                $dadosMovimentacao['comprovante_path'] = $comprovantePath;
            }

            $movimentacao = $this->cashService->registrarMovimentacao($dadosMovimentacao);

            return redirect()->route('caixa.index')
                ->with('success', 'Movimentação criada com sucesso!');
        } catch (\Exception $e) {
            \Log::error('Erro ao criar movimentação manual: ' . $e->getMessage());
            return back()->with('error', 'Erro ao criar movimentação: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Exibir detalhes de uma movimentação
     */
    public function show(int $id): View
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar o Caixa.');
        }

        $movimentacao = CashLedgerEntry::with(['operacao', 'consultor', 'categoria', 'pagamento.parcela.emprestimo'])
            ->findOrFail($id);

        if (!$user->temAcessoOperacao($movimentacao->operacao_id)) {
            abort(403, 'Acesso negado a esta movimentação.');
        }
        if ($movimentacao->consultor_id !== $user->id && !$user->temAlgumPapelNaOperacao($movimentacao->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Acesso negado a esta movimentação.');
        }

        // Comprovante da referência (liberação) quando a movimentação não tem comprovante próprio
        $comprovanteReferenciaUrl = null;
        $comprovanteReferenciaLabel = null;
        if (!$movimentacao->comprovante_path && $movimentacao->referencia_tipo && $movimentacao->referencia_id) {
            if ($movimentacao->referencia_tipo === 'liberacao_emprestimo') {
                $lib = LiberacaoEmprestimo::find($movimentacao->referencia_id);
                if ($lib && $lib->comprovante_liberacao) {
                    $comprovanteReferenciaUrl = asset('storage/' . $lib->comprovante_liberacao);
                    $comprovanteReferenciaLabel = 'Comprovante da liberação';
                }
            } elseif ($movimentacao->referencia_tipo === 'pagamento_cliente') {
                $lib = LiberacaoEmprestimo::where('emprestimo_id', $movimentacao->referencia_id)
                    ->where('status', 'pago_ao_cliente')
                    ->first();
                if ($lib && $lib->comprovante_pagamento_cliente) {
                    $comprovanteReferenciaUrl = asset('storage/' . $lib->comprovante_pagamento_cliente);
                    $comprovanteReferenciaLabel = 'Comprovante pagamento ao cliente';
                }
            }
        }

        return view('caixa.movimentacao.show', compact('movimentacao', 'comprovanteReferenciaUrl', 'comprovanteReferenciaLabel'));
    }
}
