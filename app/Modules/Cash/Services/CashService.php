<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Core\Traits\Auditable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashService
{
    use Auditable;

    /**
     * Registrar movimentação de caixa
     */
    public function registrarMovimentacao(array $dados): CashLedgerEntry
    {
        // Se não especificar origem, assume 'automatica' (compatibilidade com código existente)
        if (! isset($dados['origem'])) {
            $dados['origem'] = 'automatica';
        }

        // Obter empresa_id da operação se não foi informado
        if (! isset($dados['empresa_id']) && isset($dados['operacao_id'])) {
            $operacao = \App\Modules\Core\Models\Operacao::find($dados['operacao_id']);
            $dados['empresa_id'] = $operacao->empresa_id ?? (auth()->check() && ! auth()->user()->isSuperAdmin() ? auth()->user()->empresa_id : null);
        } elseif (! isset($dados['empresa_id']) && auth()->check() && ! auth()->user()->isSuperAdmin()) {
            $dados['empresa_id'] = auth()->user()->empresa_id;
        }

        if (empty($dados['categoria_id']) && ! empty($dados['referencia_tipo']) && ! empty($dados['tipo']) && ! empty($dados['empresa_id'])) {
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
     * @param  int  $operacaoId  (0 = todas as operações)
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
     * @param  int|null  $operacaoId  (null = todas as operações)
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
     * @param  int|null  $consultorId  Se null, lista todas (incluindo caixa da operação)
     * @param  bool|null  $apenasCaixaOperacao  Se true, lista apenas movimentações com consultor_id NULL
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
     * @param  int|null  $consultorId  Se null, inclui todas (caixa da operação + usuários), a menos que $apenasCaixaOperacao seja true
     * @param  bool  $apenasCaixaOperacao  Se true, filtra apenas movimentações com consultor_id NULL (caixa da operação)
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
     * @param  int|null  $consultorId  Se null, inclui todas (caixa da operação + usuários), a menos que $apenasCaixaOperacao seja true
     * @param  bool  $apenasCaixaOperacao  Se true, filtra apenas movimentações com consultor_id NULL (caixa da operação)
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
     * @param  int|null  $consultorId  Se null e for caixa da operação, deve filtrar por consultor_id IS NULL
     * @param  bool  $apenasCaixaOperacao  Se true, filtra apenas por consultor_id IS NULL
     */
    public function calcularSaldoInicial(
        ?int $consultorId = null,
        ?int $operacaoId = null,
        ?string $dataInicio = null,
        bool $apenasCaixaOperacao = false
    ): float {
        if (! $dataInicio) {
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
     * Obter saldos do usuário por operação (para exibição no header).
     * Chaves: total (soma todas), total_topo (valor no botão: operação preferida se existir, senão total), operacoes.
     *
     * @return array{total: float, total_topo: float, operacoes: list<array{id: int, nome: string, saldo: float}>}
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

        $preferidaId = $user->getOperacaoPrincipalId();
        $saldoTopo = $saldoTotal;
        if ($preferidaId !== null) {
            foreach ($saldosPorOperacao as $item) {
                if ((int) $item['id'] === (int) $preferidaId) {
                    $saldoTopo = (float) $item['saldo'];
                    break;
                }
            }
        }

        return [
            'total' => $saldoTotal,
            'total_topo' => $saldoTopo,
            'operacoes' => $saldosPorOperacao,
        ];
    }

    /**
     * Sangria: transfere valor do caixa do gestor/admin para o Caixa da Operação (consultor_id NULL).
     * Gera saída no usuário e entrada no caixa da operação, em uma transação.
     *
     * @return array{saida: CashLedgerEntry, entrada: CashLedgerEntry}
     */
    public function transferirParaCaixaOperacao(int $usuarioId, int $operacaoId, float $valor, ?string $observacoes = null, ?string $comprovantePath = null): array
    {
        $valor = round($valor, 2);
        if ($valor < 0.01) {
            throw ValidationException::withMessages([
                'valor' => 'Informe um valor maior que zero.',
            ]);
        }

        return DB::transaction(function () use ($usuarioId, $operacaoId, $valor, $observacoes, $comprovantePath) {
            $saldo = $this->calcularSaldo($usuarioId, $operacaoId);
            if (round($saldo, 2) < $valor) {
                throw ValidationException::withMessages([
                    'valor' => 'Saldo insuficiente. Saldo disponível: R$ '.number_format($saldo, 2, ',', '.'),
                ]);
            }

            $user = \App\Models\User::findOrFail($usuarioId);
            if (! $user->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
                throw ValidationException::withMessages([
                    'operacao_id' => 'Apenas gestores ou administradores da operação podem executar sangria.',
                ]);
            }

            $operacao = \App\Modules\Core\Models\Operacao::findOrFail($operacaoId);

            $refTipo = 'sangria_caixa_operacao';
            $dataMov = now()->format('Y-m-d');

            $dadosSaida = [
                'operacao_id' => $operacaoId,
                'consultor_id' => $usuarioId,
                'tipo' => 'saida',
                'origem' => 'automatica',
                'valor' => $valor,
                'data_movimentacao' => $dataMov,
                'descricao' => 'Sangria para o Caixa da Operação — '.$operacao->nome,
                'observacoes' => $observacoes,
                'referencia_tipo' => $refTipo,
                'referencia_id' => null,
            ];
            if ($comprovantePath !== null && $comprovantePath !== '') {
                $dadosSaida['comprovante_path'] = $comprovantePath;
            }

            $saida = $this->registrarMovimentacao($dadosSaida);

            $dadosEntrada = [
                'operacao_id' => $operacaoId,
                'consultor_id' => null,
                'tipo' => 'entrada',
                'origem' => 'automatica',
                'valor' => $valor,
                'data_movimentacao' => $dataMov,
                'descricao' => 'Sangria recebida — '.$user->name,
                'observacoes' => $observacoes,
                'referencia_tipo' => $refTipo,
                'referencia_id' => $saida->id,
            ];
            if ($comprovantePath !== null && $comprovantePath !== '') {
                $dadosEntrada['comprovante_path'] = $comprovantePath;
            }

            $entrada = $this->registrarMovimentacao($dadosEntrada);

            return ['saida' => $saida->fresh(), 'entrada' => $entrada->fresh()];
        });
    }

    /**
     * Transferência: valor do Caixa da Operação (consultor_id NULL) para o caixa de um gestor/admin na mesma operação.
     * Apenas quem tem papel **administrador** na operação pode executar (validado antes de chamar).
     *
     * @return array{saida: CashLedgerEntry, entrada: CashLedgerEntry}
     */
    public function transferirDoCaixaOperacaoParaUsuario(
        int $executorId,
        int $operacaoId,
        int $destinatarioId,
        float $valor,
        ?string $observacoes = null,
        ?string $comprovantePath = null
    ): array {
        $valor = round($valor, 2);
        if ($valor < 0.01) {
            throw ValidationException::withMessages([
                'valor' => 'Informe um valor maior que zero.',
            ]);
        }

        return DB::transaction(function () use ($executorId, $operacaoId, $destinatarioId, $valor, $observacoes, $comprovantePath) {
            $saldoOperacao = $this->calcularSaldoOperacao($operacaoId);
            if (round($saldoOperacao, 2) < $valor) {
                throw ValidationException::withMessages([
                    'valor' => 'Saldo insuficiente no Caixa da Operação. Disponível: R$ '.number_format($saldoOperacao, 2, ',', '.'),
                ]);
            }

            $executor = \App\Models\User::findOrFail($executorId);
            if (! $executor->temAlgumPapelNaOperacao($operacaoId, ['administrador'])) {
                throw ValidationException::withMessages([
                    'operacao_id' => 'Apenas administradores da operação podem executar esta transferência.',
                ]);
            }

            $destinatario = \App\Models\User::findOrFail($destinatarioId);
            if (! $destinatario->temAlgumPapelNaOperacao($operacaoId, ['gestor', 'administrador'])) {
                throw ValidationException::withMessages([
                    'destinatario_id' => 'O destinatário deve ser gestor ou administrador nesta operação.',
                ]);
            }

            $operacao = \App\Modules\Core\Models\Operacao::findOrFail($operacaoId);

            $refTipo = 'transferencia_caixa_operacao';
            $dataMov = now()->format('Y-m-d');

            $dadosSaida = [
                'operacao_id' => $operacaoId,
                'consultor_id' => null,
                'tipo' => 'saida',
                'origem' => 'automatica',
                'valor' => $valor,
                'data_movimentacao' => $dataMov,
                'descricao' => 'Transferência do Caixa da Operação → '.$destinatario->name,
                'observacoes' => $observacoes,
                'referencia_tipo' => $refTipo,
                'referencia_id' => null,
            ];
            if ($comprovantePath !== null && $comprovantePath !== '') {
                $dadosSaida['comprovante_path'] = $comprovantePath;
            }

            $saida = $this->registrarMovimentacao($dadosSaida);

            $dadosEntrada = [
                'operacao_id' => $operacaoId,
                'consultor_id' => $destinatarioId,
                'tipo' => 'entrada',
                'origem' => 'automatica',
                'valor' => $valor,
                'data_movimentacao' => $dataMov,
                'descricao' => 'Transferência recebida — Caixa da Operação ('.$operacao->nome.')',
                'observacoes' => $observacoes,
                'referencia_tipo' => $refTipo,
                'referencia_id' => $saida->id,
            ];
            if ($comprovantePath !== null && $comprovantePath !== '') {
                $dadosEntrada['comprovante_path'] = $comprovantePath;
            }

            $entrada = $this->registrarMovimentacao($dadosEntrada);

            return ['saida' => $saida->fresh(), 'entrada' => $entrada->fresh()];
        });
    }
}
