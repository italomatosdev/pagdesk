<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Cash\Services\CashService;
use App\Modules\Cash\Services\SettlementService;
use App\Modules\Core\Models\Operacao;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FechamentoCaixaController extends Controller
{
    public function __construct(
        protected SettlementService $settlementService,
        protected CashService $cashService
    ) {
        $this->middleware('auth');
    }

    /**
     * Tela principal unificada de Fechamento de Caixa
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar Fechamento de Caixa.');
        }

        // Operações disponíveis
        if ($user->hasRole('administrador')) {
            $operacoes = Operacao::where('ativo', true)->orderBy('nome')->get();
        } else {
            $operacoesIds = $user->getOperacoesIds();
            $operacoes = !empty($operacoesIds)
                ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
                : collect([]);
        }

        if ($operacoes->isEmpty()) {
            abort(403, 'Você não tem acesso a nenhuma operação.');
        }

        // Operação selecionada
        $operacaoId = $request->input('operacao_id') ? (int) $request->input('operacao_id') : $operacoes->first()->id;
        
        // Validar acesso à operação
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            $operacaoId = $operacoes->first()->id;
        }

        $operacaoSelecionada = $operacoes->firstWhere('id', $operacaoId);

        // Meu saldo na operação
        $meuSaldo = $this->cashService->calcularSaldo($user->id, $operacaoId);

        // Usuários com saldo (para gestor/admin)
        $usuariosComSaldo = collect([]);
        if ($user->hasAnyRole(['gestor', 'administrador'])) {
            $usuariosComSaldo = $this->settlementService->listarUsuariosComSaldo($operacaoId);
        }

        // Filtros para listagem
        $consultorId = null;
        if ($user->hasAnyRole(['administrador', 'gestor'])) {
            $consultorIdInput = $request->input('consultor_id');
            $consultorId = $consultorIdInput ? (int) $consultorIdInput : null;
        } else {
            $consultorId = $user->id;
        }
        $status = $request->input('status');
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        // Listar fechamentos
        $fechamentos = $this->settlementService->listar($consultorId, $operacaoId, $status, $user, $dataInicio, $dataFim);

        // Consultor selecionado para exibição
        $consultorSelecionado = null;
        if ($consultorId) {
            $consultorSelecionado = \App\Models\User::find($consultorId);
        }

        return view('caixa.fechamento.index', compact(
            'operacoes',
            'operacaoId',
            'operacaoSelecionada',
            'meuSaldo',
            'usuariosComSaldo',
            'fechamentos',
            'consultorSelecionado',
            'status',
            'dataInicio',
            'dataFim'
        ));
    }

    /**
     * Exibir detalhes de um fechamento
     */
    public function show(int $id): View
    {
        $settlement = Settlement::with(['operacao', 'consultor', 'criador', 'conferidor', 'recebedor'])->findOrFail($id);
        $user = auth()->user();

        // Verificar permissões
        if (!$user->hasAnyRole(['administrador', 'gestor'])) {
            if ($settlement->consultor_id !== $user->id) {
                abort(403, 'Acesso negado.');
            }
        } else {
            if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($settlement->operacao_id)) {
                abort(403, 'Acesso negado.');
            }
        }

        // Calcular valores do período
        $saldoInicial = $this->cashService->calcularSaldoInicial(
            $settlement->consultor_id,
            $settlement->operacao_id,
            $settlement->data_inicio->format('Y-m-d')
        );

        $totalEntradas = $this->cashService->calcularTotalEntradas(
            $settlement->consultor_id,
            $settlement->operacao_id,
            $settlement->data_inicio->format('Y-m-d'),
            $settlement->data_fim->format('Y-m-d')
        );

        $totalSaidas = $this->cashService->calcularTotalSaidas(
            $settlement->consultor_id,
            $settlement->operacao_id,
            $settlement->data_inicio->format('Y-m-d'),
            $settlement->data_fim->format('Y-m-d')
        );

        $saldoFinal = $saldoInicial + $totalEntradas - $totalSaidas;

        // Movimentações do período
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

        return view('caixa.fechamento.show', compact(
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
     * Processar fechamento de caixa (próprio ou de outro usuário)
     */
    public function fechar(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'usuario_id' => 'required|exists:users,id',
            'operacao_id' => 'required|exists:operacoes,id',
            'observacoes' => 'nullable|string|max:500',
        ]);

        $usuarioId = (int) $validated['usuario_id'];
        $operacaoId = (int) $validated['operacao_id'];

        // Verificar permissões
        if ($usuarioId !== $user->id) {
            // Fechando caixa de outro usuário - precisa ser gestor/admin
            if (!$user->hasAnyRole(['gestor', 'administrador'])) {
                return back()->with('error', 'Você não tem permissão para fechar o caixa de outro usuário.');
            }
        }

        // Validar acesso à operação
        if (!$user->hasRole('administrador') && !$user->temAcessoOperacao($operacaoId)) {
            return back()->with('error', 'Você não tem acesso a esta operação.');
        }

        try {
            $settlement = $this->settlementService->fecharCaixa(
                $usuarioId,
                $operacaoId,
                $user->id,
                $validated['observacoes'] ?? null
            );

            $msg = $usuarioId === $user->id
                ? 'Seu caixa foi fechado! Anexe o comprovante de envio.'
                : 'Caixa fechado com sucesso! O usuário foi notificado.';

            return redirect()->route('fechamento-caixa.show', $settlement->id)->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao fechar caixa: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Anexar comprovante de envio
     */
    public function anexarComprovante(Request $request, int $id): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $settlement = Settlement::findOrFail($id);

        // Apenas o dono pode anexar comprovante
        if ($settlement->consultor_id !== $user->id) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
            }
            abort(403, 'Acesso negado.');
        }

        $request->validate([
            'comprovante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        try {
            $comprovantePath = $request->file('comprovante')->store('comprovantes/fechamentos', 'public');
            $this->settlementService->anexarComprovante($id, $comprovantePath);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Comprovante anexado com sucesso!']);
            }

            return redirect()->route('fechamento-caixa.show', $id)->with('success', 'Comprovante anexado!');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
            return back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Aprovar fechamento (gestor/admin) - para fechamentos em status pendente
     */
    public function aprovar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem aprovar.');
        }

        try {
            $this->settlementService->aprovar($id, $user->id, $request->input('observacoes'));
            return redirect()->route('fechamento-caixa.show', $id)->with('success', 'Fechamento aprovado!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Rejeitar fechamento (gestor/admin)
     */
    public function rejeitar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem rejeitar.');
        }

        $validated = $request->validate([
            'motivo_rejeicao' => 'required|string|max:500',
        ]);

        try {
            $this->settlementService->rejeitar($id, $user->id, $validated['motivo_rejeicao']);
            return redirect()->route('fechamento-caixa.show', $id)->with('success', 'Fechamento rejeitado.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Confirmar recebimento (gestor/admin)
     */
    public function confirmarRecebimento(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem confirmar recebimento.');
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
        ]);

        try {
            $this->settlementService->confirmarRecebimento($id, $user->id, $validated['observacoes'] ?? null);
            return redirect()->route('fechamento-caixa.show', $id)
                ->with('success', 'Recebimento confirmado! Movimentações geradas.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }
}
