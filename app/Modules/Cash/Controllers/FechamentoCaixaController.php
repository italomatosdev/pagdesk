<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cash\Models\Settlement;
use App\Modules\Cash\Services\CashService;
use App\Modules\Cash\Services\SettlementService;
use App\Modules\Core\Models\Operacao;
use App\Support\FichaContatoLookup;
use App\Support\OperacaoPreferida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $operacoesIds = $user->getOperacoesIds();
        $operacoes = ! empty($operacoesIds)
            ? Operacao::where('ativo', true)->whereIn('id', $operacoesIds)->orderBy('nome')->get()
            : collect([]);

        if ($operacoes->isEmpty()) {
            abort(403, 'Você não tem acesso a nenhuma operação.');
        }

        $operacaoId = OperacaoPreferida::resolverParaFiltroGet($request, $operacoes->pluck('id')->all(), $user);
        if ($operacaoId === null) {
            $operacaoId = (int) $operacoes->first()->id;
        }
        if (empty($operacoesIds) || ! in_array($operacaoId, $operacoesIds, true)) {
            $operacaoId = (int) $operacoes->first()->id;
        }

        $operacaoSelecionada = $operacoes->firstWhere('id', $operacaoId);

        // Meu saldo na operação
        $meuSaldo = $this->cashService->calcularSaldo($user->id, $operacaoId);

        // Usuários com saldo (para gestor/admin na operação)
        $usuariosComSaldo = collect([]);
        if ($operacaoId && $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
            $usuariosComSaldo = $this->settlementService->listarUsuariosComSaldo($operacaoId);
        }

        // Filtros para listagem
        $consultorId = null;
        $usuariosFiltro = collect([]);
        if (! empty($user->getOperacoesIdsOndeTemPapel(['administrador', 'gestor']))) {
            $usuariosFiltro = User::query()
                ->whereHas('operacoes', function ($q) use ($operacaoId) {
                    $q->where('operacoes.id', $operacaoId);
                })
                ->orderBy('name')
                ->get(['id', 'name']);

            $consultorIdInput = $request->input('consultor_id');
            $consultorId = $consultorIdInput ? (int) $consultorIdInput : null;
            if ($consultorId && ! $usuariosFiltro->contains(fn (User $u) => (int) $u->id === $consultorId)) {
                $consultorId = null;
            }
        } else {
            $consultorId = $user->id;
        }
        $status = $request->input('status');
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        // Listar fechamentos
        $fechamentos = $this->settlementService->listar($consultorId, $operacaoId, $status, $user, $dataInicio, $dataFim);

        return view('caixa.fechamento.index', compact(
            'operacoes',
            'operacaoId',
            'operacaoSelecionada',
            'meuSaldo',
            'usuariosComSaldo',
            'usuariosFiltro',
            'fechamentos',
            'consultorId',
            'status',
            'dataInicio',
            'dataFim'
        ));
    }

    /**
     * Tela de conferência (extrato e totais) antes de confirmar o fechamento de caixa.
     */
    public function conferir(Request $request): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            abort(403, 'Super Admin não pode acessar Fechamento de Caixa.');
        }

        $validated = $request->validate([
            'usuario_id' => 'required|exists:users,id',
            'operacao_id' => 'required|exists:operacoes,id',
        ]);

        $usuarioId = (int) $validated['usuario_id'];
        $operacaoId = (int) $validated['operacao_id'];

        if (! $user->temAcessoOperacao($operacaoId)) {
            return redirect()->route('fechamento-caixa.index')
                ->with('error', 'Você não tem acesso a esta operação.');
        }

        if ($usuarioId !== $user->id) {
            if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
                return redirect()->route('fechamento-caixa.index')
                    ->with('error', 'Você não tem permissão para fechar o caixa de outro usuário.');
            }
        }

        $dados = $this->settlementService->prepararConferenciaFechamento($usuarioId, $operacaoId);

        $permiteFechar = round((float) $dados['saldoAtual'], 2) > 0;

        $usuarioAlvo = \App\Models\User::findOrFail($usuarioId);
        $operacao = Operacao::findOrFail($operacaoId);
        $movimentacoes = $dados['movimentacoes'];
        $saldoInicial = $dados['saldoInicial'];
        $totalEntradas = $dados['totalEntradas'];
        $totalSaidas = $dados['totalSaidas'];
        $saldoFinal = $dados['saldoFinal'];
        $saldoAtual = $dados['saldoAtual'];
        $dataInicioConf = $dados['dataInicio'];
        $dataFimConf = $dados['dataFim'];
        $quantidadeMovimentacoes = $movimentacoes->count();

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapFromCashLedgerEntries($movimentacoes);

        return view('caixa.fechamento.conferir', compact(
            'usuarioAlvo',
            'operacao',
            'movimentacoes',
            'saldoInicial',
            'totalEntradas',
            'totalSaidas',
            'saldoFinal',
            'saldoAtual',
            'quantidadeMovimentacoes',
            'dataInicioConf',
            'dataFimConf',
            'fichasContatoPorClienteOperacao',
            'permiteFechar'
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
        if ($settlement->consultor_id === $user->id) {
            // Dono pode ver
        } elseif ($user->temAlgumPapelNaOperacao($settlement->operacao_id, ['gestor', 'administrador'])) {
            // Gestor/admin na operação pode ver
        } else {
            abort(403, 'Acesso negado.');
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
                $settlement->data_fim->format('Y-m-d'),
            ])
            ->with(['operacao', 'pagamento.parcela.emprestimo.cliente'])
            ->orderBy('data_movimentacao', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $quantidadeMovimentacoes = $movimentacoes->count();

        $fichasContatoPorClienteOperacao = FichaContatoLookup::mapFromCashLedgerEntries($movimentacoes);

        return view('caixa.fechamento.show', compact(
            'settlement',
            'movimentacoes',
            'saldoInicial',
            'totalEntradas',
            'totalSaidas',
            'saldoFinal',
            'quantidadeMovimentacoes',
            'fichasContatoPorClienteOperacao'
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
            if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
                return back()->with('error', 'Você não tem permissão para fechar o caixa de outro usuário.');
            }
        }
        if (! $user->temAcessoOperacao($operacaoId)) {
            return back()->with('error', 'Você não tem acesso a esta operação.');
        }

        $saldoAtualFechar = $this->cashService->calcularSaldo($usuarioId, $operacaoId);
        if (round((float) $saldoAtualFechar, 2) <= 0) {
            return back()->with('error', 'Não é possível fechar com saldo zero ou negativo. Saldo atual: R$ '.number_format($saldoAtualFechar, 2, ',', '.'));
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao fechar caixa: '.$e->getMessage())->withInput();
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

            return back()->with('error', 'Erro: '.$e->getMessage());
        }
    }

    /**
     * Aprovar fechamento (gestor/admin) - para fechamentos em status pendente
     */
    public function aprovar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();

        $settlement = Settlement::findOrFail($id);
        if (! $user->temAlgumPapelNaOperacao($settlement->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem aprovar.');
        }

        try {
            $this->settlementService->aprovar($id, $user->id, $request->input('observacoes'));

            return redirect()->route('fechamento-caixa.show', $id)->with('success', 'Fechamento aprovado!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro: '.$e->getMessage());
        }
    }

    /**
     * Rejeitar fechamento (gestor/admin)
     */
    public function rejeitar(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $settlement = Settlement::findOrFail($id);
        if (! $user->temAlgumPapelNaOperacao($settlement->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem rejeitar.');
        }

        $validated = $request->validate([
            'motivo_rejeicao' => 'required|string|max:500',
        ]);

        try {
            $this->settlementService->rejeitar($id, $user->id, $validated['motivo_rejeicao']);

            return redirect()->route('fechamento-caixa.show', $id)->with('success', 'Fechamento rejeitado.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro: '.$e->getMessage());
        }
    }

    /**
     * Confirmar recebimento (gestor/admin)
     */
    public function confirmarRecebimento(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $settlement = Settlement::findOrFail($id);
        if (! $user->temAlgumPapelNaOperacao($settlement->operacao_id, ['gestor', 'administrador'])) {
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
            return back()->with('error', 'Erro: '.$e->getMessage());
        }
    }

    /**
     * Marcar como pago quando o consultor está bloqueado (gestor/admin).
     * O consultor bloqueado não pode enviar comprovante; o gestor encerra o ciclo marcando como pago.
     */
    public function marcarComoPagoConsultorBloqueado(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $settlement = Settlement::with('consultor')->findOrFail($id);
        if (! $user->temAlgumPapelNaOperacao($settlement->operacao_id, ['gestor', 'administrador'])) {
            abort(403, 'Apenas gestores e administradores podem marcar como pago.');
        }

        $validated = $request->validate([
            'observacoes' => 'nullable|string|max:500',
        ]);

        try {
            $this->settlementService->marcarComoPagoConsultorBloqueado($id, $user->id, $validated['observacoes'] ?? null);

            return redirect()->route('fechamento-caixa.show', $id)
                ->with('success', 'Marcado como pago. Movimentações de caixa geradas (consultor bloqueado).');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = $e->validator->errors()->first('settlement') ?: $e->getMessage();

            return back()->with('error', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Erro: '.$e->getMessage());
        }
    }
}
