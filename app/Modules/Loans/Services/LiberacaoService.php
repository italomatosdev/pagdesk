<?php

namespace App\Modules\Loans\Services;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Services\NotificacaoService;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\LiberacaoEmprestimo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiberacaoService
{
    use Auditable;

    protected CashService $cashService;

    public function __construct(CashService $cashService)
    {
        $this->cashService = $cashService;
    }

    /**
     * Criar registro de liberação pendente (quando empréstimo é aprovado)
     *
     * @param Emprestimo $emprestimo
     * @return LiberacaoEmprestimo
     */
    public function criarPendente(Emprestimo $emprestimo): LiberacaoEmprestimo
    {
        // Verificar se já existe liberação para este empréstimo
        $liberacaoExistente = LiberacaoEmprestimo::where('emprestimo_id', $emprestimo->id)->first();
        
        if ($liberacaoExistente) {
            return $liberacaoExistente;
        }

        // Usar Strategy Pattern para calcular valor líquido
        // Para troca_cheque: valor líquido = cheques - juros
        // Para outros tipos: valor líquido = valor_total
        $strategy = \App\Modules\Loans\Services\Strategies\LoanStrategyFactory::create($emprestimo);
        $valorLiberado = $strategy->calcularValorLiquido($emprestimo);

        $liberacao = LiberacaoEmprestimo::create([
            'emprestimo_id' => $emprestimo->id,
            'consultor_id' => $emprestimo->consultor_id,
            'valor_liberado' => $valorLiberado,
            'status' => 'aguardando',
            'empresa_id' => $emprestimo->empresa_id,
        ]);

        // Auditoria
        self::auditar('criar_liberacao_pendente', $liberacao, null, $liberacao->toArray());

        return $liberacao;
    }

    /**
     * Criar liberação para empréstimo retroativo (gestor criou direto ou aprovou retroativo do consultor).
     * O valor já foi dado no passado, então não gera movimentação de caixa; apenas o registro
     * com status pago_ao_cliente para permitir o registro de pagamento de parcelas.
     *
     * @param Emprestimo $emprestimo
     * @param int $gestorId
     * @return LiberacaoEmprestimo
     */
    public function criarParaRetroativo(Emprestimo $emprestimo, int $gestorId): LiberacaoEmprestimo
    {
        $liberacaoExistente = LiberacaoEmprestimo::where('emprestimo_id', $emprestimo->id)->first();
        if ($liberacaoExistente) {
            return $liberacaoExistente;
        }

        $strategy = \App\Modules\Loans\Services\Strategies\LoanStrategyFactory::create($emprestimo);
        $valorLiberado = $strategy->calcularValorLiquido($emprestimo);

        $liberacao = LiberacaoEmprestimo::create([
            'emprestimo_id' => $emprestimo->id,
            'consultor_id' => $emprestimo->consultor_id,
            'gestor_id' => $gestorId,
            'valor_liberado' => $valorLiberado,
            'status' => 'pago_ao_cliente',
            'liberado_em' => now(),
            'pago_ao_cliente_em' => now(),
            'empresa_id' => $emprestimo->empresa_id,
        ]);

        self::auditar('criar_liberacao_retroativa', $liberacao, null, $liberacao->toArray());

        return $liberacao;
    }

