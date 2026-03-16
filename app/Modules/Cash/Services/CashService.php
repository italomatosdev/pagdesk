<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Core\Traits\Auditable;
use Illuminate\Support\Collection;

class CashService
{
    use Auditable;

    /**
     * Registrar movimentação de caixa
     *
     * @param array $dados
     * @return CashLedgerEntry
     */
    public function registrarMovimentacao(array $dados): CashLedgerEntry
    {
        // Se não especificar origem, assume 'automatica' (compatibilidade com código existente)
        if (!isset($dados['origem'])) {
            $dados['origem'] = 'automatica';
        }

        // Obter empresa_id da operação se não foi informado
        if (!isset($dados['empresa_id']) && isset($dados['operacao_id'])) {
            $operacao = \App\Modules\Core\Models\Operacao::find($dados['operacao_id']);
            $dados['empresa_id'] = $operacao->empresa_id ?? (auth()->check() && !auth()->user()->isSuperAdmin() ? auth()->user()->empresa_id : null);
        } elseif (!isset($dados['empresa_id']) && auth()->check() && !auth()->user()->isSuperAdmin()) {
            $dados['empresa_id'] = auth()->user()->empresa_id;
        }

        if (empty($dados['categoria_id']) && !empty($dados['referencia_tipo']) && !empty($dados['tipo']) && !empty($dados['empresa_id'])) {
            $resolver = app(CashCategoriaAutomaticaService::class);
            $categoriaId = $resolver->resolverCategoriaId(
                (int) $dados['empresa_id'],
                $dados['referencia_tipo'],
                $dados['tipo']
            );
            if ($categoriaId !== null) {
                $dados['categoria_id'] = $categoriaId;
            }
        }

        $movimentacao = CashLedgerEntry::create($dados);

        // Auditoria
        self::auditar('registrar_movimentacao_caixa', $movimentacao, null, $movimentacao->toArray());

        return $movimentacao;
    }

    /**
     * Calcular saldo do consultor em uma operação
     *
     * @param int $consultorId
     * @param int $operacaoId (0 = todas as operações)
     * @return float
     */
    public function calcularSaldo(int $consultorId, int $operacaoId = 0): float
    {
        $queryEntradas = CashLedgerEntry::where('consultor_id', $consultorId)
            ->where('tipo', 'entrada');
        
        $querySaidas = CashLedgerEntry::where('consultor_id', $consultorId)
            ->where('tipo', 'saida');

        if ($operacaoId > 0) {
            $queryEntradas->where('operacao_id', $operacaoId);
            $querySaidas->where('operacao_id', $operacaoId);
        }

        $entradas = $queryEntradas->sum('valor');
        $saidas = $querySaidas->sum('valor');

        return $entradas - $saidas;
    }

    /**
     * Calcular saldo total (todos os consultores + caixa da operação) em uma operação
     *
     * @param int|null $operacaoId (null = todas as operações)
     * @return float
     */
    public function calcularSaldoTotal(?int $operacaoId = null): float
    {
        $queryEntradas = CashLedgerEntry::where('tipo', 'entrada');
        $querySaidas = CashLedgerEntry::where('tipo', 'saida');

        if ($operacaoId) {
            $queryEntradas->where('operacao_id', $operacaoId);
            $querySaidas->where('operacao_id', $operacaoId);
        }

        $entradas = $queryEntradas->sum('valor');
        $saidas = $querySaidas->sum('valor');

        return $entradas - $saidas;
    }

    /**
     * Calcular saldo do caixa da operação (sem usuário específico)
     *
     * @param int $operacaoId
     * @return float
     */
    public function calcularSaldoOperacao(int $operacaoId): float
    {
        $entradas = CashLedgerEntry::where('operacao_id', $operacaoId)
            ->whereNull('consultor_id')
            ->where('tipo', 'entrada')
            ->sum('valor') ?? 0;

        $saidas = CashLedgerEntry::where('operacao_id', $operacaoId)
            ->whereNull('consultor_id')
            ->where('tipo', 'saida')
            ->sum('valor') ?? 0;

        return $entradas - $saidas;
    }

