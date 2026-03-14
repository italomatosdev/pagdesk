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
        
        // Se for admin ou gestor, pode ver de outros consultores ou todas as movimentações
        if ($user->hasAnyRole(['administrador', 'gestor'])) {
            $consultorIdInput = $request->input('consultor_id');
            // Se não especificar consultor_id, pode ver todas (null)
            // Se especificar, converte para int ou mantém null se vazio
            $consultorId = $consultorIdInput ? (int) $consultorIdInput : null;
        }

        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        
        // Validar se o usuário tem acesso à operação selecionada
        if ($operacaoId && !$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null; // Resetar se não tiver acesso
        }
        
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
        $referenciaTipo = $request->input('referencia_tipo');

        // Aplicar filtro de operações antes de chamar o service
        $query = \App\Modules\Cash\Models\CashLedgerEntry::with(['operacao', 'consultor', 'pagamento.parcela.emprestimo']);
        
        // Aplicar filtro de operações do usuário (exceto administradores)
        if (!$user->hasRole('administrador')) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                // Se não tem operações vinculadas, retorna vazio
                $query->whereRaw('1 = 0');
            }
        }
        
        // Filtrar por consultor/gestor ou caixa da operação
        if ($user->hasRole('consultor')) {
            // Consultor sempre vê apenas suas próprias movimentações
            $query->where('consultor_id', $consultorId);
        } elseif ($user->hasAnyRole(['administrador', 'gestor'])) {
            // Gestor/Admin: se não selecionou consultor, mostra TODAS as movimentações
            if ($consultorId !== null) {
                // Consultor/gestor específico selecionado
                $query->where('consultor_id', $consultorId);
            }
            // Se $consultorId === null, não aplicar filtro de consultor (mostra todas as movimentações)
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

        // Calcular saldo
        if ($user->hasRole('consultor')) {
            // Consultor sempre vê seu próprio saldo
            $saldo = $this->cashService->calcularSaldo($user->id, $operacaoId ?? 0);
        } elseif ($user->hasAnyRole(['administrador', 'gestor'])) {
            if ($consultorId !== null) {
                // Admin/gestor com filtro de consultor/gestor específico
                $saldo = $this->cashService->calcularSaldo($consultorId, $operacaoId ?? 0);
            } else {
                // Admin/gestor sem filtro = mostrar saldo TOTAL (todas as movimentações)
                // Se for gestor e não tiver operação selecionada, calcular para todas as operações dele
                if (!$user->hasRole('administrador') && !$operacaoId) {
                    $operacoesIds = $user->getOperacoesIds();
                    $saldo = 0;
                    if (!empty($operacoesIds)) {
                        foreach ($operacoesIds as $opId) {
                            $saldo += $this->cashService->calcularSaldoTotal($opId);
                        }
                    }
                } else {
                    $saldo = $this->cashService->calcularSaldoTotal($operacaoId);
                }
            }
        }

        // Filtrar operações disponíveis para o usuário
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        // Calcular métricas do período filtrado
        // Se for gestor/admin sem consultor selecionado, usar métodos que consideram TODAS as movimentações
        if ($user->hasAnyRole(['administrador', 'gestor']) && $consultorId === null) {
            // Todas as movimentações (consultores + caixa da operação)
            // Se for gestor e não tiver operação selecionada, calcular apenas para as operações dele
            if (!$user->hasRole('administrador') && !$operacaoId) {
                $operacoesIds = $user->getOperacoesIds();
                $totalEntradas = 0;
                $totalSaidas = 0;
                $saldoInicial = 0;
                if (!empty($operacoesIds)) {
                    foreach ($operacoesIds as $opId) {
                        $totalEntradas += $this->cashService->calcularTotalEntradas(null, $opId, $dataInicio, $dataFim);
                        $totalSaidas += $this->cashService->calcularTotalSaidas(null, $opId, $dataInicio, $dataFim);
                        $saldoInicial += $this->cashService->calcularSaldoInicial(null, $opId, $dataInicio, false);
                    }
                }
            } else {
                $totalEntradas = $this->cashService->calcularTotalEntradas(null, $operacaoId, $dataInicio, $dataFim);
                $totalSaidas = $this->cashService->calcularTotalSaidas(null, $operacaoId, $dataInicio, $dataFim);
                $saldoInicial = $this->cashService->calcularSaldoInicial(null, $operacaoId, $dataInicio, false);
            }
        } else {
            // Consultor específico ou consultor logado
            $totalEntradas = $this->cashService->calcularTotalEntradas($consultorId, $operacaoId, $dataInicio, $dataFim);
            $totalSaidas = $this->cashService->calcularTotalSaidas($consultorId, $operacaoId, $dataInicio, $dataFim);
            $saldoInicial = $this->cashService->calcularSaldoInicial($consultorId, $operacaoId, $dataInicio);
        }
        $diferencaPeriodo = $totalEntradas - $totalSaidas;

        // Buscar dados do consultor selecionado (se houver) para pré-seleção no Select2
        $consultorSelecionado = null;
        if ($consultorId) {
            $consultorSelecionado = User::with('roles')->find($consultorId);
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
            'consultorSelecionado',
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
        
        // Verificar permissão: apenas gestor e administrador
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem criar movimentações manuais.');
        }
        
        // Filtrar operações disponíveis para o usuário
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        // Buscar consultores e gestores com suas operações (para filtrar por operação no select)
        $usuarios = collect();
        if ($user->hasRole('administrador')) {
            $usuarios = User::with(['operacoes', 'roles'])
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['consultor', 'gestor']);
                })
                ->orderBy('name')
                ->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $usuarios = User::with(['operacoes', 'roles'])
                    ->whereHas('roles', function ($q) {
                        $q->whereIn('name', ['consultor', 'gestor']);
                    })
                    ->whereHas('operacoes', function ($q) use ($operacoesIds) {
                        $q->whereIn('operacoes.id', $operacoesIds);
                    })
                    ->orderBy('name')
                    ->get();
            }
        }

        // Por operação: lista de usuários que pertencem a ela (para o select responsável)
        // Sempre filtra por operação: administrador é da empresa/operação, só vê usuários daquela operação
        $usuariosPorOperacao = [];
        foreach ($operacoes as $op) {
            $usuariosPorOperacao[$op->id] = $usuarios->filter(function ($u) use ($op) {
                $ids = $u->operacoes->pluck('id')->toArray();
                if ($u->operacao_id && !in_array($u->operacao_id, $ids)) {
                    $ids[] = $u->operacao_id;
                }
                return in_array($op->id, $ids);
            })->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'roles' => $u->roles->pluck('name')->map(fn ($r) => ucfirst($r))->implode(', '),
                ];
            })->values()->toArray();
        }

        $categoriasEntrada = CategoriaMovimentacao::where('tipo', 'entrada')->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']);
        $categoriasDespesa = CategoriaMovimentacao::where('tipo', 'despesa')->where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']);
        return view('caixa.movimentacao.create', compact('operacoes', 'usuarios', 'usuariosPorOperacao', 'categoriasEntrada', 'categoriasDespesa'));
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
        
        // Verificar permissão: apenas gestor e administrador
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
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

        // Validar se o usuário tem acesso à operação
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($validated['operacao_id'])) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }

        // Se consultor_id foi informado, validar o usuário
        if (!empty($validated['consultor_id'])) {
            $consultor = User::findOrFail($validated['consultor_id']);
            
            // Validar se é gestor ou consultor (não pode ser outro tipo)
            if (!$consultor->hasAnyRole(['consultor', 'gestor'])) {
                return back()->with('error', 'O usuário selecionado deve ser um consultor ou gestor.')->withInput();
            }

            // Validar se o consultor/gestor pertence à operação
            if (!$user->hasRole('administrador')) {
                $operacoesIds = $user->getOperacoesIds();
                if (!empty($operacoesIds)) {
                    $consultorOperacoes = $consultor->getOperacoesIds();
                    $temAcesso = !empty(array_intersect($operacoesIds, $consultorOperacoes));
                    if (!$temAcesso) {
                        return back()->with('error', 'O usuário selecionado não pertence às suas operações.')->withInput();
                    }
                }
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

        // Consultor só vê suas próprias movimentações
        if ($user->hasRole('consultor') && $movimentacao->consultor_id !== $user->id) {
            abort(403, 'Acesso negado a esta movimentação.');
        }

        // Gestor/Admin: deve ter acesso à operação da movimentação
        if ($user->hasAnyRole(['gestor', 'administrador']) && !$user->hasRole('administrador')) {
            if (!$user->temAcessoOperacao($movimentacao->operacao_id)) {
                abort(403, 'Acesso negado a esta movimentação.');
            }
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
