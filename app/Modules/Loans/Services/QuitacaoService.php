<?php

namespace App\Modules\Loans\Services;

use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Services\NotificacaoService;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\SolicitacaoQuitacao;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuitacaoService
{
    use Auditable;

    public function __construct(
        protected PagamentoService $pagamentoService,
        protected CashService $cashService
    ) {
    }

    /**
     * Retorna o saldo devedor do empréstimo (soma do que falta em parcelas não quitadas).
     */
    public function getSaldoDevedor(Emprestimo $emprestimo): float
    {
        $parcelas = $emprestimo->parcelas()
            ->whereNotIn('status', ['paga', 'quitada_garantia'])
            ->get();

        $saldo = 0;
        foreach ($parcelas as $parcela) {
            $restante = (float) $parcela->valor - (float) ($parcela->valor_pago ?? 0);
            if ($restante > 0) {
                $saldo += $restante;
            }
        }
        return round($saldo, 2);
    }

    /**
     * Retorna as parcelas em aberto ordenadas por vencimento.
     *
     * @return \Illuminate\Support\Collection<int, Parcela>
     */
    public function getParcelasEmAberto(Emprestimo $emprestimo)
    {
        return $emprestimo->parcelas()
            ->whereNotIn('status', ['paga', 'quitada_garantia'])
            ->orderBy('data_vencimento')
            ->get();
    }

    /**
     * Executa a quitação do empréstimo: distribui o valor entre as parcelas em aberto,
     * cria pagamentos, uma entrada de caixa única e finaliza o empréstimo se todas quitadas.
     * Quando quitacao_com_desconto = true, todas as parcelas que recebem pagamento são
     * marcadas como 'paga' (inclusive a última com valor parcial), para o empréstimo finalizar.
     *
     * @param array $dados valor, data_pagamento, metodo, consultor_id, comprovante_path?, observacoes?, quitacao_com_desconto?
     */
    public function executarQuitacao(Emprestimo $emprestimo, array $dados): void
    {
        $valor = (float) $dados['valor'];
        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'O valor deve ser maior que zero.']);
        }

        $parcelas = $this->getParcelasEmAberto($emprestimo);
        if ($parcelas->isEmpty()) {
            throw ValidationException::withMessages(['emprestimo' => 'Não há parcelas em aberto para quitar.']);
        }

        $saldoDevedor = $this->getSaldoDevedor($emprestimo);
        if ($valor > $saldoDevedor) {
            throw ValidationException::withMessages(['valor' => 'O valor informado é maior que o saldo devedor (R$ ' . number_format($saldoDevedor, 2, ',', '.') . ').']);
        }

        $quitacaoComDesconto = !empty($dados['quitacao_com_desconto']);

        DB::transaction(function () use ($emprestimo, $dados, $valor, $parcelas, $quitacaoComDesconto) {
            $restante = $valor;
            $dataPagamento = isset($dados['data_pagamento']) ? Carbon::parse($dados['data_pagamento']) : Carbon::today();
            $consultorId = (int) $dados['consultor_id'];
            $metodo = $dados['metodo'] ?? 'dinheiro';
            $comprovantePath = isset($dados['comprovante_path']) && trim((string) $dados['comprovante_path']) !== ''
                ? trim((string) $dados['comprovante_path'])
                : null;
            $observacoes = $dados['observacoes'] ?? null;
            $primeiroPagamento = null;
            $valorTotalMovimentacao = 0;

            $parcelasProcessadas = [];

            foreach ($parcelas as $parcela) {
                if ($restante <= 0) {
                    break;
                }
                $valorParcela = (float) $parcela->valor;
                $pagoParcela = (float) ($parcela->valor_pago ?? 0);
                $faltaParcela = $valorParcela - $pagoParcela;
                if ($faltaParcela <= 0) {
                    continue;
                }
                $valorNestePagamento = min($faltaParcela, $restante);
                $restante -= $valorNestePagamento;
                $valorTotalMovimentacao += $valorNestePagamento;

                $pagamento = Pagamento::create([
                    'parcela_id' => $parcela->id,
                    'consultor_id' => $consultorId,
                    'valor' => $valorNestePagamento,
                    'metodo' => $metodo,
                    'data_pagamento' => $dataPagamento,
                    'comprovante_path' => $comprovantePath,
                    'observacoes' => $observacoes,
                    'tipo_juros' => null,
                    'taxa_juros_aplicada' => null,
                    'valor_juros' => 0,
                ]);
                if ($primeiroPagamento === null) {
                    $primeiroPagamento = $pagamento;
                }

                $novoValorPago = $pagoParcela + $valorNestePagamento;
                $marcarComoPaga = $novoValorPago >= $valorParcela || $quitacaoComDesconto;
                $parcela->update([
                    'valor_pago' => $novoValorPago,
                    'data_pagamento' => $dataPagamento,
                    'status' => $marcarComoPaga ? 'paga' : 'pendente',
                    'dias_atraso' => 0,
                ]);
                $parcelasProcessadas[] = $parcela->id;
            }

            // Quitação com desconto: marcar TODAS as parcelas restantes como 'paga'
            if ($quitacaoComDesconto) {
                foreach ($parcelas as $parcela) {
                    if (in_array($parcela->id, $parcelasProcessadas)) {
                        continue;
                    }
                    if ($parcela->status === 'paga' || $parcela->status === 'quitada_garantia') {
                        continue;
                    }
                    $parcela->update([
                        'data_pagamento' => $dataPagamento,
                        'status' => 'paga',
                        'dias_atraso' => 0,
                    ]);
                }
            }

            if ($valorTotalMovimentacao > 0 && $primeiroPagamento) {
                $this->cashService->registrarMovimentacao([
                    'operacao_id' => $emprestimo->operacao_id,
                    'consultor_id' => $consultorId,
                    'pagamento_id' => $primeiroPagamento->id,
                    'tipo' => 'entrada',
                    'origem' => 'automatica',
                    'valor' => $valorTotalMovimentacao,
                    'descricao' => 'Quitação empréstimo #' . $emprestimo->id,
                    'data_movimentacao' => $dataPagamento,
                    'referencia_tipo' => 'quitacao_emprestimo',
                    'referencia_id' => $emprestimo->id,
                ]);
            }

            $this->pagamentoService->verificarEFinalizarEmprestimo($emprestimo->fresh());
        });
    }

    /**
     * Solicita quitação com desconto (cria solicitação pendente de aprovação).
     * Valida que valor_solicitado < saldo_devedor, valor_solicitado >= valor emprestado (principal)
     * e que motivo_desconto foi informado.
     */
    public function solicitarQuitacaoComDesconto(Emprestimo $emprestimo, array $dados): SolicitacaoQuitacao
    {
        $saldoDevedor = $this->getSaldoDevedor($emprestimo);
        $valorSolicitado = (float) $dados['valor_solicitado'];
        $valorEmprestado = (float) $emprestimo->valor_total;

        if ($valorSolicitado >= $saldoDevedor) {
            throw ValidationException::withMessages([
                'valor_solicitado' => 'Para solicitar com desconto, o valor deve ser menor que o saldo devedor (R$ ' . number_format($saldoDevedor, 2, ',', '.') . ').',
            ]);
        }
        if ($valorSolicitado < $valorEmprestado) {
            throw ValidationException::withMessages([
                'valor_solicitado' => 'O valor não pode ser menor que o valor emprestado (R$ ' . number_format($valorEmprestado, 2, ',', '.') . ').',
            ]);
        }
        if (empty(trim($dados['motivo_desconto'] ?? ''))) {
            throw ValidationException::withMessages(['motivo_desconto' => 'O motivo do desconto é obrigatório.']);
        }

        $user = auth()->user();
        $empresaId = $emprestimo->empresa_id ?? $user->empresa_id;

        return DB::transaction(function () use ($emprestimo, $dados, $saldoDevedor, $valorSolicitado, $user, $empresaId) {
            $solicitacao = SolicitacaoQuitacao::create([
                'emprestimo_id' => $emprestimo->id,
                'solicitante_id' => $user->id,
                'saldo_devedor' => $saldoDevedor,
                'valor_solicitado' => $valorSolicitado,
                'metodo' => $dados['metodo'] ?? 'dinheiro',
                'data_pagamento' => $dados['data_pagamento'] ?? now()->format('Y-m-d'),
                'comprovante_path' => $dados['comprovante_path'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'motivo_desconto' => $dados['motivo_desconto'],
                'status' => 'pendente',
                'empresa_id' => $empresaId,
            ]);
            self::auditar('solicitar_quitacao_desconto', $solicitacao, null, $solicitacao->toArray());

            $cliente = $emprestimo->cliente;
            $clienteNome = $cliente ? $cliente->nome : 'Cliente';
            $mensagem = "Quitação com desconto do empréstimo #{$emprestimo->id} ({$clienteNome}): R$ " . number_format($valorSolicitado, 2, ',', '.') . " (saldo R$ " . number_format($saldoDevedor, 2, ',', '.') . "). Solicitado por {$user->name}.";
            $notificacaoService = app(NotificacaoService::class);
            $operacaoId = (int) $emprestimo->operacao_id;
            $dadosNotif = [
                'tipo' => 'quitacao_desconto_pendente',
                'titulo' => 'Quitação com desconto – aguardando aprovação',
                'mensagem' => $mensagem,
                'url' => route('quitacao.pendentes'),
                'dados' => ['solicitacao_id' => $solicitacao->id, 'emprestimo_id' => $emprestimo->id],
            ];
            $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
            $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);

            return $solicitacao;
        });
    }

    /**
     * Aprova uma solicitação de quitação com desconto e executa a quitação.
     * Apenas gestor ou administrador.
     * Garante que o valor não seja menor que o valor emprestado (principal).
     */
    public function aprovarSolicitacao(SolicitacaoQuitacao $solicitacao, int $aprovadorId): void
    {
        if (!$solicitacao->isPendente()) {
            throw ValidationException::withMessages(['solicitacao' => 'Esta solicitação já foi processada.']);
        }

        $emprestimo = $solicitacao->emprestimo;
        if (!$emprestimo->isAtivo()) {
            throw ValidationException::withMessages(['emprestimo' => 'O empréstimo não está ativo.']);
        }

        $valorEmprestado = (float) $emprestimo->valor_total;
        if ((float) $solicitacao->valor_solicitado < $valorEmprestado) {
            throw ValidationException::withMessages([
                'valor_solicitado' => 'O valor aprovado não pode ser menor que o valor emprestado (R$ ' . number_format($valorEmprestado, 2, ',', '.') . ').',
            ]);
        }

        DB::transaction(function () use ($solicitacao, $aprovadorId) {
            $solicitacao->update([
                'status' => 'aprovado',
                'aprovado_por' => $aprovadorId,
                'aprovado_em' => now(),
                'motivo_rejeicao' => null,
            ]);
            self::auditar('aprovar_quitacao_desconto', $solicitacao, ['status' => 'pendente'], ['status' => 'aprovado']);

            $this->executarQuitacao($solicitacao->emprestimo, [
                'valor' => $solicitacao->valor_solicitado,
                'data_pagamento' => $solicitacao->data_pagamento->format('Y-m-d'),
                'metodo' => $solicitacao->metodo,
                'consultor_id' => $solicitacao->solicitante_id,
                'comprovante_path' => $solicitacao->comprovante_path,
                'observacoes' => 'Quitação com desconto aprovada. ' . ($solicitacao->observacoes ?? ''),
                'quitacao_com_desconto' => true,
            ]);

            $notificacaoService = app(NotificacaoService::class);
            $notificacaoService->criar([
                'user_id' => $solicitacao->solicitante_id,
                'tipo' => 'quitacao_desconto_aprovada',
                'titulo' => 'Quitação com desconto aprovada',
                'mensagem' => "Sua solicitação de quitação do empréstimo #{$solicitacao->emprestimo_id} foi aprovada. O empréstimo foi quitado.",
                'url' => route('emprestimos.show', $solicitacao->emprestimo_id),
                'dados' => ['solicitacao_id' => $solicitacao->id, 'emprestimo_id' => $solicitacao->emprestimo_id],
            ]);
        });
    }

    /**
     * Rejeita uma solicitação de quitação com desconto.
     */
    public function rejeitarSolicitacao(SolicitacaoQuitacao $solicitacao, int $aprovadorId, string $motivoRejeicao): void
    {
        if (!$solicitacao->isPendente()) {
            throw ValidationException::withMessages(['solicitacao' => 'Esta solicitação já foi processada.']);
        }

        $solicitacao->update([
            'status' => 'rejeitado',
            'aprovado_por' => $aprovadorId,
            'aprovado_em' => now(),
            'motivo_rejeicao' => $motivoRejeicao,
        ]);
        self::auditar('rejeitar_quitacao_desconto', $solicitacao, ['status' => 'pendente'], ['status' => 'rejeitado']);

        $notificacaoService = app(NotificacaoService::class);
        $notificacaoService->criar([
            'user_id' => $solicitacao->solicitante_id,
            'tipo' => 'quitacao_desconto_rejeitada',
            'titulo' => 'Quitação com desconto rejeitada',
            'mensagem' => "Sua solicitação de quitação do empréstimo #{$solicitacao->emprestimo_id} foi rejeitada. Motivo: " . \Str::limit($motivoRejeicao, 100),
            'url' => route('emprestimos.show', $solicitacao->emprestimo_id),
            'dados' => ['solicitacao_id' => $solicitacao->id, 'emprestimo_id' => $solicitacao->emprestimo_id],
        ]);
    }
}
