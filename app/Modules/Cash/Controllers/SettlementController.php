<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Cash\Services\SettlementService;
use App\Modules\Core\Models\Operacao;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SettlementController extends Controller
{
    protected SettlementService $settlementService;

    public function __construct(SettlementService $settlementService)
    {
        $this->middleware('auth');
        $this->settlementService = $settlementService;
    }

    /**
     * Listar prestações de contas
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        
        // Super Admin não pode acessar Prestação de Contas
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar Prestação de Contas.');
        }
        
        $consultorId = $user->id;
        
        // Se for admin ou gestor, pode ver de outros consultores ou todos
        if ($user->hasAnyRole(['administrador', 'gestor'])) {
            $consultorIdInput = $request->input('consultor_id');
            // Se não especificar ou for vazio, mostra todos (null)
            $consultorId = $consultorIdInput ? (int) $consultorIdInput : null;
        }
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;
        $status = $request->input('status');
        
        // Validar se o usuário tem acesso à operação selecionada
        if ($operacaoId && !$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = null; // Resetar se não tiver acesso
        }
        
        $settlements = $this->settlementService->listar($consultorId, $operacaoId, $status, $user);
        
        // Filtrar operações disponíveis para o usuário
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->get()
                : collect([]);
        }

        // Buscar dados do consultor selecionado (se houver) para pré-seleção no Select2
        $consultorSelecionado = null;
        if ($consultorId && $consultorId != auth()->id()) {
            $consultorSelecionado = \App\Models\User::with('roles')->find($consultorId);
        }

        return view('prestacoes.index', compact('settlements', 'operacoes', 'operacaoId', 'consultorId', 'consultorSelecionado', 'status'));
    }

    /**
     * Exibir detalhes da prestação de contas
     */
    public function show(int $id): View
    {
        $settlement = Settlement::with(['operacao', 'consultor', 'criador', 'conferidor', 'recebedor'])->findOrFail($id);
        $user = auth()->user();

        // Verificar permissões
        if (!$user->hasRole('administrador') && !$user->hasRole('gestor')) {
            // Se for consultor, só pode ver as próprias prestações
            if ($settlement->consultor_id !== $user->id) {
                abort(403, 'Acesso negado.');
            }
        } else {
            // Gestor/Admin só pode ver prestações das operações que tem acesso
            if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($settlement->operacao_id)) {
                abort(403, 'Acesso negado.');
            }
        }

        // Instanciar CashService para cálculos
        $cashService = app(\App\Modules\Cash\Services\CashService::class);

        // Calcular saldo inicial (antes do período)
        $saldoInicial = $cashService->calcularSaldoInicial(
            $settlement->consultor_id,
            $settlement->operacao_id,
            $settlement->data_inicio->format('Y-m-d')
        );

        // Calcular total de entradas no período
        $totalEntradas = $cashService->calcularTotalEntradas(
            $settlement->consultor_id,
            $settlement->operacao_id,
            $settlement->data_inicio->format('Y-m-d'),
            $settlement->data_fim->format('Y-m-d')
        );

        // Calcular total de saídas no período
        $totalSaidas = $cashService->calcularTotalSaidas(
            $settlement->consultor_id,
            $settlement->operacao_id,
            $settlement->data_inicio->format('Y-m-d'),
            $settlement->data_fim->format('Y-m-d')
        );

        // Saldo final = saldo inicial + entradas - saídas
        $saldoFinal = $saldoInicial + $totalEntradas - $totalSaidas;

        // Buscar TODAS as movimentações do consultor no período (entradas e saídas)
        $movimentacoes = \App\Modules\Cash\Models\CashLedgerEntry::where('consultor_id', $settlement->consultor_id)
            ->where('operacao_id', $settlement->operacao_id)
            ->whereBetween('data_movimentacao', [
                $settlement->data_inicio->format('Y-m-d'),
                $settlement->data_fim->format('Y-m-d')
            ])
            ->with(['operacao', 'pagamento.parcela.emprestimo.cliente'])
            ->orderBy('data_movimentacao', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $quantidadeMovimentacoes = $movimentacoes->count();

        return view('prestacoes.show', compact(
            'settlement',
            'movimentacoes',
            'saldoInicial',
            'totalEntradas',
            'totalSaidas',
            'saldoFinal',
            'quantidadeMovimentacoes'
        ));
    }

    /**
     * Mostrar formulário de criação
     */
    public function create(): View
    {
        $user = auth()->user();
        
        // Super Admin não pode criar Prestação de Contas
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar Prestação de Contas.');
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
        
        return view('prestacoes.create', compact('operacoes'));
    }

    /**
     * Mostrar tela de conferência antes de criar prestação
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $user = auth()->user();
        
        // Super Admin não pode criar Prestação de Contas
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar Prestação de Contas.');
        }
        
        $validated = $request->validate([
            'operacao_id' => 'required|exists:operacoes,id',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'observacoes' => 'nullable|string',
        ]);

        $user = auth()->user();
        $consultorId = $user->id;

        // Validar se o usuário tem acesso à operação selecionada
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($validated['operacao_id'])) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }

        // Buscar operação
        $operacao = Operacao::findOrFail($validated['operacao_id']);

        // Instanciar CashService para cálculos
        $cashService = app(\App\Modules\Cash\Services\CashService::class);

        // Calcular saldo inicial (antes do período)
        $saldoInicial = $cashService->calcularSaldoInicial(
            $consultorId,
            $validated['operacao_id'],
            $validated['data_inicio']
        );

        // Calcular total de entradas no período
        $totalEntradas = $cashService->calcularTotalEntradas(
            $consultorId,
            $validated['operacao_id'],
            $validated['data_inicio'],
            $validated['data_fim']
        );

        // Calcular total de saídas no período
        $totalSaidas = $cashService->calcularTotalSaidas(
            $consultorId,
            $validated['operacao_id'],
            $validated['data_inicio'],
            $validated['data_fim']
        );

        // Saldo final = saldo inicial + entradas - saídas
        $saldoFinal = $saldoInicial + $totalEntradas - $totalSaidas;

        // Buscar TODAS as movimentações do consultor no período (entradas e saídas)
        $movimentacoes = \App\Modules\Cash\Models\CashLedgerEntry::where('consultor_id', $consultorId)
            ->where('operacao_id', $validated['operacao_id'])
            ->whereBetween('data_movimentacao', [
                $validated['data_inicio'],
                $validated['data_fim']
            ])
            ->with(['operacao', 'pagamento.parcela.emprestimo.cliente'])
            ->orderBy('data_movimentacao', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $quantidadeMovimentacoes = $movimentacoes->count();

        return view('prestacoes.preview', compact(
            'operacao',
            'movimentacoes',
            'saldoInicial',
            'totalEntradas',
            'totalSaidas',
            'saldoFinal',
            'quantidadeMovimentacoes',
            'validated'
        ));
    }

    /**
     * Criar prestação de contas (após conferência)
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        // Super Admin não pode criar Prestação de Contas
        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode criar Prestação de Contas.');
        }
        
        $validated = $request->validate([
            'operacao_id' => 'required|exists:operacoes,id',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'observacoes' => 'nullable|string',
        ]);

        $validated['consultor_id'] = $user->id;

        // Validar se o usuário tem acesso à operação selecionada
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($validated['operacao_id'])) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }

        try {
            $settlement = $this->settlementService->criar($validated);
            return redirect()->route('prestacoes.index')
                ->with('success', 'Prestação de contas criada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar prestação de contas: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Aprovar prestação de contas (Gestor ou Administrador)
     */
    public function aprovar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        
        // Apenas gestores e administradores podem aprovar
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem aprovar prestações de contas.');
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
        ]);

        try {
            $this->settlementService->aprovar($id, $user->id, $validated['observacoes'] ?? null);
            return redirect()->route('prestacoes.show', $id)
                ->with('success', 'Prestação de contas aprovada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao aprovar: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar prestação de contas (Gestor ou Administrador)
     */
    public function rejeitar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        
        // Apenas gestores e administradores podem rejeitar
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem rejeitar prestações de contas.');
        }

        $validated = $request->validate([
            'motivo_rejeicao' => 'required|string|max:500',
        ]);

        try {
            $this->settlementService->rejeitar($id, $user->id, $validated['motivo_rejeicao']);
            return redirect()->route('prestacoes.show', $id)
                ->with('success', 'Prestação de contas rejeitada.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao rejeitar: ' . $e->getMessage());
        }
    }

    /**
     * Consultor anexa comprovante de envio
     */
    public function anexarComprovante(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $settlement = Settlement::findOrFail($id);

        // Apenas o consultor dono da prestação pode anexar comprovante
        if ($settlement->consultor_id !== $user->id) {
            abort(403, 'Acesso negado. Você só pode anexar comprovante nas suas próprias prestações.');
        }

        $validated = $request->validate([
            'comprovante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        try {
            // Upload do comprovante
            $file = $request->file('comprovante');
            $comprovantePath = $file->store('comprovantes/prestacoes', 'public');

            // Anexar comprovante (não gera movimentações ainda)
            $this->settlementService->anexarComprovante($id, $comprovantePath);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Comprovante anexado com sucesso! Aguardando confirmação de recebimento do gestor.'
                ]);
            }
            
            return redirect()->route('prestacoes.show', $id)
                ->with('success', 'Comprovante anexado com sucesso! Aguardando confirmação de recebimento do gestor.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao anexar comprovante: ' . $e->getMessage());
        }
    }

    /**
     * Gestor confirma recebimento do dinheiro
     * GERA as movimentações de caixa automaticamente
     */
    public function confirmarRecebimento(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        
        // Apenas gestores e administradores podem confirmar recebimento
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem confirmar recebimento.');
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
        ]);

        try {
            // Confirmar recebimento (gera movimentações automaticamente)
            $this->settlementService->confirmarRecebimento($id, $user->id, $validated['observacoes'] ?? null);
            
            return redirect()->route('prestacoes.show', $id)
                ->with('success', 'Recebimento confirmado! Movimentações de caixa geradas automaticamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao confirmar recebimento: ' . $e->getMessage());
        }
    }

    /**
     * Tela de fechamento de caixa (Gestor/Admin)
     * Lista usuários com saldo positivo para fechar
     */
    public function fechamentoCaixa(Request $request): View
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem fechar caixa.');
        }

        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : null;

        // Filtrar operações disponíveis para o usuário
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds) 
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
                : collect([]);
        }

        // Se não selecionou operação, usa a primeira disponível
        if (!$operacaoId && $operacoes->isNotEmpty()) {
            $operacaoId = $operacoes->first()->id;
        }

        // Validar se o usuário tem acesso à operação selecionada
        if ($operacaoId && !$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = $operacoes->first()?->id;
        }

        $usuariosComSaldo = collect([]);
        if ($operacaoId) {
            $usuariosComSaldo = $this->settlementService->listarUsuariosComSaldo($operacaoId);
        }

        return view('prestacoes.fechamento-caixa', compact('operacoes', 'operacaoId', 'usuariosComSaldo'));
    }

    /**
     * Processar fechamento de caixa de um usuário (Gestor/Admin)
     */
    public function fecharCaixa(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Acesso negado. Apenas gestores e administradores podem fechar caixa.');
        }

        $validated = $request->validate([
            'usuario_id' => 'required|exists:users,id',
            'operacao_id' => 'required|exists:operacoes,id',
            'observacoes' => 'nullable|string|max:500',
        ]);

        // Validar se o usuário tem acesso à operação
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($validated['operacao_id'])) {
            return back()->with('error', 'Você não tem acesso a esta operação.')->withInput();
        }

        try {
            $settlement = $this->settlementService->fecharCaixa(
                $validated['usuario_id'],
                $validated['operacao_id'],
                $user->id,
                $validated['observacoes'] ?? null
            );
            
            return redirect()->route('prestacoes.show', $settlement->id)
                ->with('success', 'Caixa fechado com sucesso! O usuário foi notificado para enviar o comprovante.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao fechar caixa: ' . $e->getMessage())->withInput();
        }
    }
}
