<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\Settlement;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Core\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    use Auditable;

    /**
     * Criar prestação de contas (solicitação pelo próprio usuário)
     * Calcula o saldo líquido do consultor (saldo inicial + entradas - saídas)
     *
     * @param array $dados
     * @return Settlement
     */
    public function criar(array $dados): Settlement
    {
        return DB::transaction(function () use ($dados) {
            $cashService = app(\App\Modules\Cash\Services\CashService::class);

            // Calcular saldo inicial (antes do período)
            $saldoInicial = $cashService->calcularSaldoInicial(
                $dados['consultor_id'],
                $dados['operacao_id'],
                $dados['data_inicio']
            );

            // Calcular total de entradas no período
            $totalEntradas = $cashService->calcularTotalEntradas(
                $dados['consultor_id'],
                $dados['operacao_id'],
                $dados['data_inicio'],
                $dados['data_fim']
            );

            // Calcular total de saídas no período
            $totalSaidas = $cashService->calcularTotalSaidas(
                $dados['consultor_id'],
                $dados['operacao_id'],
                $dados['data_inicio'],
                $dados['data_fim']
            );

            // Saldo final = saldo inicial + entradas - saídas
            $valorTotal = $saldoInicial + $totalEntradas - $totalSaidas;

            // Obter empresa_id da operação
            $operacao = \App\Modules\Core\Models\Operacao::find($dados['operacao_id']);
            $empresaId = $operacao->empresa_id ?? (auth()->check() && !auth()->user()->isSuperAdmin() ? auth()->user()->empresa_id : null);

            $settlement = Settlement::create([
                'operacao_id' => $dados['operacao_id'],
                'consultor_id' => $dados['consultor_id'],
                'criado_por' => $dados['consultor_id'], // Próprio usuário criou
                'data_inicio' => $dados['data_inicio'],
                'data_fim' => $dados['data_fim'],
                'valor_total' => $valorTotal,
                'empresa_id' => $empresaId,
                'status' => 'pendente',
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            self::auditar('criar_settlement', $settlement, null, $settlement->toArray());

            return $settlement;
        });
    }

    /**
     * Fechar caixa de um usuário (iniciado por gestor/admin)
     * Calcula o saldo atual do usuário e cria um fechamento
     *
     * @param int $usuarioId Usuário que terá o caixa fechado
     * @param int $operacaoId Operação
     * @param int $gestorId Gestor/Admin que está fechando
     * @param string|null $observacoes
     * @return Settlement
     */
    public function fecharCaixa(int $usuarioId, int $operacaoId, int $criadoPorId, ?string $observacoes = null): Settlement
    {
        return DB::transaction(function () use ($usuarioId, $operacaoId, $criadoPorId, $observacoes) {
            $cashService = app(\App\Modules\Cash\Services\CashService::class);

            // Verificar se é o próprio usuário fechando o caixa
            $fechamentoProprio = ($usuarioId === $criadoPorId);

            // Buscar último fechamento concluído do usuário nesta operação
            $ultimoFechamento = Settlement::where('consultor_id', $usuarioId)
                ->where('operacao_id', $operacaoId)
                ->where('status', 'concluido')
                ->orderBy('data_fim', 'desc')
                ->first();

            // Data início = dia seguinte ao último fechamento ou primeira movimentação
            if ($ultimoFechamento) {
                $dataInicio = $ultimoFechamento->data_fim->addDay()->format('Y-m-d');
            } else {
                $primeiraMov = CashLedgerEntry::where('consultor_id', $usuarioId)
                    ->where('operacao_id', $operacaoId)
                    ->orderBy('data_movimentacao', 'asc')
                    ->first();
                $dataInicio = $primeiraMov ? $primeiraMov->data_movimentacao->format('Y-m-d') : now()->format('Y-m-d');
            }

            $dataFim = now()->format('Y-m-d');

            // Calcular saldo atual do usuário na operação
            $saldoAtual = $cashService->calcularSaldo($usuarioId, $operacaoId);

            if ($saldoAtual <= 0) {
                throw ValidationException::withMessages([
                    'usuario' => 'Não há saldo positivo para fechar. Saldo atual: R$ ' . number_format($saldoAtual, 2, ',', '.')
                ]);
            }

            // Obter empresa_id da operação
            $operacao = \App\Modules\Core\Models\Operacao::find($operacaoId);
            $empresaId = $operacao->empresa_id ?? (auth()->check() && !auth()->user()->isSuperAdmin() ? auth()->user()->empresa_id : null);

            $settlement = Settlement::create([
                'operacao_id' => $operacaoId,
                'consultor_id' => $usuarioId,
                'criado_por' => $criadoPorId,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'valor_total' => $saldoAtual,
                'empresa_id' => $empresaId,
                'status' => 'aprovado',
                'conferido_por' => $criadoPorId,
                'conferido_em' => now(),
                'observacoes' => $observacoes,
            ]);

            self::auditar('fechar_caixa', $settlement, null, $settlement->toArray());

            // Notificar apenas se não foi o próprio usuário que fechou
            if (!$fechamentoProprio) {
                $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                $notificacaoService->criar([
                    'user_id' => $usuarioId,
                    'tipo' => 'prestacao_pendente',
                    'titulo' => 'Seu caixa foi fechado',
                    'mensagem' => "Seu caixa na operação {$operacao->nome} foi fechado. Valor a enviar: R$ " . number_format($saldoAtual, 2, ',', '.') . ". Anexe o comprovante de envio.",
                    'url' => route('fechamento-caixa.show', $settlement->id),
                    'dados' => ['settlement_id' => $settlement->id],
                ]);
            }

            return $settlement;
        });
    }

    /**
     * Listar usuários com saldo positivo em uma operação (para fechamento de caixa)
     *
     * @param int $operacaoId
     * @return \Illuminate\Support\Collection
     */
    public function listarUsuariosComSaldo(int $operacaoId): \Illuminate\Support\Collection
    {
        $cashService = app(\App\Modules\Cash\Services\CashService::class);

        // Buscar todos os usuários que têm movimentações nesta operação
        $usuariosIds = CashLedgerEntry::where('operacao_id', $operacaoId)
            ->whereNotNull('consultor_id')
            ->distinct()
            ->pluck('consultor_id');

        $usuarios = \App\Models\User::whereIn('id', $usuariosIds)
            ->with('roles')
            ->get();

        // Calcular saldo de cada usuário
        return $usuarios->map(function ($usuario) use ($operacaoId, $cashService) {
            $saldo = $cashService->calcularSaldo($usuario->id, $operacaoId);
            $usuario->saldo_operacao = $saldo;
            return $usuario;
        })->filter(function ($usuario) {
            return $usuario->saldo_operacao > 0; // Apenas usuários com saldo positivo
        })->sortByDesc('saldo_operacao')->values();
    }

    /**
     * Aprovar prestação de contas (Gestor ou Administrador)
     *
     * @param int $settlementId
     * @param int $userId
     * @param string|null $observacoes
     * @return Settlement
     */
    public function aprovar(int $settlementId, int $userId, ?string $observacoes = null): Settlement
    {
        $settlement = Settlement::findOrFail($settlementId);

        if ($settlement->status !== 'pendente') {
            throw ValidationException::withMessages([
                'settlement' => 'Apenas prestações pendentes podem ser aprovadas. Status atual: ' . $settlement->status
            ]);
        }

        $oldStatus = $settlement->status;

        $settlement->update([
            'status' => 'aprovado',
            'conferido_por' => $userId, // Mantém compatibilidade com campo existente
            'conferido_em' => now(),
            'observacoes' => $observacoes ?? $settlement->observacoes,
        ]);

        // Auditoria
        self::auditar(
            'aprovar_settlement',
            $settlement,
            ['status' => $oldStatus],
            ['status' => 'aprovado']
        );

        return $settlement->fresh();
    }

    /**
     * Consultor anexa comprovante de envio
     * NÃO gera movimentações ainda - apenas armazena o comprovante
     *
     * @param int $settlementId
     * @param string $comprovantePath
     * @return Settlement
     */
    public function anexarComprovante(int $settlementId, string $comprovantePath): Settlement
    {
        $settlement = Settlement::with(['consultor', 'operacao'])->findOrFail($settlementId);

        if ($settlement->status !== 'aprovado') {
            throw ValidationException::withMessages([
                'settlement' => 'Apenas prestações aprovadas podem ter comprovante anexado. Status atual: ' . $settlement->status
            ]);
        }

        $oldStatus = $settlement->status;

        $settlement->update([
            'status' => 'enviado',
            'comprovante_path' => $comprovantePath,
            'enviado_em' => now(),
        ]);

        // Auditoria
        self::auditar(
            'anexar_comprovante_settlement',
            $settlement,
            ['status' => $oldStatus],
            ['status' => 'enviado', 'comprovante_path' => $comprovantePath]
        );

        // Notificar gestores da operação e administradores
        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        $consultorNome = $settlement->consultor?->name ?? 'Usuário';
        $operacaoNome = $settlement->operacao?->nome ?? 'Operação';
        $valorFormatado = 'R$ ' . number_format($settlement->valor_total, 2, ',', '.');

        $mensagem = "{$consultorNome} enviou comprovante de fechamento de caixa ({$operacaoNome}) - {$valorFormatado}. Confirme o recebimento.";

        $dadosNotificacao = [
            'tipo' => 'prestacao_pendente',
            'titulo' => 'Comprovante de fechamento recebido',
            'mensagem' => $mensagem,
            'url' => route('fechamento-caixa.show', $settlement->id),
            'dados' => ['settlement_id' => $settlement->id],
        ];

        // Gestores da operação específica
        $gestoresIds = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'gestor'))
            ->whereHas('operacoes', fn($q) => $q->where('operacoes.id', $settlement->operacao_id))
            ->where('id', '!=', $settlement->consultor_id)
            ->pluck('id')
            ->toArray();

        if (!empty($gestoresIds)) {
            $notificacaoService->criarParaMultiplos($gestoresIds, $dadosNotificacao);
        }

        // Todos os administradores (exceto o próprio consultor se for admin)
        $adminsIds = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'administrador'))
            ->where('id', '!=', $settlement->consultor_id)
            ->pluck('id')
            ->toArray();

        if (!empty($adminsIds)) {
            $notificacaoService->criarParaMultiplos($adminsIds, $dadosNotificacao);
        }

        return $settlement->fresh();
    }

    /**
     * Gestor confirma recebimento do dinheiro
     * GERA as movimentações de caixa automaticamente
     *
     * @param int $settlementId
     * @param int $gestorId
     * @param string|null $observacoes
     * @return Settlement
     */
    public function confirmarRecebimento(int $settlementId, int $gestorId, ?string $observacoes = null): Settlement
    {
        return DB::transaction(function () use ($settlementId, $gestorId, $observacoes) {
            $settlement = Settlement::with(['consultor', 'operacao'])->findOrFail($settlementId);

            if ($settlement->status !== 'enviado') {
                throw ValidationException::withMessages([
                    'settlement' => 'Apenas prestações com comprovante anexado podem ter recebimento confirmado. Status atual: ' . $settlement->status
                ]);
            }

            if (!$settlement->comprovante_path) {
                throw ValidationException::withMessages([
                    'settlement' => 'Não é possível confirmar recebimento sem comprovante anexado.'
                ]);
            }

            $oldStatus = $settlement->status;

            // Atualizar settlement
            $settlement->update([
                'status' => 'concluido',
                'recebido_por' => $gestorId,
                'recebido_em' => now(),
                'observacoes' => $observacoes ?? $settlement->observacoes,
            ]);

            // GERAR MOVIMENTAÇÕES DE CAIXA
            $cashService = app(\App\Modules\Cash\Services\CashService::class);

            // 1. Saída do caixa do consultor
            $cashService->registrarMovimentacao([
                'operacao_id' => $settlement->operacao_id,
                'consultor_id' => $settlement->consultor_id,
                'tipo' => 'saida',
                'origem' => 'automatica',
                'valor' => $settlement->valor_total,
                'data_movimentacao' => now(),
                'descricao' => "Prestação de contas - Período {$settlement->data_inicio->format('d/m/Y')} a {$settlement->data_fim->format('d/m/Y')}",
                'referencia_tipo' => 'settlement',
                'referencia_id' => $settlement->id,
                'comprovante_path' => $settlement->comprovante_path, // Mesmo comprovante
            ]);

            // 2. Entrada no caixa do gestor
            $cashService->registrarMovimentacao([
                'operacao_id' => $settlement->operacao_id,
                'consultor_id' => $gestorId,
                'tipo' => 'entrada',
                'origem' => 'automatica',
                'valor' => $settlement->valor_total,
                'data_movimentacao' => now(),
                'descricao' => "Recebimento de prestação de contas - Consultor {$settlement->consultor->name} - Período {$settlement->data_inicio->format('d/m/Y')} a {$settlement->data_fim->format('d/m/Y')}",
                'referencia_tipo' => 'settlement',
                'referencia_id' => $settlement->id,
                'comprovante_path' => null, // Gestor não precisa anexar comprovante
            ]);

            // Auditoria
            self::auditar(
                'confirmar_recebimento_settlement',
                $settlement,
                ['status' => $oldStatus],
                ['status' => 'concluido', 'recebido_por' => $gestorId]
            );

            return $settlement->fresh();
        });
    }

    /**
     * Rejeitar prestação de contas (Gestor ou Administrador)
     *
     * @param int $settlementId
     * @param int $userId
     * @param string $motivoRejeicao
     * @return Settlement
     */
    public function rejeitar(int $settlementId, int $userId, string $motivoRejeicao): Settlement
    {
        $settlement = Settlement::findOrFail($settlementId);

        // Pode rejeitar se estiver pendente ou aprovado
        if (!in_array($settlement->status, ['pendente', 'aprovado'])) {
            throw ValidationException::withMessages([
                'settlement' => 'Apenas prestações pendentes ou aprovadas podem ser rejeitadas. Status atual: ' . $settlement->status
            ]);
        }

        $oldStatus = $settlement->status;

        $settlement->update([
            'status' => 'rejeitado',
            'motivo_rejeicao' => $motivoRejeicao,
        ]);

        // Auditoria
        self::auditar(
            'rejeitar_settlement',
            $settlement,
            ['status' => $oldStatus],
            ['status' => 'rejeitado', 'motivo_rejeicao' => $motivoRejeicao]
        );

        return $settlement->fresh();
    }

    /**
     * Listar settlements com filtros flexíveis
     *
     * @param int|null $consultorId null = todos os usuários
     * @param int|null $operacaoId
     * @param string|null $status
     * @param \App\Models\User|null $user
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listar(
        ?int $consultorId = null,
        ?int $operacaoId = null,
        ?string $status = null,
        ?\App\Models\User $user = null,
        ?string $dataInicio = null,
        ?string $dataFim = null
    ) {
        $query = Settlement::with(['operacao', 'consultor', 'criador', 'conferidor', 'recebedor']);

        // Filtrar por consultor específico
        if ($consultorId !== null) {
            $query->where('consultor_id', $consultorId);
        }

        if ($user) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        // Filtrar por status
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        // Filtrar por período (data de criação do fechamento)
        if ($dataInicio) {
            $query->whereDate('created_at', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('created_at', '<=', $dataFim);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
    }

    /**
     * Contar settlements aguardando confirmação (status = enviado)
     * Para exibir badge no sidebar
     *
     * @param \App\Models\User $user
     * @return int
     */
    public function contarAguardandoConfirmacao(\App\Models\User $user): int
    {
        $query = Settlement::where('status', 'enviado');

        $operacoesIds = $user->getOperacoesIds();
        if (!empty($operacoesIds)) {
            $query->whereIn('operacao_id', $operacoesIds);
        } else {
            return 0;
        }

        return $query->count();
    }

    /**
     * Listar settlements do consultor (método legado, mantido para compatibilidade)
     *
     * @param int $consultorId
     * @param int|null $operacaoId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarPorConsultor(int $consultorId, ?int $operacaoId = null, ?\App\Models\User $user = null)
    {
        $query = Settlement::with(['operacao', 'conferidor', 'validador', 'recebedor'])
            ->where('consultor_id', $consultorId);

        if ($user) {
            $operacoesIds = $user->getOperacoesIds();
            if (!empty($operacoesIds)) {
                $query->whereIn('operacao_id', $operacoesIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
    }
}
