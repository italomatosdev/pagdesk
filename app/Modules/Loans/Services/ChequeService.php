<?php

namespace App\Modules\Loans\Services;

use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\EmprestimoCheque;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChequeService
{
    use Auditable;

    /**
     * Criar cheque para empréstimo tipo troca_cheque
     *
     * @param int $emprestimoId
     * @param array $dados
     * @return EmprestimoCheque
     * @throws ValidationException
     */
    public function criar(int $emprestimoId, array $dados): EmprestimoCheque
    {
        return DB::transaction(function () use ($emprestimoId, $dados) {
            $emprestimo = Emprestimo::findOrFail($emprestimoId);

            // Validar se é tipo troca_cheque
            if (!$emprestimo->isTrocaCheque()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos do tipo troca de cheque podem ter cheques cadastrados.'
                ]);
            }

            // Validar se empréstimo não está finalizado
            if ($emprestimo->isFinalizado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível adicionar cheques a empréstimos finalizados.'
                ]);
            }

            // Validar se cheque não está vencido
            $dataVencimento = Carbon::parse($dados['data_vencimento']);
            if ($dataVencimento->isPast()) {
                throw ValidationException::withMessages([
                    'data_vencimento' => 'Não é possível cadastrar cheque com data de vencimento no passado.'
                ]);
            }

            // Calcular dias até vencimento
            $diasAteVencimento = Carbon::today()->diffInDays($dataVencimento);

            // Calcular juros (usar taxa do empréstimo ou operação)
            $taxaJuros = $dados['taxa_juros'] ?? $emprestimo->taxa_juros ?? 0;
            if ($taxaJuros <= 0) {
                // Tentar pegar da operação
                $operacao = $emprestimo->operacao;
                $taxaJuros = $operacao->taxa_juros_atraso ?? 0;
            }

            $valorCheque = $dados['valor_cheque'];
            $valorJuros = ($valorCheque * $taxaJuros * $diasAteVencimento) / (100 * 30);
            $valorLiquido = $valorCheque - $valorJuros;

            // Normalizar banco, agência, conta e número (podem ser preenchidos depois)
            $banco = isset($dados['banco']) && trim((string) $dados['banco']) !== '' ? trim($dados['banco']) : null;
            $agencia = isset($dados['agencia']) && trim((string) $dados['agencia']) !== '' ? trim($dados['agencia']) : null;
            $conta = isset($dados['conta']) && trim((string) $dados['conta']) !== '' ? trim($dados['conta']) : null;
            $numeroCheque = isset($dados['numero_cheque']) && trim((string) $dados['numero_cheque']) !== '' ? trim($dados['numero_cheque']) : null;

            // Verificar duplicidade só quando todos os dados do cheque estiverem preenchidos
            if ($numeroCheque !== null && $banco !== null && $agencia !== null && $conta !== null) {
                $chequeExistente = EmprestimoCheque::where('numero_cheque', $numeroCheque)
                    ->where('banco', $banco)
                    ->where('agencia', $agencia)
                    ->where('conta', $conta)
                    ->where('emprestimo_id', '!=', $emprestimoId)
                    ->first();

                if ($chequeExistente) {
                    throw ValidationException::withMessages([
                        'numero_cheque' => 'Este cheque já foi cadastrado em outro empréstimo.'
                    ]);
                }
            }

            // Criar cheque
            $cheque = EmprestimoCheque::create([
                'emprestimo_id' => $emprestimoId,
                'banco' => $banco,
                'agencia' => $agencia,
                'conta' => $conta,
                'numero_cheque' => $numeroCheque,
                'data_vencimento' => $dataVencimento,
                'valor_cheque' => $valorCheque,
                'dias_ate_vencimento' => $diasAteVencimento,
                'taxa_juros' => $taxaJuros,
                'valor_juros' => round($valorJuros, 2),
                'valor_liquido' => round($valorLiquido, 2),
                'portador' => $dados['portador'] ?? null,
                'status' => 'aguardando',
                'observacoes' => $dados['observacoes'] ?? null,
                'empresa_id' => $emprestimo->empresa_id,
            ]);

            // Auditoria
            self::auditar(
                'criar_cheque',
                $cheque,
                null,
                $cheque->toArray(),
                "Cheque cadastrado para troca de cheque #{$emprestimoId}"
            );

            return $cheque->fresh();
        });
    }

    /**
     * Atualizar cheque
     *
     * @param int $chequeId
     * @param array $dados
     * @return EmprestimoCheque
     * @throws ValidationException
     */
    public function atualizar(int $chequeId, array $dados): EmprestimoCheque
    {
        return DB::transaction(function () use ($chequeId, $dados) {
            $cheque = EmprestimoCheque::findOrFail($chequeId);
            $emprestimo = $cheque->emprestimo;

            // Validar se empréstimo não está finalizado
            if ($emprestimo->isFinalizado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível editar cheques de empréstimos finalizados.'
                ]);
            }

            // Validar se cheque não está compensado ou devolvido
            if ($cheque->isCompensado() || $cheque->isDevolvido()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Não é possível editar cheques que já foram compensados ou devolvidos.'
                ]);
            }

            $oldData = $cheque->toArray();

            // Se mudou data de vencimento ou valor, recalcular juros
            $recalcularJuros = false;
            if (isset($dados['data_vencimento']) || isset($dados['valor_cheque'])) {
                $recalcularJuros = true;
            }

            // Atualizar campos básicos
            if (isset($dados['banco'])) $cheque->banco = $dados['banco'];
            if (isset($dados['agencia'])) $cheque->agencia = $dados['agencia'];
            if (isset($dados['conta'])) $cheque->conta = $dados['conta'];
            if (isset($dados['numero_cheque'])) $cheque->numero_cheque = $dados['numero_cheque'];
            if (isset($dados['data_vencimento'])) {
                $dataVencimento = Carbon::parse($dados['data_vencimento']);
                if ($dataVencimento->isPast()) {
                    throw ValidationException::withMessages([
                        'data_vencimento' => 'Não é possível alterar para data de vencimento no passado.'
                    ]);
                }
                $cheque->data_vencimento = $dataVencimento;
            }
            if (isset($dados['valor_cheque'])) $cheque->valor_cheque = $dados['valor_cheque'];
            if (isset($dados['portador'])) $cheque->portador = $dados['portador'];
            if (isset($dados['observacoes'])) $cheque->observacoes = $dados['observacoes'];

            // Recalcular se necessário
            if ($recalcularJuros) {
                $taxaJuros = $dados['taxa_juros'] ?? $cheque->taxa_juros ?? $emprestimo->taxa_juros ?? 0;
                $cheque->atualizarCalculos($taxaJuros);
            }

            $cheque->save();

            // Auditoria
            self::auditar(
                'atualizar_cheque',
                $cheque,
                $oldData,
                $cheque->toArray(),
                "Cheque atualizado"
            );

            return $cheque->fresh();
        });
    }

    /**
     * Deletar cheque
     *
     * @param int $chequeId
     * @return void
     * @throws ValidationException
     */
    public function deletar(int $chequeId): void
    {
        DB::transaction(function () use ($chequeId) {
            $cheque = EmprestimoCheque::findOrFail($chequeId);
            $emprestimo = $cheque->emprestimo;

            // Validar se empréstimo não está finalizado
            if ($emprestimo->isFinalizado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível excluir cheques de empréstimos finalizados.'
                ]);
            }

            // Validar se cheque não está compensado ou devolvido
            if ($cheque->isCompensado() || $cheque->isDevolvido()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Não é possível excluir cheques que já foram compensados ou devolvidos.'
            ]);
            }

            $oldData = $cheque->toArray();

            $cheque->delete();

            // Auditoria
            self::auditar(
                'deletar_cheque',
                $cheque,
                $oldData,
                null,
                "Cheque excluído"
            );
        });
    }

    /**
     * Marcar cheque como depositado
     *
     * @param int $chequeId
     * @param string|null $observacoes
     * @return EmprestimoCheque
     */
    public function depositar(int $chequeId, ?string $observacoes = null): EmprestimoCheque
    {
        return DB::transaction(function () use ($chequeId, $observacoes) {
            $cheque = EmprestimoCheque::findOrFail($chequeId);

            if ($cheque->status !== 'aguardando') {
                throw ValidationException::withMessages([
                    'cheque' => 'Apenas cheques aguardando podem ser marcados como depositados.'
                ]);
            }

            $oldStatus = $cheque->status;

            $cheque->update([
                'status' => 'depositado',
                'data_deposito' => Carbon::now(),
                'observacoes' => ($cheque->observacoes ?? '') . 
                    ($observacoes ? "\n\n[DEPOSITADO EM " . Carbon::now()->format('d/m/Y H:i') . "]\n{$observacoes}" : ''),
            ]);

            // Auditoria
            self::auditar(
                'depositar_cheque',
                $cheque,
                ['status' => $oldStatus],
                ['status' => 'depositado', 'data_deposito' => $cheque->data_deposito],
                "Cheque depositado" . ($observacoes ? ". Observações: {$observacoes}" : '')
            );

            return $cheque->fresh();
        });
    }

    /**
     * Marcar cheque como compensado
     *
     * @param int $chequeId
     * @param string|null $observacoes
     * @return EmprestimoCheque
     */
    public function compensar(int $chequeId, ?string $observacoes = null): EmprestimoCheque
    {
        return DB::transaction(function () use ($chequeId, $observacoes) {
            $cheque = EmprestimoCheque::findOrFail($chequeId);
            $emprestimo = $cheque->emprestimo;

            if (!in_array($cheque->status, ['aguardando', 'depositado'])) {
                throw ValidationException::withMessages([
                    'cheque' => 'Apenas cheques aguardando ou depositados podem ser marcados como compensados.'
                ]);
            }

            $oldStatus = $cheque->status;

            $cheque->update([
                'status' => 'compensado',
                'data_compensacao' => Carbon::now(),
                'observacoes' => ($cheque->observacoes ?? '') . 
                    "\n\n[COMPENSADO EM " . Carbon::now()->format('d/m/Y H:i') . "]" .
                    ($observacoes ? "\n{$observacoes}" : ''),
            ]);

            // Criar movimentação de caixa (entrada com valor bruto do cheque)
            $cashService = app(\App\Modules\Cash\Services\CashService::class);
            $cashService->registrarMovimentacao([
                'operacao_id' => $emprestimo->operacao_id,
                'consultor_id' => $emprestimo->consultor_id,
                'tipo' => 'entrada',
                'valor' => $cheque->valor_cheque, // Valor bruto do cheque (sem desconto de juros)
                'data_movimentacao' => Carbon::now(),
                'descricao' => "Compensação de Cheque #{$cheque->numero_cheque} - Empréstimo #{$emprestimo->id}",
                'referencia_tipo' => 'compensacao_cheque',
                'referencia_id' => $cheque->id,
            ]);

            // Auditoria
            self::auditar(
                'compensar_cheque',
                $cheque,
                ['status' => $oldStatus],
                ['status' => 'compensado', 'data_compensacao' => $cheque->data_compensacao],
                "Cheque compensado" . ($observacoes ? ". Observações: {$observacoes}" : '')
            );

            // Verificar se pode finalizar o empréstimo (todos cheques compensados)
            $strategy = \App\Modules\Loans\Services\Strategies\LoanStrategyFactory::create($emprestimo);
            if ($strategy->podeFinalizar($emprestimo)) {
                $statusAnterior = $emprestimo->status;
                
                // Finalizar empréstimo
                $emprestimo->update([
                    'status' => 'finalizado',
                ]);

                // Auditoria da finalização
                self::auditar(
                    'finalizar_emprestimo',
                    $emprestimo,
                    ['status' => $statusAnterior],
                    ['status' => 'finalizado'],
                    "Empréstimo finalizado automaticamente - Todos os cheques foram compensados"
                );
            }

            return $cheque->fresh();
        });
    }

    /**
     * Marcar cheque como devolvido
     *
     * @param int $chequeId
     * @param string $motivoDevolucao
     * @param string|null $observacoes
     * @return EmprestimoCheque
     */
    public function devolver(int $chequeId, string $motivoDevolucao, ?string $observacoes = null): EmprestimoCheque
    {
        return DB::transaction(function () use ($chequeId, $motivoDevolucao, $observacoes) {
            $cheque = EmprestimoCheque::findOrFail($chequeId);

            if (!in_array($cheque->status, ['aguardando', 'depositado'])) {
                throw ValidationException::withMessages([
                    'cheque' => 'Apenas cheques aguardando ou depositados podem ser marcados como devolvidos.'
                ]);
            }

            $oldStatus = $cheque->status;

            $cheque->update([
                'status' => 'devolvido',
                'data_devolucao' => Carbon::now(),
                'motivo_devolucao' => $motivoDevolucao,
                'observacoes' => ($cheque->observacoes ?? '') . 
                    "\n\n[DEVOLVIDO EM " . Carbon::now()->format('d/m/Y H:i') . "]\n" .
                    "Motivo: {$motivoDevolucao}" .
                    ($observacoes ? "\n{$observacoes}" : ''),
            ]);

            // Auditoria
            self::auditar(
                'devolver_cheque',
                $cheque,
                ['status' => $oldStatus],
                [
                    'status' => 'devolvido',
                    'data_devolucao' => $cheque->data_devolucao,
                    'motivo_devolucao' => $motivoDevolucao,
                ],
                "Cheque devolvido. Motivo: {$motivoDevolucao}"
            );

            return $cheque->fresh();
        });
    }

    /**
     * Recalcular todos os cheques de um empréstimo
     * Útil quando a taxa de juros é alterada
     *
     * @param Emprestimo $emprestimo
     * @param float $taxaJuros
     * @return void
     */
    public function recalcularTodosCheques(Emprestimo $emprestimo, float $taxaJuros): void
    {
        DB::transaction(function () use ($emprestimo, $taxaJuros) {
            foreach ($emprestimo->cheques as $cheque) {
                // Apenas recalcular se ainda não foi compensado ou devolvido
                if (in_array($cheque->status, ['aguardando', 'depositado'])) {
                    $cheque->atualizarCalculos($taxaJuros);
                }
            }
        });
    }

    /**
     * Cliente pagou cheque devolvido em dinheiro
     *
     * @param int $chequeId
     * @param array $dados
     * @return EmprestimoCheque
     * @throws ValidationException
     */
    public function pagarEmDinheiro(int $chequeId, array $dados): EmprestimoCheque
    {
        return DB::transaction(function () use ($chequeId, $dados) {
            $cheque = EmprestimoCheque::findOrFail($chequeId);
            $emprestimo = $cheque->emprestimo;

            // Validar se cheque está devolvido
            if ($cheque->status !== 'devolvido') {
                throw ValidationException::withMessages([
                    'cheque' => 'Apenas cheques devolvidos podem ser pagos em dinheiro.'
                ]);
            }

            // Calcular juros de atraso
            $dadosJuros = $this->calcularJurosAtraso($cheque, $dados);
            $valorTotal = $cheque->valor_cheque + $dadosJuros['valor_juros'];
            $metodoPagamento = $this->getMetodoPagamentoLabel($dados['metodo_pagamento'] ?? 'dinheiro');

            // Criar movimentação de caixa (entrada)
            $dadosMovimentacao = [
                'operacao_id' => $emprestimo->operacao_id,
                'consultor_id' => $emprestimo->consultor_id,
                'tipo' => 'entrada',
                'valor' => $valorTotal,
                'data_movimentacao' => Carbon::parse($dados['data_pagamento'] ?? Carbon::now()),
                'descricao' => "Pagamento - Cheque devolvido #{$cheque->numero_cheque} - {$metodoPagamento} - Empréstimo #{$emprestimo->id}" .
                    ($dadosJuros['valor_juros'] > 0 ? " (Juros: R$ " . number_format($dadosJuros['valor_juros'], 2, ',', '.') . ")" : ''),
                'referencia_tipo' => 'pagamento_cheque_devolvido',
                'referencia_id' => $cheque->id,
            ];
            if (!empty($dados['comprovante_path'])) {
                $dadosMovimentacao['comprovante_path'] = $dados['comprovante_path'];
            }
            $cashService = app(\App\Modules\Cash\Services\CashService::class);
            $cashService->registrarMovimentacao($dadosMovimentacao);

            // Atualizar cheque
            $cheque->update([
                'status' => 'compensado', // Marcar como compensado (foi pago)
                'data_compensacao' => Carbon::parse($dados['data_pagamento'] ?? Carbon::now()),
                'observacoes' => ($cheque->observacoes ?? '') . 
                    "\n\n[PAGAMENTO REGISTRADO EM " . Carbon::now()->format('d/m/Y H:i') . "]\n" .
                    "Método: {$metodoPagamento}\n" .
                    "Valor pago: R$ " . number_format($valorTotal, 2, ',', '.') .
                    ($dadosJuros['valor_juros'] > 0 ? "\nJuros de atraso: R$ " . number_format($dadosJuros['valor_juros'], 2, ',', '.') : '') .
                    ($dados['observacoes'] ? "\n{$dados['observacoes']}" : ''),
            ]);

            // Auditoria
            self::auditar(
                'pagar_cheque_devolvido_dinheiro',
                $cheque,
                ['status' => 'devolvido'],
                [
                    'status' => 'compensado',
                    'data_compensacao' => $cheque->data_compensacao,
                    'valor_total_pago' => $valorTotal,
                    'juros_aplicados' => $dadosJuros['valor_juros'],
                ],
                "Cheque devolvido pago em dinheiro. Valor: R$ " . number_format($valorTotal, 2, ',', '.')
            );

            // Verificar se pode finalizar o empréstimo
            $strategy = \App\Modules\Loans\Services\Strategies\LoanStrategyFactory::create($emprestimo);
            if ($strategy->podeFinalizar($emprestimo)) {
                $statusAnterior = $emprestimo->status;
                $emprestimo->update(['status' => 'finalizado']);
                
                self::auditar(
                    'finalizar_emprestimo',
                    $emprestimo,
                    ['status' => $statusAnterior],
                    ['status' => 'finalizado'],
                    "Empréstimo finalizado automaticamente - Todos os cheques foram compensados/pagos"
                );
            }

            return $cheque->fresh();
        });
    }

    /**
     * Substituir cheque devolvido por novo cheque
     *
     * @param int $chequeId
     * @param array $dadosNovoCheque
     * @return EmprestimoCheque
     * @throws ValidationException
     */
    public function substituirPorNovoCheque(int $chequeId, array $dadosNovoCheque): EmprestimoCheque
    {
        return DB::transaction(function () use ($chequeId, $dadosNovoCheque) {
            $chequeAntigo = EmprestimoCheque::findOrFail($chequeId);
            $emprestimo = $chequeAntigo->emprestimo;

            // Validar se cheque está devolvido
            if ($chequeAntigo->status !== 'devolvido') {
                throw ValidationException::withMessages([
                    'cheque' => 'Apenas cheques devolvidos podem ser substituídos.'
                ]);
            }

            // Validar se novo cheque não está vencido
            $dataVencimento = Carbon::parse($dadosNovoCheque['data_vencimento']);
            if ($dataVencimento->isPast()) {
                throw ValidationException::withMessages([
                    'data_vencimento' => 'Não é possível cadastrar cheque com data de vencimento no passado.'
                ]);
            }

            // Calcular dias até vencimento
            $diasAteVencimento = Carbon::today()->diffInDays($dataVencimento);

            // Calcular juros (usar taxa do novo cheque, do empréstimo ou operação)
            $taxaJuros = $dadosNovoCheque['taxa_juros'] ?? $emprestimo->taxa_juros ?? 0;
            if ($taxaJuros <= 0) {
                $operacao = $emprestimo->operacao;
                $taxaJuros = $operacao->taxa_juros_atraso ?? 0;
            }

            $valorCheque = $dadosNovoCheque['valor_cheque'];
            $valorJuros = ($valorCheque * $taxaJuros * $diasAteVencimento) / (100 * 30);
            $valorLiquido = $valorCheque - $valorJuros;

            // Criar novo cheque
            $novoCheque = EmprestimoCheque::create([
                'emprestimo_id' => $emprestimo->id,
                'banco' => $dadosNovoCheque['banco'],
                'agencia' => $dadosNovoCheque['agencia'],
                'conta' => $dadosNovoCheque['conta'],
                'numero_cheque' => $dadosNovoCheque['numero_cheque'],
                'data_vencimento' => $dataVencimento,
                'valor_cheque' => $valorCheque,
                'dias_ate_vencimento' => $diasAteVencimento,
                'taxa_juros' => $taxaJuros,
                'valor_juros' => round($valorJuros, 2),
                'valor_liquido' => round($valorLiquido, 2),
                'portador' => $dadosNovoCheque['portador'] ?? null,
                'status' => 'aguardando',
                'observacoes' => ($dadosNovoCheque['observacoes'] ?? '') . 
                    "\n\n[SUBSTITUIÇÃO DO CHEQUE DEVOLVIDO #{$chequeAntigo->numero_cheque} EM " . Carbon::now()->format('d/m/Y H:i') . "]",
                'empresa_id' => $emprestimo->empresa_id,
            ]);

            // Atualizar cheque antigo com referência ao novo
            $chequeAntigo->update([
                'observacoes' => ($chequeAntigo->observacoes ?? '') . 
                    "\n\n[SUBSTITUÍDO POR NOVO CHEQUE #{$novoCheque->numero_cheque} EM " . Carbon::now()->format('d/m/Y H:i') . "]",
            ]);

            // Auditoria
            self::auditar(
                'substituir_cheque_devolvido',
                $novoCheque,
                null,
                $novoCheque->toArray(),
                "Novo cheque criado para substituir cheque devolvido #{$chequeAntigo->id}"
            );

            return $novoCheque->fresh();
        });
    }

    /**
     * Calcular juros de atraso para cheque devolvido
     *
     * @param EmprestimoCheque $cheque
     * @param array $dados
     * @return array
     */
    private function calcularJurosAtraso(EmprestimoCheque $cheque, array $dados): array
    {
        $resultado = [
            'tipo_juros' => null,
            'taxa_juros_aplicada' => null,
            'valor_juros' => 0,
        ];

        $tipoJuros = $dados['tipo_juros'] ?? 'nenhum';

        // Se não há juros, retorna vazio
        if ($tipoJuros === 'nenhum' || empty($tipoJuros)) {
            return $resultado;
        }

        // Calcular dias de atraso (desde vencimento até data de pagamento)
        $dataVencimento = $cheque->data_vencimento;
        $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::now());
        $diasAtraso = max(0, $dataVencimento->diffInDays($dataPagamento, false)); // false = não absoluto (pode ser negativo)

        if ($diasAtraso <= 0) {
            return $resultado; // Não está atrasado
        }

        $valorCheque = $cheque->valor_cheque;
        $operacao = $cheque->emprestimo->operacao;

        switch ($tipoJuros) {
            case 'automatico':
                // Usa a taxa configurada na operação
                if ($operacao->taxa_juros_atraso > 0) {
                    $taxa = $operacao->taxa_juros_atraso;
                    $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';

                    if ($tipoCalculo === 'por_dia') {
                        $valorJuros = $valorCheque * ($taxa / 100) * $diasAtraso;
                    } else { // por_mes
                        $valorJuros = $valorCheque * ($taxa / 100) * ($diasAtraso / 30);
                    }

                    $resultado = [
                        'tipo_juros' => 'automatico',
                        'taxa_juros_aplicada' => $taxa,
                        'valor_juros' => round($valorJuros, 2),
                    ];
                }
                break;

            case 'manual':
                // Usa a taxa informada pelo consultor
                $taxa = $dados['taxa_juros_manual'] ?? 0;
                if ($taxa > 0) {
                    $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
                    if ($tipoCalculo === 'por_dia') {
                        $valorJuros = $valorCheque * ($taxa / 100) * $diasAtraso;
                    } else {
                        $valorJuros = $valorCheque * ($taxa / 100) * ($diasAtraso / 30);
                    }

                    $resultado = [
                        'tipo_juros' => 'manual',
                        'taxa_juros_aplicada' => $taxa,
                        'valor_juros' => round($valorJuros, 2),
                    ];
                }
                break;

            case 'fixo':
                // Usa o valor fixo informado (valor total - valor do cheque = juros)
                $valorTotalFixo = $dados['valor_total_fixo'] ?? 0;
                if ($valorTotalFixo > 0) {
                    $valorJuros = $valorTotalFixo - $valorCheque;
                    $resultado = [
                        'tipo_juros' => 'fixo',
                        'taxa_juros_aplicada' => null,
                        'valor_juros' => round(max(0, $valorJuros), 2), // Não pode ser negativo
                    ];
                }
                break;
        }

        return $resultado;
    }

    /**
     * Retorna o label do método de pagamento para exibição
     */
    private function getMetodoPagamentoLabel(string $metodo): string
    {
        return match ($metodo) {
            'dinheiro' => 'Dinheiro',
            'pix' => 'PIX',
            'transferencia' => 'Transferência',
            'cartao_debito' => 'Cartão de débito',
            'cartao_credito' => 'Cartão de crédito',
            'boleto' => 'Boleto',
            'outro' => 'Outro',
            default => ucfirst($metodo),
        };
    }
}