    /**
     * Listar movimentações
     * 
     * @param int|null $consultorId Se null, lista todas (incluindo caixa da operação)
     * @param int|null $operacaoId
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @param bool|null $apenasCaixaOperacao Se true, lista apenas movimentações com consultor_id NULL
     * @return Collection
     */
    public function listarMovimentacoes(
        ?int $consultorId = null,
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        ?bool $apenasCaixaOperacao = null
    ): Collection {
        $query = CashLedgerEntry::with(['operacao', 'consultor', 'pagamento.parcela.emprestimo']);

        // Filtrar por tipo de caixa
        if ($apenasCaixaOperacao === true) {
            // Apenas caixa da operação (consultor_id NULL)
            $query->whereNull('consultor_id');
        } elseif ($apenasCaixaOperacao === false) {
            // Apenas caixas de usuários (consultor_id NOT NULL)
            $query->whereNotNull('consultor_id');
        } elseif ($consultorId !== null) {
            // Consultor específico
            $query->where('consultor_id', $consultorId);
        }
        // Se $consultorId for null e $apenasCaixaOperacao for null, lista todas

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        if ($dataInicio) {
            $query->where('data_movimentacao', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->where('data_movimentacao', '<=', $dataFim);
        }

        return $query->orderBy('data_movimentacao', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Calcular total de entradas com filtros
     *
     * @param int|null $consultorId Se null, inclui todas (caixa da operação + usuários), a menos que $apenasCaixaOperacao seja true
     * @param int|null $operacaoId
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @param bool $apenasCaixaOperacao Se true, filtra apenas movimentações com consultor_id NULL (caixa da operação)
     * @return float
     */
    public function calcularTotalEntradas(
        ?int $consultorId = null,
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        bool $apenasCaixaOperacao = false
    ): float {
        $query = CashLedgerEntry::where('tipo', 'entrada');

        if ($apenasCaixaOperacao) {
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

        return $query->sum('valor') ?? 0;
    }

    /**
     * Calcular total de saídas com filtros
     *
     * @param int|null $consultorId Se null, inclui todas (caixa da operação + usuários), a menos que $apenasCaixaOperacao seja true
     * @param int|null $operacaoId
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @param bool $apenasCaixaOperacao Se true, filtra apenas movimentações com consultor_id NULL (caixa da operação)
     * @return float
     */
    public function calcularTotalSaidas(
        ?int $consultorId = null,
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null,
        bool $apenasCaixaOperacao = false
    ): float {
        $query = CashLedgerEntry::where('tipo', 'saida');

        if ($apenasCaixaOperacao) {
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

        return $query->sum('valor') ?? 0;
    }

    /**
     * Calcular saldo inicial (antes do período filtrado)
     *
     * @param int|null $consultorId Se null e for caixa da operação, deve filtrar por consultor_id IS NULL
     * @param int|null $operacaoId
     * @param string|null $dataInicio
     * @param bool $apenasCaixaOperacao Se true, filtra apenas por consultor_id IS NULL
     * @return float
     */
    public function calcularSaldoInicial(
        ?int $consultorId = null,
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        bool $apenasCaixaOperacao = false
    ): float {
        if (!$dataInicio) {
            return 0;
        }

        $queryEntradas = CashLedgerEntry::where('tipo', 'entrada')
            ->where('data_movimentacao', '<', $dataInicio);
        
        $querySaidas = CashLedgerEntry::where('tipo', 'saida')
            ->where('data_movimentacao', '<', $dataInicio);

        if ($apenasCaixaOperacao) {
            // Apenas caixa da operação
            $queryEntradas->whereNull('consultor_id');
            $querySaidas->whereNull('consultor_id');
        } elseif ($consultorId !== null) {
            // Consultor/gestor específico
            $queryEntradas->where('consultor_id', $consultorId);
            $querySaidas->where('consultor_id', $consultorId);
        }
        // Se consultorId for null e apenasCaixaOperacao for false, não filtra (inclui todos)

        if ($operacaoId) {
            $queryEntradas->where('operacao_id', $operacaoId);
            $querySaidas->where('operacao_id', $operacaoId);
        }

        $entradas = $queryEntradas->sum('valor') ?? 0;
        $saidas = $querySaidas->sum('valor') ?? 0;

        return $entradas - $saidas;
    }

    /**
     * Calcular total de entradas do caixa da operação com filtros de período
     *
     * @param int|null $operacaoId
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return float
     */
    public function calcularTotalEntradasOperacao(
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null
    ): float {
        $query = CashLedgerEntry::where('tipo', 'entrada')
            ->whereNull('consultor_id');

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        if ($dataInicio) {
            $query->where('data_movimentacao', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->where('data_movimentacao', '<=', $dataFim);
        }

        return $query->sum('valor') ?? 0;
    }

    /**
     * Calcular total de saídas do caixa da operação com filtros de período
     *
     * @param int|null $operacaoId
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return float
     */
    public function calcularTotalSaidasOperacao(
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        ?string $dataFim = null
    ): float {
        $query = CashLedgerEntry::where('tipo', 'saida')
            ->whereNull('consultor_id');

        if ($operacaoId) {
            $query->where('operacao_id', $operacaoId);
        }

        if ($dataInicio) {
            $query->where('data_movimentacao', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->where('data_movimentacao', '<=', $dataFim);
        }

        return $query->sum('valor') ?? 0;
    }

    /**
     * Obter saldos do usuário por operação (para exibição no header)
     *
     * @param \App\Models\User $user
     * @return array ['total' => float, 'operacoes' => [['id' => int, 'nome' => string, 'saldo' => float], ...]]
     */
    public function getSaldosUsuarioHeader(\App\Models\User $user): array
    {
        $operacoes = $user->operacoes;
        $saldosPorOperacao = [];
        $saldoTotal = 0;

        foreach ($operacoes as $operacao) {
            $saldo = $this->calcularSaldo($user->id, $operacao->id);
            $saldosPorOperacao[] = [
                'id' => $operacao->id,
                'nome' => $operacao->nome,
                'saldo' => $saldo,
            ];
            $saldoTotal += $saldo;
        }

        return [
            'total' => $saldoTotal,
            'operacoes' => $saldosPorOperacao,
        ];
    }
}