    /**
     * Liberar dinheiro para o consultor (Gestor)
     *
     * @param int $liberacaoId
     * @param int $gestorId
     * @param string|null $observacoes
     * @param string|null $comprovantePath
     * @return LiberacaoEmprestimo
     */
    public function liberar(int $liberacaoId, int $gestorId, ?string $observacoes = null, ?string $comprovantePath = null): LiberacaoEmprestimo
    {
        return DB::transaction(function () use ($liberacaoId, $gestorId, $observacoes, $comprovantePath) {
            $liberacao = LiberacaoEmprestimo::with(['emprestimo', 'consultor'])->findOrFail($liberacaoId);
            $emprestimo = $liberacao->emprestimo;

            $gestor = \App\Models\User::find($gestorId);
            if ($gestor && !$gestor->isSuperAdmin()) {
                $operacoesIds = $gestor->getOperacoesIds();
                if (empty($operacoesIds) || !in_array((int) $emprestimo->operacao_id, $operacoesIds, true)) {
                    throw ValidationException::withMessages([
                        'liberacao' => 'Você não tem permissão para liberar desta operação.',
                    ]);
                }
            }

            // VALIDAÇÃO 1: Empréstimo deve estar APROVADO
            if (!$emprestimo->isAprovado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível liberar dinheiro. O empréstimo precisa estar APROVADO. Status atual: ' . $emprestimo->status
                ]);
            }

            // VALIDAÇÃO 2: Liberação deve estar AGUARDANDO
            if ($liberacao->status !== 'aguardando') {
                $mensagem = match($liberacao->status) {
                    'liberado' => 'Este dinheiro já foi liberado.',
                    'pago_ao_cliente' => 'Este dinheiro já foi pago ao cliente.',
                    default => 'Apenas liberações aguardando podem ser liberadas. Status atual: ' . $liberacao->status
                };
                throw ValidationException::withMessages([
                    'liberacao' => $mensagem
                ]);
            }

            $oldStatus = $liberacao->status;

            // Atualizar liberação
            $liberacao->update([
                'status' => 'liberado',
                'gestor_id' => $gestorId,
                'liberado_em' => now(),
                'observacoes_liberacao' => $observacoes,
                'comprovante_liberacao' => $comprovantePath,
            ]);

            // Criar movimentação de caixa - SAÍDA no caixa do gestor
            // Nota: O gestor tem seu próprio caixa (consultor_id = gestor_id)
            // Se quiser usar o caixa da operação, usar consultor_id = NULL
            $this->cashService->registrarMovimentacao([
                'operacao_id' => $liberacao->emprestimo->operacao_id,
                'consultor_id' => $gestorId, // Gestor que está liberando (saída do caixa dele)
                'tipo' => 'saida',
                'valor' => $liberacao->valor_liberado,
                'data_movimentacao' => now(),
                'descricao' => "Liberação para consultor {$liberacao->consultor->name} - Empréstimo #{$liberacao->emprestimo->id}",
                'referencia_tipo' => 'liberacao_emprestimo',
                'referencia_id' => $liberacao->id,
            ]);

            // Criar movimentação de caixa - ENTRADA no caixa do consultor
            $this->cashService->registrarMovimentacao([
                'operacao_id' => $liberacao->emprestimo->operacao_id,
                'consultor_id' => $liberacao->consultor_id,
                'tipo' => 'entrada',
                'valor' => $liberacao->valor_liberado,
                'data_movimentacao' => now(),
                'descricao' => "Liberação de dinheiro recebida - Empréstimo #{$liberacao->emprestimo->id}",
                'referencia_tipo' => 'liberacao_emprestimo',
                'referencia_id' => $liberacao->id,
            ]);

            // Auditoria
            self::auditar(
                'liberar_dinheiro',
                $liberacao,
                ['status' => $oldStatus],
                ['status' => 'liberado', 'gestor_id' => $gestorId],
                $observacoes
            );

            // Notificações
            $notificacaoService = app(NotificacaoService::class);
            $cliente = $liberacao->emprestimo->cliente;
            
            // Notificar consultor sobre liberação disponível
            $notificacaoService->criar([
                'user_id' => $liberacao->consultor_id,
                'tipo' => 'liberacao_disponivel',
                'titulo' => 'Dinheiro Liberado',
                'mensagem' => "R$ " . number_format($liberacao->valor_liberado, 2, ',', '.') . " liberado para pagamento ao cliente {$cliente->nome}",
                'url' => route('liberacoes.minhas'),
                'dados' => ['liberacao_id' => $liberacao->id, 'emprestimo_id' => $liberacao->emprestimo_id],
            ]);

            return $liberacao->fresh();
        });
    }

    /**
     * Liberar dinheiro em lote (Gestor/Admin)
     * Um único comprovante para todos; cada empréstimo mantém seu registro de liberação.
     * A observação lista todos os empréstimos do lote.
     *
     * @param array $liberacaoIds
     * @param int $gestorId
     * @param string|null $observacoes
     * @param string|null $comprovantePath
     * @return array{LiberacaoEmprestimo}
     */
    public function liberarLote(array $liberacaoIds, int $gestorId, ?string $observacoes = null, ?string $comprovantePath = null): array
    {
        $liberacaoIds = array_unique(array_filter(array_map('intval', $liberacaoIds)));
        if (empty($liberacaoIds)) {
            throw ValidationException::withMessages(['liberacao' => 'Nenhuma liberação selecionada.']);
        }

        return DB::transaction(function () use ($liberacaoIds, $gestorId, $observacoes, $comprovantePath) {
            $liberacoes = LiberacaoEmprestimo::with(['emprestimo.cliente', 'consultor'])
                ->whereIn('id', $liberacaoIds)
                ->where('status', 'aguardando')
                ->orderBy('id')
                ->get();

            if ($liberacoes->isEmpty()) {
                throw ValidationException::withMessages([
                    'liberacao' => 'Nenhuma liberação aguardando encontrada ou já foram liberadas.'
                ]);
            }

            $emprestimoIdsStr = $liberacoes->pluck('emprestimo_id')->map(fn ($id) => '#' . $id)->implode(', ');
            $observacaoLote = trim(($observacoes ?? '') . "\n\nLiberação em lote - Empréstimos: " . $emprestimoIdsStr);

            $notificacaoService = app(NotificacaoService::class);
            $liberadas = [];

            foreach ($liberacoes as $liberacao) {
                $emprestimo = $liberacao->emprestimo;

                if (!$emprestimo->isAprovado()) {
                    throw ValidationException::withMessages([
                        'emprestimo' => "Empréstimo #{$emprestimo->id} não está aprovado. Status: {$emprestimo->status}"
                    ]);
                }

                $liberacao->update([
                    'status' => 'liberado',
                    'gestor_id' => $gestorId,
                    'liberado_em' => now(),
                    'observacoes_liberacao' => $observacaoLote,
                    'comprovante_liberacao' => $comprovantePath,
                ]);

                $dadosMovimentacao = [
                    'operacao_id' => $liberacao->emprestimo->operacao_id,
                    'tipo' => 'saida',
                    'valor' => $liberacao->valor_liberado,
                    'data_movimentacao' => now(),
                    'descricao' => "Liberação para consultor {$liberacao->consultor->name} - Empréstimo #{$liberacao->emprestimo->id}",
                    'referencia_tipo' => 'liberacao_emprestimo',
                    'referencia_id' => $liberacao->id,
                    'observacoes' => $observacaoLote,
                    'comprovante_path' => $comprovantePath,
                ];
                $this->cashService->registrarMovimentacao(array_merge($dadosMovimentacao, [
                    'consultor_id' => $gestorId,
                ]));

                $this->cashService->registrarMovimentacao(array_merge($dadosMovimentacao, [
                    'consultor_id' => $liberacao->consultor_id,
                    'tipo' => 'entrada',
                    'descricao' => "Liberação de dinheiro recebida - Empréstimo #{$liberacao->emprestimo->id}",
                ]));

                $cliente = $liberacao->emprestimo->cliente;
                $notificacaoService->criar([
                    'user_id' => $liberacao->consultor_id,
                    'tipo' => 'liberacao_disponivel',
                    'titulo' => 'Dinheiro Liberado',
                    'mensagem' => "R$ " . number_format($liberacao->valor_liberado, 2, ',', '.') . " liberado para pagamento ao cliente {$cliente->nome}",
                    'url' => route('liberacoes.minhas'),
                    'dados' => ['liberacao_id' => $liberacao->id, 'emprestimo_id' => $liberacao->emprestimo_id],
                ]);

                $liberadas[] = $liberacao->fresh();
            }

            return $liberadas;
        });
    }

    /**
     * Confirmar pagamento ao cliente (Consultor)
     *
     * @param int $liberacaoId
     * @param int $consultorId
     * @param string|null $observacoes
     * @param string|null $comprovantePath
     * @return LiberacaoEmprestimo
     */
    public function confirmarPagamentoCliente(int $liberacaoId, int $consultorId, ?string $observacoes = null, ?string $comprovantePath = null): LiberacaoEmprestimo
    {
        return DB::transaction(function () use ($liberacaoId, $consultorId, $observacoes, $comprovantePath) {
            $liberacao = LiberacaoEmprestimo::with('emprestimo')->findOrFail($liberacaoId);
            $emprestimo = $liberacao->emprestimo;

            // VALIDAÇÃO 1: Empréstimo deve estar APROVADO ou ATIVO
            if (!$emprestimo->isAprovado() && !$emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível confirmar pagamento. O empréstimo precisa estar APROVADO ou ATIVO. Status atual: ' . $emprestimo->status
                ]);
            }

            // VALIDAÇÃO 2: Consultor deve ser o dono da liberação
            if ($liberacao->consultor_id !== $consultorId) {
                throw ValidationException::withMessages([
                    'liberacao' => 'Você não tem permissão para confirmar esta liberação.'
                ]);
            }

            // VALIDAÇÃO 3: Liberação deve estar LIBERADA
            if ($liberacao->status !== 'liberado') {
                $mensagem = match($liberacao->status) {
                    'aguardando' => 'O dinheiro ainda não foi liberado pelo gestor.',
                    'pago_ao_cliente' => 'O pagamento ao cliente já foi confirmado.',
                    default => 'Apenas liberações já liberadas podem ser confirmadas como pagas ao cliente. Status atual: ' . $liberacao->status
                };
                throw ValidationException::withMessages([
                    'liberacao' => $mensagem
                ]);
            }

            $oldStatus = $liberacao->status;

            // Atualizar liberação
            $liberacao->update([
                'status' => 'pago_ao_cliente',
                'pago_ao_cliente_em' => now(),
                'observacoes_pagamento' => $observacoes,
                'comprovante_pagamento_cliente' => $comprovantePath,
            ]);

            // Criar movimentação de caixa (saída no caixa do consultor)
            $this->cashService->registrarMovimentacao([
                'operacao_id' => $liberacao->emprestimo->operacao_id,
                'consultor_id' => $liberacao->consultor_id,
                'tipo' => 'saida',
                'valor' => $liberacao->valor_liberado,
                'data_movimentacao' => now(),
                'descricao' => "Pagamento ao cliente - Empréstimo #{$liberacao->emprestimo->id}",
                'referencia_tipo' => 'pagamento_cliente',
                'referencia_id' => $liberacao->emprestimo->id,
            ]);

            // Atualizar status do empréstimo para ativo (se ainda não estiver)
            if ($liberacao->emprestimo->status !== 'ativo') {
                $liberacao->emprestimo->update(['status' => 'ativo']);
            }

            // Auditoria
            self::auditar(
                'confirmar_pagamento_cliente',
                $liberacao,
                ['status' => $oldStatus],
                ['status' => 'pago_ao_cliente'],
                $observacoes
            );

            // Notificações
            $notificacaoService = app(NotificacaoService::class);
            $emprestimo = $liberacao->emprestimo;
            $cliente = $emprestimo->cliente;
            $operacaoId = (int) $emprestimo->operacao_id;
            $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, [
                'tipo' => 'pagamento_registrado',
                'titulo' => 'Pagamento ao Cliente Confirmado',
                'mensagem' => "Consultor confirmou pagamento de R$ " . number_format($liberacao->valor_liberado, 2, ',', '.') . " ao cliente {$cliente->nome}",
                'url' => route('emprestimos.show', $liberacao->emprestimo_id),
                'dados' => ['liberacao_id' => $liberacao->id, 'emprestimo_id' => $liberacao->emprestimo_id],
            ]);

            return $liberacao->fresh();
        });
    }

    /**
     * Anexar comprovante de liberação depois (apenas se ainda não tiver).
     * Apenas para liberações já liberadas/pagas. Não permite editar se já existir comprovante.
     */
    public function anexarComprovanteLiberacao(int $liberacaoId, string $comprovantePath): LiberacaoEmprestimo
    {
        return DB::transaction(function () use ($liberacaoId, $comprovantePath) {
            $liberacao = LiberacaoEmprestimo::findOrFail($liberacaoId);

            if (!empty($liberacao->comprovante_liberacao)) {
                throw ValidationException::withMessages([
                    'comprovante' => 'Esta liberação já possui comprovante. Não é possível substituir.',
                ]);
            }

            if ($liberacao->status !== 'liberado' && $liberacao->status !== 'pago_ao_cliente') {
                throw ValidationException::withMessages([
                    'liberacao' => 'Só é possível anexar comprovante em liberações já liberadas.',
                ]);
            }

            $liberacao->update(['comprovante_liberacao' => $comprovantePath]);
            self::auditar('anexar_comprovante_liberacao', $liberacao, null, ['comprovante_liberacao' => $comprovantePath]);
            return $liberacao->fresh();
        });
    }

    /**
     * Anexar comprovante de pagamento ao cliente depois (apenas se ainda não tiver).
     * Apenas para liberações já com status pago_ao_cliente. Não permite editar se já existir comprovante.
     */
    public function anexarComprovantePagamentoCliente(int $liberacaoId, int $consultorId, string $comprovantePath): LiberacaoEmprestimo
    {
        return DB::transaction(function () use ($liberacaoId, $consultorId, $comprovantePath) {
            $liberacao = LiberacaoEmprestimo::findOrFail($liberacaoId);

            if ($liberacao->consultor_id !== $consultorId) {
                throw ValidationException::withMessages([
                    'liberacao' => 'Você não tem permissão para anexar comprovante nesta liberação.',
                ]);
            }

            if (!empty($liberacao->comprovante_pagamento_cliente)) {
                throw ValidationException::withMessages([
                    'comprovante' => 'Esta liberação já possui comprovante de pagamento. Não é possível substituir.',
                ]);
            }

            if ($liberacao->status !== 'pago_ao_cliente') {
                throw ValidationException::withMessages([
                    'liberacao' => 'Só é possível anexar comprovante de pagamento após confirmar o pagamento ao cliente.',
                ]);
            }

            $liberacao->update(['comprovante_pagamento_cliente' => $comprovantePath]);
            self::auditar('anexar_comprovante_pagamento_cliente', $liberacao, null, ['comprovante_pagamento_cliente' => $comprovantePath]);
            return $liberacao->fresh();
        });
    }

    /**
     * Listar liberações aguardando (para gestor)
     *
     * @param int|null $operacaoId
     * @param \App\Models\User|null $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarAguardando(?int $operacaoId = null, ?\App\Models\User $user = null)
    {
        $query = LiberacaoEmprestimo::with([
            'emprestimo.cliente',
            'emprestimo.operacao',
            'emprestimo.parcelas',
            'consultor'
        ])
        ->where('status', 'aguardando')
        ->orderBy('created_at', 'asc');

        // Aplicar filtro de operações do usuário (Super Admin vê todas; demais só das operações vinculadas)
        if ($user && !$user->isSuperAdmin()) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereHas('emprestimo', function ($q) use ($operacoesIds) {
                    $q->whereIn('operacao_id', $operacoesIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($operacaoId) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId) {
                $q->where('operacao_id', $operacaoId);
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Listar liberações recebidas pelo consultor
     *
     * @param int $consultorId
     * @param string|null $status
     * @param int|string|null $operacaoId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarPorConsultor(int $consultorId, ?string $status = null, $operacaoId = null)
    {
        $query = LiberacaoEmprestimo::with([
            'emprestimo.cliente',
            'emprestimo.operacao',
            'emprestimo.parcelas',
            'gestor'
        ])
        ->where('consultor_id', $consultorId)
        ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($operacaoId !== null && $operacaoId !== '') {
            $query->whereHas('emprestimo', fn ($q) => $q->where('operacao_id', $operacaoId));
        }

        return $query->get();
    }
}
