<?php

namespace App\Modules\Loans\Services;

use App\Modules\Core\Models\OperationClient;
use App\Modules\Core\Services\NotificacaoService;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Models\User;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\Strategies\LoanStrategyFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmprestimoService
{
    use Auditable;

    /**
     * Criar novo empréstimo
     *
     * @param array $dados
     * @return Emprestimo
     * @throws ValidationException
     */
    public function criar(array $dados): Emprestimo
    {
        return DB::transaction(function () use ($dados) {
            // Garantir que existe vínculo entre cliente e operação
            $this->garantirVinculoClienteOperacao(
                $dados['cliente_id'],
                $dados['operacao_id'],
                $dados['consultor_id'] ?? null
            );

            // Buscar operação e empresa para verificar configurações
            // Usar withoutGlobalScope para garantir que encontre a operação mesmo se não pertencer à empresa atual
            $operacao = \App\Modules\Core\Models\Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->with('empresa')
                ->find($dados['operacao_id']);
            
            if (!$operacao) {
                throw new ValidationException(['operacao_id' => 'Operação não encontrada.']);
            }
            
            // Verificar se operação requer aprovação
            $requerAprovacao = $operacao->requerAprovacao();
            
            // Verificar valor de aprovação automática da operação
            $valorAprovacaoAutomatica = $operacao->getValorAprovacaoAutomatica();

            // Verificar se valor está dentro do limite de aprovação automática
            $aprovacaoAutomatica = false;
            if ($valorAprovacaoAutomatica > 0 && $dados['valor_total'] <= $valorAprovacaoAutomatica) {
                // Valor está dentro do limite de aprovação automática
                // Ignora validações de dívida ativa e limite de crédito
                $aprovacaoAutomatica = true;
            }

            // Definir status inicial
            $status = 'draft';
            
            // Se empresa não requer aprovação, aprovar automaticamente
            if (!$requerAprovacao) {
                $status = 'aprovado';
            } elseif ($aprovacaoAutomatica) {
                // Aprovado automaticamente (ignora outras validações)
                $status = 'aprovado';
            } else {
                // Aplicar validações normais
                $temDividaAtiva = $this->temDividaAtiva(
                    $dados['cliente_id'],
                    $dados['operacao_id']
                );

                $limiteExcedido = $this->limiteExcedido(
                    $dados['cliente_id'],
                    $dados['operacao_id'],
                    $dados['valor_total']
                );

                if ($temDividaAtiva || $limiteExcedido) {
                    $status = 'pendente';
                } else {
                    $status = 'aprovado';
                }
            }

            // Obter empresa_id da operação (já temos $operacao carregado acima)
            $empresaId = $operacao->empresa_id ?? (auth()->check() && !auth()->user()->isSuperAdmin() ? auth()->user()->empresa_id : null);

            // Empréstimo retroativo: gestor/admin → ativo direto; consultor → aguardando aceite
            $isRetroativo = !empty($dados['is_retroativo']);
            $solicitarAceiteRetroativo = !empty($dados['solicitar_aceite_retroativo']);
            if ($isRetroativo) {
                $status = $solicitarAceiteRetroativo ? 'aguardando_aceite_retroativo' : 'ativo';
            }

            // Criar empréstimo (criado_por = quem estava logado ao criar)
            $emprestimo = Emprestimo::create([
                'operacao_id' => $dados['operacao_id'],
                'cliente_id' => $dados['cliente_id'],
                'consultor_id' => $dados['consultor_id'],
                'criado_por_user_id' => $dados['criado_por_user_id'] ?? (auth()->check() ? auth()->id() : null),
                'valor_total' => $dados['valor_total'],
                'numero_parcelas' => $dados['numero_parcelas'],
                'frequencia' => $dados['frequencia'],
                'data_inicio' => $dados['data_inicio'],
                'taxa_juros' => $dados['taxa_juros'] ?? 0,
                'tipo' => $dados['tipo'] ?? 'dinheiro',
                'status' => $status,
                'observacoes' => $dados['observacoes'] ?? null,
                'is_retroativo' => $isRetroativo,
                'empresa_id' => $empresaId,
            ]);

            // Usar Strategy Pattern para gerar estrutura de pagamento
            // Para troca_cheque: não gera parcelas (cheques são cadastrados agora)
            // Para outros tipos: gera parcelas normalmente
            $strategy = LoanStrategyFactory::create($emprestimo);
            $strategy->gerarEstruturaPagamento($emprestimo);

            // Se for troca_cheque, criar os cheques agora
            if ($emprestimo->tipo === 'troca_cheque' && !empty($dados['cheques'])) {
                $chequeService = app(\App\Modules\Loans\Services\ChequeService::class);
                foreach ($dados['cheques'] as $chequeData) {
                    // Usar taxa de juros do cheque ou do empréstimo
                    $chequeData['taxa_juros'] = $chequeData['taxa_juros'] ?? $emprestimo->taxa_juros ?? null;
                    $chequeService->criar($emprestimo->id, $chequeData);
                }
                
                // Recalcular valor_total baseado nos cheques
                $emprestimo->refresh();
                $emprestimo->load('cheques');
                $valorTotalCheques = $emprestimo->cheques->sum('valor_cheque');
                $emprestimo->update(['valor_total' => $valorTotalCheques]);
            }

            // Consultor criou retroativo: criar solicitação de aceite para gestor/admin
            if ($isRetroativo && $solicitarAceiteRetroativo) {
                \App\Modules\Loans\Models\SolicitacaoEmprestimoRetroativo::create([
                    'emprestimo_id' => $emprestimo->id,
                    'solicitante_id' => $emprestimo->consultor_id,
                    'status' => 'aguardando',
                    'empresa_id' => $empresaId,
                ]);
                $notificacaoService = app(NotificacaoService::class);
                $operacaoId = (int) $emprestimo->operacao_id;
                $dadosNotif = [
                    'tipo' => 'emprestimo_retroativo_aguardando_aceite',
                    'titulo' => 'Empréstimo retroativo aguardando aceite',
                    'mensagem' => 'Empréstimo #' . $emprestimo->id . ' (retroativo) criado por consultor aguardando sua aprovação.',
                    'url' => route('emprestimos.retroativo.pendentes'),
                ];
                $notificacaoService->criarParaGestoresDaOperacao($operacaoId, $dadosNotif);
            }

            // Se aprovado, verificar se precisa criar liberação (retroativo não cria liberação)
            if ($status === 'aprovado' && !$isRetroativo) {
                $requerLiberacao = $operacao->requerLiberacao();
                
                if ($requerLiberacao) {
                    // Criar registro de liberação pendente (gestor precisa liberar dinheiro)
                    $this->criarLiberacaoPendente($emprestimo);
                    
                    // Status permanece 'aprovado' até o dinheiro ser liberado e pago ao cliente
                    // Depois muda para 'ativo'
                } else {
                    // Não requer liberação - liberar automaticamente
                    $liberacaoService = app(\App\Modules\Loans\Services\LiberacaoService::class);
                    $liberacao = $this->criarLiberacaoPendente($emprestimo);
                    
                    // Liberar automaticamente (sem necessidade de gestor)
                    // Usar o consultor como gestor para liberação automática
                    $liberacaoService->liberar($liberacao->id, $emprestimo->consultor_id, 'Liberação automática - empresa não requer aprovação de gestor');
                    
                    // Status permanece 'aprovado' até o consultor confirmar pagamento ao cliente
                    // Depois muda para 'ativo'
                }
            }

            // Retroativo criado pelo gestor (status já 'ativo'): criar liberação como pago_ao_cliente
            // para permitir registro de pagamento de parcelas (sem movimentação de caixa).
            if ($status === 'ativo' && $isRetroativo) {
                $liberacaoService = app(\App\Modules\Loans\Services\LiberacaoService::class);
                $liberacaoService->criarParaRetroativo($emprestimo, auth()->id() ?? $emprestimo->consultor_id);
                // Marcar como atrasadas as parcelas já vencidas (não depender do cron)
                app(\App\Modules\Loans\Services\ParcelaService::class)->marcarAtrasadasDoEmprestimo($emprestimo);
            }

            // Auditoria
            $mensagemAuditoria = null;
            if ($aprovacaoAutomatica) {
                $mensagemAuditoria = "Empréstimo aprovado automaticamente (valor dentro do limite de aprovação automática da operação)";
            } elseif ($status === 'pendente') {
                $mensagemAuditoria = 'Empréstimo criado como pendente (dívida ativa ou limite excedido)';
            }
            
            self::auditar('criar_emprestimo', $emprestimo, null, $emprestimo->toArray(), $mensagemAuditoria);

            // Notificações
            $notificacaoService = app(NotificacaoService::class);
            // Carregar cliente se ainda não estiver carregado
            if (!$emprestimo->relationLoaded('cliente')) {
                $emprestimo->load('cliente');
            }
            $cliente = $emprestimo->cliente;
            
            if ($cliente) {
                $operacaoId = (int) $emprestimo->operacao_id;
                if ($status === 'pendente') {
                    $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, [
                        'tipo' => 'emprestimo_pendente',
                        'titulo' => 'Novo Empréstimo Pendente',
                        'mensagem' => "Empréstimo de R$ " . number_format($emprestimo->valor_total, 2, ',', '.') . " para {$cliente->nome} aguardando aprovação",
                        'url' => route('aprovacoes.index'),
                        'dados' => ['emprestimo_id' => $emprestimo->id],
                    ]);
                } elseif ($status === 'aprovado') {
                    $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, [
                        'tipo' => 'emprestimo_aprovado',
                        'titulo' => 'Empréstimo Aprovado - Liberação Pendente',
                        'mensagem' => "Empréstimo de R$ " . number_format($emprestimo->valor_total, 2, ',', '.') . " para {$cliente->nome} aprovado e aguardando liberação",
                        'url' => route('liberacoes.index'),
                        'dados' => ['emprestimo_id' => $emprestimo->id],
                    ]);
                }
            }

            return $emprestimo->fresh();
        });
    }

    /**
     * Renovar empréstimo criando um novo e marcando o atual como finalizado.
     * Fluxo pensado especialmente para empréstimos mensais com 1 parcela,
     * onde o cliente paga apenas os juros e renova o prazo do principal.
     *
     * @param int $emprestimoId
     * @return Emprestimo Novo empréstimo gerado pela renovação
     * @throws ValidationException
     */
    /**
     * Verificar se os juros do empréstimo já foram pagos
     *
     * @param Emprestimo $emprestimo
     * @return bool
     */
    public function jurosJaForamPagos(Emprestimo $emprestimo): bool
    {
        return $emprestimo->jurosJaForamPagos();
    }

    public function renovar(
        int $emprestimoId,
        bool $registrarPagamentoAutomatico = true,
        ?string $tipoJurosRenovacao = 'automatico',
        ?float $taxaJurosManual = null,
        ?float $valorJurosFixo = null,
        ?float $valorTotalPago = null,
        ?string $metodoPagamento = 'dinheiro',
        $dataPagamento = null,
        bool $solicitacaoAprovada = false,
        ?int $consultorId = null
    ): Emprestimo {
        return DB::transaction(function () use ($emprestimoId, $registrarPagamentoAutomatico, $tipoJurosRenovacao, $taxaJurosManual, $valorJurosFixo, $valorTotalPago, $metodoPagamento, $dataPagamento, $solicitacaoAprovada, $consultorId) {
            $emprestimo = Emprestimo::with(['parcelas', 'garantias.anexos', 'operacao'])->findOrFail($emprestimoId);

            // Apenas empréstimos ativos podem ser renovados por este fluxo
            if (!$emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos ativos podem ser renovados.',
                ]);
            }

            // Fluxo focado em empréstimos com 1 parcela (qualquer frequência)
            if ((int) $emprestimo->numero_parcelas !== 1) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Este fluxo de renovação (pagar apenas juros) é permitido apenas para empréstimos com 1 parcela.',
                ]);
            }

            // Verificar se os juros já foram pagos
            if ($this->jurosJaForamPagos($emprestimo)) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Os juros deste empréstimo já foram pagos. Não é necessário renovar.',
                ]);
            }

            $parcela = $emprestimo->parcelas->first();
            if (!$parcela) {
                throw ValidationException::withMessages([
                    'parcela' => 'Parcela não encontrada para este empréstimo.',
                ]);
            }

            // Empréstimo mensal: pode renovar antes do vencimento (parcela pendente). Demais frequências: só quando atrasada.
            $ehMensal = $emprestimo->frequencia === 'mensal';
            if (!$ehMensal && !$parcela->isAtrasada()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'A renovação só é permitida quando a parcela está em vencimento ou atrasada.',
                ]);
            }

            $valorJuros = 0.0;
            $saldoRestante = (float) $emprestimo->valor_total; // usado só no fluxo com abate

            // --- Fluxo RENOVAÇÃO COM ABATE: valor total pago abate saldo; novo empréstimo = saldo restante; alocação "juros primeiro" ---
            if ($valorTotalPago !== null && $valorTotalPago > 0) {
                $valorParcelaTotal = (float) $parcela->valor;
                $valorPrincipalParcela = $parcela->valor_amortizacao !== null && (float) $parcela->valor_amortizacao > 0
                    ? (float) $parcela->valor_amortizacao
                    : (float) $parcela->valor;
                $valorJurosContrato = (float) ($parcela->valor_juros ?? ($valorParcelaTotal - $valorPrincipalParcela));

                if ($valorTotalPago > $valorParcelaTotal) {
                    throw ValidationException::withMessages([
                        'valor' => 'O valor pago na renovação não pode ser maior que o valor total da parcela (R$ ' . number_format($valorParcelaTotal, 2, ',', '.') . ').',
                    ]);
                }
                if (!$solicitacaoAprovada && $valorTotalPago < $valorPrincipalParcela) {
                    throw ValidationException::withMessages([
                        'valor' => 'O valor pago na renovação não pode ser menor que o principal (R$ ' . number_format($valorPrincipalParcela, 2, ',', '.') . '). Para valores inferiores, solicite aprovação.',
                    ]);
                }

                // Alocação "juros primeiro": primeiro cobre juros do contrato, o restante abate principal
                $parteJuros = min($valorTotalPago, $valorJurosContrato);
                $partePrincipal = round($valorTotalPago - $parteJuros, 2);
                $valorJuros = $parteJuros;
                $saldoRestante = round($valorParcelaTotal - $valorTotalPago, 2);

                $dataPagamentoObj = $dataPagamento ? Carbon::parse($dataPagamento) : Carbon::today();
                $pagamentoService = app(\App\Modules\Loans\Services\PagamentoService::class);
                $pagamentoService->registrar([
                    'parcela_id' => $parcela->id,
                    'consultor_id' => $consultorId ?? auth()->id(),
                    'valor' => $valorTotalPago,
                    'metodo' => $metodoPagamento ?? 'dinheiro',
                    'data_pagamento' => $dataPagamentoObj,
                    'observacoes' => "Renovação com abate do empréstimo #{$emprestimo->id}. Juros: R$ " . number_format($parteJuros, 2, ',', '.') . "; principal abatido: R$ " . number_format($partePrincipal, 2, ',', '.') . ". Novo saldo: R$ " . number_format($saldoRestante, 2, ',', '.'),
                    'tipo_juros' => 'fixo',
                    'valor_juros_fixo' => $parteJuros,
                    'encerra_parcela_renovacao_abate' => true,
                ]);
                $parcela->refresh();
            } else {
                // --- Fluxo atual: pagar apenas juros (calculado); novo empréstimo = mesmo principal ---
                $valorJuros = $this->calcularJurosRenovacao(
                    $emprestimo,
                    $parcela,
                    $tipoJurosRenovacao,
                    $taxaJurosManual,
                    $valorJurosFixo
                );

                if ($registrarPagamentoAutomatico) {
                    $pagamentoService = app(\App\Modules\Loans\Services\PagamentoService::class);
                    $observacoes = "Pagamento automático de juros na renovação do empréstimo #{$emprestimo->id}";
                    if ($tipoJurosRenovacao === 'nenhum') {
                        $observacoes .= " (renovação sem juros)";
                    } elseif ($tipoJurosRenovacao === 'automatico') {
                        $observacoes .= " (juros automático)";
                    } elseif ($tipoJurosRenovacao === 'manual') {
                        $observacoes .= " (juros manual: {$taxaJurosManual}%)";
                    } elseif ($tipoJurosRenovacao === 'fixo') {
                        $observacoes .= " (valor fixo: R$ " . number_format($valorJurosFixo ?? 0, 2, ',', '.') . ")";
                    }
                    $pagamentoService->registrar([
                        'parcela_id' => $parcela->id,
                        'consultor_id' => auth()->id(),
                        'valor' => $valorJuros,
                        'metodo' => 'dinheiro',
                        'data_pagamento' => Carbon::today(),
                        'observacoes' => $observacoes,
                    ]);
                    $parcela->refresh();
                    $parcela->update([
                        'valor_pago' => $parcela->valor,
                        'status' => 'paga',
                        'data_pagamento' => Carbon::today(),
                    ]);
                }
            }

            // Criar novo empréstimo: com abate = saldo restante; sem abate = mesmo principal
            $novaDataInicio = Carbon::today();
            $novoValorTotal = ($valorTotalPago !== null && $valorTotalPago > 0) ? $saldoRestante : (float) $emprestimo->valor_total;

            $novoEmprestimo = Emprestimo::create([
                'operacao_id' => $emprestimo->operacao_id,
                'cliente_id' => $emprestimo->cliente_id,
                'consultor_id' => $emprestimo->consultor_id,
                'valor_total' => $novoValorTotal,
                'numero_parcelas' => $emprestimo->numero_parcelas,
                'frequencia' => $emprestimo->frequencia,
                'data_inicio' => $novaDataInicio,
                'taxa_juros' => $emprestimo->taxa_juros,
                'tipo' => $emprestimo->tipo, // Copiar tipo (importante para empréstimos tipo empenho)
                'status' => 'ativo', // Já está ativo, pois não há nova liberação de dinheiro
                'observacoes' => $emprestimo->observacoes,
                'empresa_id' => $emprestimo->empresa_id,
                'emprestimo_origem_id' => $emprestimo->id,
            ]);

            // Gerar parcelas para o novo empréstimo
            $this->gerarParcelas($novoEmprestimo);

            // Se o empréstimo original tinha garantias (tipo empenho), transferir para o novo empréstimo
            if ($emprestimo->garantias->isNotEmpty()) {
                foreach ($emprestimo->garantias as $garantiaOriginal) {
                    // Criar nova garantia para o novo empréstimo
                    $novaGarantia = \App\Modules\Loans\Models\EmprestimoGarantia::create([
                        'emprestimo_id' => $novoEmprestimo->id,
                        'categoria' => $garantiaOriginal->categoria,
                        'descricao' => $garantiaOriginal->descricao,
                        'valor_avaliado' => $garantiaOriginal->valor_avaliado,
                        'localizacao' => $garantiaOriginal->localizacao,
                        'observacoes' => $garantiaOriginal->observacoes . (empty($garantiaOriginal->observacoes) ? '' : "\n\n") . 
                                        "Garantia transferida da renovação do empréstimo #{$emprestimo->id}",
                    ]);

                    // Copiar todos os anexos da garantia original
                    foreach ($garantiaOriginal->anexos as $anexoOriginal) {
                        \App\Modules\Loans\Models\EmprestimoGarantiaAnexo::create([
                            'garantia_id' => $novaGarantia->id,
                            'nome_arquivo' => $anexoOriginal->nome_arquivo,
                            'caminho' => $anexoOriginal->caminho, // Mesmo arquivo físico (bem continua o mesmo)
                            'tipo' => $anexoOriginal->tipo,
                            'tamanho' => $anexoOriginal->tamanho,
                        ]);
                    }
                }
            }

            // Marcar empréstimo original como finalizado
            $statusAnterior = $emprestimo->status;
            $emprestimo->update([
                'status' => 'finalizado',
            ]);

            // Auditoria da renovação
            $dadosNovos = [
                'novo_emprestimo_id' => $novoEmprestimo->id,
                'emprestimo_origem_id' => $emprestimo->id,
                'status_origem_anterior' => $statusAnterior,
                'status_origem_atual' => $emprestimo->status,
                'valor_juros_pago' => $valorJuros,
                'pagamento_automatico' => $registrarPagamentoAutomatico,
            ];

            self::auditar(
                'renovar_emprestimo',
                $novoEmprestimo,
                null,
                $dadosNovos,
                "Empréstimo #{$emprestimo->id} renovado para o empréstimo #{$novoEmprestimo->id}" . 
                ($registrarPagamentoAutomatico ? " - Pagamento de juros (R$ " . number_format($valorJuros, 2, ',', '.') . ") registrado automaticamente" : "")
            );

            return $novoEmprestimo->fresh();
        });
    }

    /**
     * Verificar se cliente tem dívida ativa na operação
     *
     * @param int $clienteId
     * @param int $operacaoId
     * @return bool
     */
    public function temDividaAtiva(int $clienteId, int $operacaoId): bool
    {
        return Emprestimo::where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->whereIn('status', ['pendente', 'aprovado', 'ativo'])
            ->exists();
    }

    /**
     * Verificar se valor excede limite de crédito
     *
     * @param int $clienteId
     * @param int $operacaoId
     * @param float $valorEmprestimo
     * @return bool
     */
    public function limiteExcedido(int $clienteId, int $operacaoId, float $valorEmprestimo): bool
    {
        $vinculo = OperationClient::where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->where('status', 'ativo')
            ->first();

        if (!$vinculo) {
            return true; // Sem vínculo = sem limite = excedido
        }

        // Calcular dívida atual (empréstimos ativos)
        $dividaAtual = Emprestimo::where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->whereIn('status', ['aprovado', 'ativo'])
            ->sum('valor_total');

        // Verificar se novo empréstimo + dívida atual excede limite
        return ($dividaAtual + $valorEmprestimo) > $vinculo->limite_credito;
    }

    /**
     * Gerar parcelas automaticamente
     * 
     * @deprecated Use LoanStrategyFactory instead
     * Mantido para compatibilidade com código existente
     *
     * @param Emprestimo $emprestimo
     * @return void
     */
    public function gerarParcelas(Emprestimo $emprestimo): void
    {
        // Verificar se já existem parcelas geradas
        if ($emprestimo->parcelas()->count() > 0) {
            return; // Parcelas já foram geradas, não gerar novamente
        }

        // Se for tipo Price, gera com amortização
        if ($emprestimo->isPrice()) {
            $this->gerarParcelasPrice($emprestimo);
            return;
        }

        // Se for troca_cheque, não gera parcelas
        if ($emprestimo->isTrocaCheque()) {
            return; // Cheques são cadastrados separadamente
        }

        // Senão, usa geração atual (juros simples)
        $this->gerarParcelasSimples($emprestimo);
    }

    /**
     * Gerar parcelas para sistema Price (amortização)
     * 
     * @internal Usado pelas Strategies
     */
    public function gerarParcelasPrice(Emprestimo $emprestimo): void
    {
        $parcelaFixa = $emprestimo->calcularParcelaPrice();
        $saldoDevedor = $emprestimo->valor_total;
        $taxaDecimal = ($emprestimo->taxa_juros ?? 0) / 100;
        $dataVencimento = Carbon::parse($emprestimo->data_inicio);

        if ($parcelaFixa <= 0) {
            throw new \Exception('Não foi possível calcular a parcela fixa. Verifique a taxa de juros.');
        }

        for ($i = 1; $i <= $emprestimo->numero_parcelas; $i++) {
            // Calcular juros, amortização e saldo devedor
            $juros = $saldoDevedor * $taxaDecimal;
            $amortizacao = $parcelaFixa - $juros;
            $saldoDevedor = $saldoDevedor - $amortizacao;

            // Ajuste na última parcela: forçar saldo zero (comparação com (int) evita falha quando numero_parcelas vem como string do DB)
            if ($i === (int) $emprestimo->numero_parcelas) {
                $saldoDevedor = 0;
            }

            // Calcular data de vencimento baseado na frequência
            if ($i > 1) {
                switch ($emprestimo->frequencia) {
                    case 'diaria':
                        $dataVencimento->addDay();
                        break;
                    case 'semanal':
                        $dataVencimento->addWeek();
                        break;
                    case 'mensal':
                        $dataVencimento->addMonth();
                        break;
                }
            }

            Parcela::create([
                'emprestimo_id' => $emprestimo->id,
                'numero' => $i,
                'valor' => round($parcelaFixa, 2),
                'valor_juros' => round($juros, 2),
                'valor_amortizacao' => round($amortizacao, 2),
                'saldo_devedor' => round($saldoDevedor, 2),
                'empresa_id' => $emprestimo->empresa_id,
                'valor_pago' => 0,
                'data_vencimento' => $dataVencimento->copy(),
                'status' => 'pendente',
            ]);
        }
    }

    /**
     * Gerar parcelas para sistema simples (juros simples)
     * 
     * @internal Usado pelas Strategies
     */
    public function gerarParcelasSimples(Emprestimo $emprestimo): void
    {
        $valorParcela = $emprestimo->calcularValorParcela();
        $principalTotal = (float) $emprestimo->valor_total;
        $valorAmortizacao = round($principalTotal / $emprestimo->numero_parcelas, 2);
        $valorJuros = round($valorParcela - $valorAmortizacao, 2);
        $dataVencimento = Carbon::parse($emprestimo->data_inicio);

        // Primeira parcela vence 1 período após data_inicio (não no mesmo dia da venda/contrato)
        switch ($emprestimo->frequencia) {
            case 'diaria':
                $dataVencimento->addDay();
                break;
            case 'semanal':
                $dataVencimento->addWeek();
                break;
            case 'mensal':
            default:
                $dataVencimento->addMonth();
                break;
        }

        for ($i = 1; $i <= $emprestimo->numero_parcelas; $i++) {
            // A partir da 2ª parcela, soma mais um período
            if ($i > 1) {
                switch ($emprestimo->frequencia) {
                    case 'diaria':
                        $dataVencimento->addDay();
                        break;
                    case 'semanal':
                        $dataVencimento->addWeek();
                        break;
                    case 'mensal':
                        $dataVencimento->addMonth();
                        break;
                    default:
                        $dataVencimento->addMonth();
                        break;
                }
            }

            Parcela::create([
                'emprestimo_id' => $emprestimo->id,
                'numero' => $i,
                'valor' => $valorParcela,
                'valor_juros' => $valorJuros,
                'valor_amortizacao' => $valorAmortizacao,
                'valor_pago' => 0,
                'data_vencimento' => $dataVencimento->copy(),
                'status' => 'pendente',
                'empresa_id' => $emprestimo->empresa_id,
            ]);
        }
    }

    /**
     * Aprovar empréstimo pendente
     *
     * @param int $emprestimoId
     * @param int $aprovadorId
     * @param string|null $observacoes
     * @return Emprestimo
     */
    public function aprovar(int $emprestimoId, int $aprovadorId, ?string $observacoes = null): Emprestimo
    {
        return DB::transaction(function () use ($emprestimoId, $aprovadorId, $observacoes) {
            $emprestimo = Emprestimo::findOrFail($emprestimoId);

            if ($emprestimo->status !== 'pendente') {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos pendentes podem ser aprovados.'
                ]);
            }

            // Usar Strategy Pattern para validações específicas
            $strategy = LoanStrategyFactory::create($emprestimo);
            $strategy->validarAntesAprovacao($emprestimo);

            $oldStatus = $emprestimo->status;

            // Verificar se estrutura de pagamento já foi gerada
            // Se não existir, gerar agora (para empréstimos antigos)
            // Para troca_cheque: cheques são cadastrados manualmente, não precisa gerar
            if (!$emprestimo->isTrocaCheque() && $emprestimo->parcelas()->count() === 0) {
                $strategy = LoanStrategyFactory::create($emprestimo);
                $strategy->gerarEstruturaPagamento($emprestimo);
            }

            // Verificar se operação requer liberação
            // Carregar operação se ainda não estiver carregado
            if (!$emprestimo->relationLoaded('operacao')) {
                $emprestimo->load('operacao');
            }
            $requerLiberacao = $emprestimo->operacao ? $emprestimo->operacao->requerLiberacao() : true;

            if ($requerLiberacao) {
                // Criar registro de liberação pendente (gestor precisa liberar dinheiro)
                $this->criarLiberacaoPendente($emprestimo);

                // Atualizar status (permanece 'aprovado' até dinheiro ser liberado)
                $emprestimo->update([
                    'status' => 'aprovado', // Muda para 'ativo' quando consultor confirma pagamento ao cliente
                    'aprovado_por' => $aprovadorId,
                    'aprovado_em' => now(),
                ]);
            } else {
                // Não requer liberação - liberar automaticamente
                $liberacaoService = app(\App\Modules\Loans\Services\LiberacaoService::class);
                $liberacao = $this->criarLiberacaoPendente($emprestimo);
                
                // Liberar automaticamente (sem necessidade de gestor)
                // Usar o aprovador como gestor para liberação automática
                $liberacaoService->liberar($liberacao->id, $aprovadorId, 'Liberação automática - operação não requer aprovação de gestor');
                
                // Status permanece 'aprovado' até o consultor confirmar pagamento ao cliente
                // Depois muda para 'ativo'
                $emprestimo->update([
                    'status' => 'aprovado',
                    'aprovado_por' => $aprovadorId,
                    'aprovado_em' => now(),
                ]);
            }

            // Auditoria
            self::auditar(
                'aprovar_emprestimo',
                $emprestimo,
                ['status' => $oldStatus],
                ['status' => 'ativo'],
                $observacoes
            );

            // Notificações
            $notificacaoService = app(NotificacaoService::class);
            // Carregar cliente se ainda não estiver carregado
            if (!$emprestimo->relationLoaded('cliente')) {
                $emprestimo->load('cliente');
            }
            $cliente = $emprestimo->cliente;
            
            if ($cliente) {
                $operacaoId = (int) $emprestimo->operacao_id;
                $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, [
                    'tipo' => 'emprestimo_aprovado',
                    'titulo' => 'Empréstimo Aprovado - Liberação Pendente',
                    'mensagem' => "Empréstimo de R$ " . number_format($emprestimo->valor_total, 2, ',', '.') . " para {$cliente->nome} aprovado e aguardando liberação",
                    'url' => route('liberacoes.index'),
                    'dados' => ['emprestimo_id' => $emprestimo->id],
                ]);
            }

            return $emprestimo->fresh();
        });
    }

    /**
     * Rejeitar empréstimo pendente
     *
     * @param int $emprestimoId
     * @param int $aprovadorId
     * @param string $motivoRejeicao
     * @return Emprestimo
     */
    public function rejeitar(int $emprestimoId, int $aprovadorId, string $motivoRejeicao): Emprestimo
    {
        return DB::transaction(function () use ($emprestimoId, $aprovadorId, $motivoRejeicao) {
            $emprestimo = Emprestimo::findOrFail($emprestimoId);

            if ($emprestimo->status !== 'pendente') {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos pendentes podem ser rejeitados.'
                ]);
            }

            $oldStatus = $emprestimo->status;

            // Fazer soft delete das parcelas (apenas se não houver pagamentos)
            $parcelas = $emprestimo->parcelas;
            foreach ($parcelas as $parcela) {
                // Verificar se a parcela já tem pagamentos
                if ($parcela->pagamentos()->count() === 0 && $parcela->valor_pago == 0) {
                    // Apenas deletar se não houver pagamentos registrados
                    $parcela->delete();
                }
            }

            $emprestimo->update([
                'status' => 'cancelado',
                'aprovado_por' => $aprovadorId,
                'aprovado_em' => now(),
                'motivo_rejeicao' => $motivoRejeicao,
            ]);

            // Auditoria
            self::auditar(
                'rejeitar_emprestimo',
                $emprestimo,
                ['status' => $oldStatus],
                ['status' => 'cancelado', 'motivo_rejeicao' => $motivoRejeicao],
                $motivoRejeicao
            );

            return $emprestimo->fresh();
        });
    }

    /**
     * Garantir que existe vínculo entre cliente e operação
     * Cria automaticamente se não existir
     *
     * @param int $clienteId
     * @param int $operacaoId
     * @param int|null $consultorId
     * @return OperationClient
     */
    public function garantirVinculoClienteOperacao(int $clienteId, int $operacaoId, ?int $consultorId = null): OperationClient
    {
        $vinculo = OperationClient::where('cliente_id', $clienteId)
            ->where('operacao_id', $operacaoId)
            ->first();

        if (!$vinculo) {
            // Criar vínculo automaticamente com limite padrão (0 = sem limite definido)
            $vinculo = OperationClient::create([
                'cliente_id' => $clienteId,
                'operacao_id' => $operacaoId,
                'limite_credito' => 0, // Sem limite definido inicialmente
                'status' => 'ativo',
                'consultor_id' => $consultorId,
            ]);

            // Auditoria
            self::auditar('criar_vinculo_cliente_operacao_automatico', $vinculo, null, $vinculo->toArray(), 'Vínculo criado automaticamente ao criar empréstimo');
        } elseif ($consultorId && !$vinculo->consultor_id) {
            // Atualizar consultor se não estiver definido
            $vinculo->update(['consultor_id' => $consultorId]);
        }

        return $vinculo;
    }

    /**
     * Criar liberação pendente para empréstimo aprovado
     *
     * @param Emprestimo $emprestimo
     * @return \App\Modules\Loans\Models\LiberacaoEmprestimo
     */
    protected function criarLiberacaoPendente(Emprestimo $emprestimo): \App\Modules\Loans\Models\LiberacaoEmprestimo
    {
        $liberacaoService = app(\App\Modules\Loans\Services\LiberacaoService::class);
        return $liberacaoService->criarPendente($emprestimo);
    }

    /**
     * Cancelar empréstimo (apenas administradores)
     * 
     * Só pode cancelar se:
     * - Dinheiro ainda não foi pago ao cliente
     * - Não há parcelas pagas
     * 
     * Se o dinheiro já foi liberado para o consultor, cria movimentações de estorno
     *
     * @param int $emprestimoId
     * @param int $administradorId
     * @param string $motivoCancelamento
     * @return Emprestimo
     * @throws ValidationException
     */
    public function cancelar(int $emprestimoId, int $administradorId, string $motivoCancelamento): Emprestimo
    {
        return DB::transaction(function () use ($emprestimoId, $administradorId, $motivoCancelamento) {
            $emprestimo = Emprestimo::with(['liberacao', 'parcelas', 'garantias'])->findOrFail($emprestimoId);
            $oldStatus = $emprestimo->status;

            // VALIDAÇÃO 1: Não pode cancelar se já está cancelado
            if ($emprestimo->isCancelado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Este empréstimo já está cancelado.'
                ]);
            }

            // VALIDAÇÃO 2: Não pode cancelar se já foi finalizado
            if ($emprestimo->isFinalizado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível cancelar um empréstimo finalizado.'
                ]);
            }

            // VALIDAÇÃO 2.5: Não pode cancelar se é um empréstimo renovado
            if ($emprestimo->isRenovacao()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível cancelar um empréstimo renovado. O empréstimo original já foi finalizado e o dinheiro foi pago ao cliente. As garantias foram transferidas e não podem ser revertidas automaticamente.'
                ]);
            }

            // VALIDAÇÃO 3: Verificar se há parcelas pagas ou quitadas
            $parcelasComPagamento = $emprestimo->parcelas->filter(function ($parcela) {
                return $parcela->valor_pago > 0 || $parcela->pagamentos()->count() > 0 || $parcela->isQuitada();
            });
            
            if ($parcelasComPagamento->count() > 0) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível cancelar um empréstimo que já possui parcelas pagas ou quitadas.'
                ]);
            }

            // VALIDAÇÃO 4: Verificar se dinheiro já foi pago ao cliente
            if ($emprestimo->liberacao && $emprestimo->liberacao->isPagoAoCliente()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível cancelar um empréstimo cujo dinheiro já foi pago ao cliente.'
                ]);
            }

            // Se existe liberação e o dinheiro já foi liberado (mas não pago ao cliente)
            // Precisa criar movimentações de estorno
            if ($emprestimo->liberacao && $emprestimo->liberacao->isLiberado()) {
                $cashService = app(\App\Modules\Cash\Services\CashService::class);
                $liberacao = $emprestimo->liberacao;

                // Criar movimentação de estorno: SAÍDA do caixa do consultor
                $cashService->registrarMovimentacao([
                    'operacao_id' => $emprestimo->operacao_id,
                    'consultor_id' => $liberacao->consultor_id,
                    'tipo' => 'saida',
                    'origem' => 'automatica',
                    'valor' => $liberacao->valor_liberado,
                    'data_movimentacao' => now(),
                    'descricao' => "Estorno - Cancelamento Empréstimo #{$emprestimo->id}",
                    'referencia_tipo' => 'cancelamento_emprestimo',
                    'referencia_id' => $emprestimo->id,
                ]);

                // Criar movimentação de estorno: ENTRADA no caixa do gestor
                if ($liberacao->gestor_id) {
                    $nomeConsultor = $liberacao->consultor ? $liberacao->consultor->name : 'N/A';
                    $cashService->registrarMovimentacao([
                        'operacao_id' => $emprestimo->operacao_id,
                        'consultor_id' => $liberacao->gestor_id,
                        'tipo' => 'entrada',
                        'origem' => 'automatica',
                        'valor' => $liberacao->valor_liberado,
                        'data_movimentacao' => now(),
                        'descricao' => "Estorno - Cancelamento Empréstimo #{$emprestimo->id} - Consultor {$nomeConsultor}",
                        'referencia_tipo' => 'cancelamento_emprestimo',
                        'referencia_id' => $emprestimo->id,
                    ]);
                }
            }

            // Atualizar status da liberação para cancelado (se existir)
            if ($emprestimo->liberacao) {
                $emprestimo->liberacao->update([
                    'status' => 'cancelado'
                ]);
            }

            // Fazer soft delete das parcelas (apenas se não houver pagamentos)
            foreach ($emprestimo->parcelas as $parcela) {
                if ($parcela->pagamentos()->count() === 0 && $parcela->valor_pago == 0) {
                    $parcela->delete();
                }
            }

            // Marcar garantias como canceladas (se for empréstimo tipo empenho)
            if ($emprestimo->isEmpenho() && $emprestimo->garantias->isNotEmpty()) {
                foreach ($emprestimo->garantias as $garantia) {
                    // Apenas cancelar garantias que ainda estão ativas
                    if ($garantia->isAtiva()) {
                        $observacoesAnteriores = $garantia->observacoes ?? '';
                        $observacaoCancelamento = "\n\n[CANCELADA EM " . Carbon::now()->format('d/m/Y H:i') . "]\n" .
                                                   "Empréstimo #{$emprestimo->id} foi cancelado.\n" .
                                                   "Motivo do cancelamento: {$motivoCancelamento}";
                        
                        $garantia->update([
                            'status' => 'cancelada',
                            'observacoes' => $observacoesAnteriores . $observacaoCancelamento,
                        ]);

                        // Auditoria da garantia
                        self::auditar(
                            'cancelar_garantia',
                            $garantia,
                            ['status' => 'ativa'],
                            ['status' => 'cancelada', 'observacoes' => $garantia->observacoes],
                            "Garantia cancelada devido ao cancelamento do empréstimo #{$emprestimo->id}. Motivo: {$motivoCancelamento}"
                        );
                    }
                }
            }

            // Atualizar empréstimo
            $emprestimo->update([
                'status' => 'cancelado',
                'aprovado_por' => $administradorId,
                'aprovado_em' => now(),
                'motivo_rejeicao' => $motivoCancelamento,
            ]);

            // Auditoria
            $observacoesAuditoria = "Empréstimo cancelado por administrador. Motivo: {$motivoCancelamento}";
            if ($emprestimo->liberacao && $emprestimo->liberacao->isLiberado()) {
                $observacoesAuditoria .= " Movimentações de estorno criadas.";
            }

            self::auditar(
                'cancelar_emprestimo',
                $emprestimo,
                ['status' => $oldStatus],
                ['status' => 'cancelado', 'motivo_rejeicao' => $motivoCancelamento],
                $observacoesAuditoria
            );

            // Notificações
            $notificacaoService = app(NotificacaoService::class);
            $cliente = $emprestimo->cliente;
            
            // Notificar consultor sobre cancelamento
            if ($emprestimo->consultor_id) {
                $notificacaoService->criar([
                    'user_id' => $emprestimo->consultor_id,
                    'tipo' => 'emprestimo_cancelado',
                    'titulo' => 'Empréstimo Cancelado',
                    'mensagem' => "O empréstimo #{$emprestimo->id} do cliente {$cliente->nome} foi cancelado. Motivo: {$motivoCancelamento}",
                    'url' => route('emprestimos.show', $emprestimo->id),
                    'dados' => ['emprestimo_id' => $emprestimo->id],
                ]);
            }

            return $emprestimo->fresh();
        });
    }

    /**
     * Cancelar empréstimo com desfazimento de todos os pagamentos (gestor ou administrador).
     * Desfaz: movimentações de caixa, pagamentos, zera parcelas, soft-delete parcelas, marca empréstimo e liberação como cancelados.
     *
     * @param int $emprestimoId
     * @param int $userId
     * @param string $motivoCancelamento
     * @return Emprestimo
     * @throws ValidationException
     */
    public function cancelarComDesfazimento(int $emprestimoId, int $userId, string $motivoCancelamento): Emprestimo
    {
        return DB::transaction(function () use ($emprestimoId, $userId, $motivoCancelamento) {
            $emprestimo = Emprestimo::with(['liberacao', 'parcelas.pagamentos', 'garantias'])->findOrFail($emprestimoId);
            $oldStatus = $emprestimo->status;

            if ($emprestimo->isCancelado()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Este empréstimo já está cancelado.',
                ]);
            }

            if ($emprestimo->isRenovacao()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível cancelar um empréstimo renovado.',
                ]);
            }

            $parcelasComPagamento = $emprestimo->parcelas->filter(fn ($p) => $p->valor_pago > 0 || $p->pagamentos->isNotEmpty());
            $pagamentoIds = $parcelasComPagamento->flatMap(fn ($p) => $p->pagamentos->pluck('id'))->unique()->filter()->values()->all();

            // 1) Soft-delete das movimentações de caixa ligadas aos pagamentos
            if (!empty($pagamentoIds)) {
                CashLedgerEntry::whereIn('pagamento_id', $pagamentoIds)->delete();
                Pagamento::whereIn('id', $pagamentoIds)->delete();
            }

            // 2) Zerar parcelas que tinham valor pago
            Parcela::where('emprestimo_id', $emprestimoId)
                ->where('valor_pago', '>', 0)
                ->update([
                    'valor_pago' => 0,
                    'data_pagamento' => null,
                    'status' => 'pendente',
                    'dias_atraso' => 0,
                ]);

            // 3) Atualizar liberação para cancelado (se existir)
            if ($emprestimo->liberacao) {
                $emprestimo->liberacao->update(['status' => 'cancelado']);
            }

            // 4) Cancelar garantias ativas (empenho)
            if ($emprestimo->isEmpenho() && $emprestimo->garantias->isNotEmpty()) {
                foreach ($emprestimo->garantias as $garantia) {
                    if ($garantia->isAtiva()) {
                        $obs = ($garantia->observacoes ?? '') . "\n\n[CANCELADA EM " . Carbon::now()->format('d/m/Y H:i') . "]\nEmpréstimo #{$emprestimo->id} cancelado com desfazimento. Motivo: {$motivoCancelamento}";
                        $garantia->update(['status' => 'cancelada', 'observacoes' => $obs]);
                    }
                }
            }

            // 5) Marcar empréstimo como cancelado
            $emprestimo->update([
                'status' => 'cancelado',
                'aprovado_por' => $userId,
                'aprovado_em' => now(),
                'motivo_rejeicao' => $motivoCancelamento,
            ]);

            // 6) Soft-delete das parcelas do empréstimo
            Parcela::where('emprestimo_id', $emprestimoId)->delete();

            self::auditar(
                'cancelar_emprestimo_com_desfazimento',
                $emprestimo,
                ['status' => $oldStatus],
                ['status' => 'cancelado', 'motivo_rejeicao' => $motivoCancelamento],
                "Empréstimo cancelado com desfazimento de pagamentos por gestor/administrador. Motivo: {$motivoCancelamento}"
            );

            $notificacaoService = app(NotificacaoService::class);
            $cliente = $emprestimo->cliente;
            if ($emprestimo->consultor_id) {
                $notificacaoService->criar([
                    'user_id' => $emprestimo->consultor_id,
                    'tipo' => 'emprestimo_cancelado',
                    'titulo' => 'Empréstimo Cancelado',
                    'mensagem' => "O empréstimo #{$emprestimo->id} do cliente {$cliente->nome} foi cancelado (com desfazimento de pagamentos). Motivo: {$motivoCancelamento}",
                    'url' => route('emprestimos.show', $emprestimo->id),
                    'dados' => ['emprestimo_id' => $emprestimo->id],
                ]);
            }

            return $emprestimo->fresh();
        });
    }

    /**
     * Calcular juros de renovação baseado na sub-opção escolhida
     *
     * @param Emprestimo $emprestimo
     * @param Parcela $parcela
     * @param string $tipoJurosRenovacao (nenhum, automatico, manual, fixo)
     * @param float|null $taxaJurosManual Taxa informada manualmente (se tipo = manual)
     * @param float|null $valorJurosFixo Valor fixo informado (se tipo = fixo)
     * @return float
     */
    private function calcularJurosRenovacao(
        Emprestimo $emprestimo,
        Parcela $parcela,
        string $tipoJurosRenovacao,
        ?float $taxaJurosManual = null,
        ?float $valorJurosFixo = null
    ): float {
        $valorPrincipal = $emprestimo->valor_total;
        $diasAtraso = $parcela->calcularDiasAtraso();
        
        // Carregar operação se não estiver carregada
        if (!$emprestimo->relationLoaded('operacao')) {
            $emprestimo->load('operacao');
        }
        
        $operacao = $emprestimo->operacao;
        $taxaJurosAtraso = $operacao->taxa_juros_atraso ?? 0;
        $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
        
        switch ($tipoJurosRenovacao) {
            case 'nenhum':
                // Sem juros de atraso, mas o cliente paga ao menos os juros do contrato
                return round($emprestimo->calcularValorJuros(), 2);
                
            case 'automatico':
                // Juros originais do empréstimo (taxa_juros)
                $jurosOriginais = $emprestimo->calcularValorJuros();
                
                // Juros por atraso (taxa_juros_atraso × dias)
                $jurosAtraso = 0;
                if ($taxaJurosAtraso > 0 && $diasAtraso > 0) {
                    if ($tipoCalculo === 'por_dia') {
                        $jurosAtraso = $valorPrincipal * ($taxaJurosAtraso / 100) * $diasAtraso;
                    } else {
                        $jurosAtraso = $valorPrincipal * ($taxaJurosAtraso / 100) * ($diasAtraso / 30);
                    }
                }
                
                // Total = Juros originais + Juros por atraso
                return round($jurosOriginais + $jurosAtraso, 2);
                
            case 'manual':
                // Juros originais (sempre incluídos)
                $jurosOriginais = $emprestimo->calcularValorJuros();
                
                // Juros por atraso (taxa informada manualmente × dias)
                $jurosAtraso = 0;
                if ($taxaJurosManual > 0 && $diasAtraso > 0) {
                    if ($tipoCalculo === 'por_dia') {
                        $jurosAtraso = $valorPrincipal * ($taxaJurosManual / 100) * $diasAtraso;
                    } else {
                        $jurosAtraso = $valorPrincipal * ($taxaJurosManual / 100) * ($diasAtraso / 30);
                    }
                }
                
                // Total = Juros originais + Juros por atraso
                return round($jurosOriginais + $jurosAtraso, 2);
                
            case 'fixo':
                // Valor fixo = juros de atraso informado; total = juros do contrato + valor fixo
                $jurosOriginais = $emprestimo->calcularValorJuros();
                $valorFixo = max(0, (float) ($valorJurosFixo ?? 0));
                return round($jurosOriginais + $valorFixo, 2);
                
            default:
                // Fallback para juros originais
                return round($emprestimo->calcularValorJuros(), 2);
        }
    }

    /**
     * Executar garantia de empréstimo tipo empenho
     * Finaliza o empréstimo automaticamente quando a garantia é executada
     *
     * @param int $emprestimoId
     * @param int $garantiaId
     * @param int $executorId ID do usuário que está executando
     * @param string $observacoes Observações/motivo da execução
     * @return \App\Modules\Loans\Models\EmprestimoGarantia
     * @throws ValidationException
     */
    public function executarGarantia(int $emprestimoId, int $garantiaId, int $executorId, string $observacoes): \App\Modules\Loans\Models\EmprestimoGarantia
    {
        return DB::transaction(function () use ($emprestimoId, $garantiaId, $executorId, $observacoes) {
            $emprestimo = Emprestimo::with(['garantias', 'parcelas'])->findOrFail($emprestimoId);
            $garantia = \App\Modules\Loans\Models\EmprestimoGarantia::findOrFail($garantiaId);

            // VALIDAÇÃO 1: Garantia deve pertencer ao empréstimo
            if ($garantia->emprestimo_id !== $emprestimoId) {
                throw ValidationException::withMessages([
                    'garantia' => 'A garantia não pertence a este empréstimo.'
                ]);
            }

            // VALIDAÇÃO 2: Empréstimo deve ser tipo empenho
            if (!$emprestimo->isEmpenho()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos do tipo empenho podem ter garantias executadas.'
                ]);
            }

            // VALIDAÇÃO 3: Empréstimo deve estar ativo
            if (!$emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos ativos podem ter garantias executadas.'
                ]);
            }

            // VALIDAÇÃO 4: Garantia deve estar ativa
            if (!$garantia->isAtiva()) {
                throw ValidationException::withMessages([
                    'garantia' => 'Apenas garantias ativas podem ser executadas. Status atual: ' . $garantia->status
                ]);
            }

            // Permite executar mesmo sem parcela atrasada (ex.: cliente decide não pagar antes do vencimento)

            $oldStatusGarantia = $garantia->status;
            $oldStatusEmprestimo = $emprestimo->status;

            $executor = User::find($executorId);
            $executorTexto = $executor ? $executor->name . ' (ID ' . $executorId . ')' : 'ID ' . $executorId;

            // Executar garantia
            $garantia->update([
                'status' => 'executada',
                'data_execucao' => Carbon::now(),
                'observacoes' => ($garantia->observacoes ?? '') . "\n\n[EXECUTADA EM " . Carbon::now()->format('d/m/Y H:i') . "]\nExecutor: {$executorTexto}\nMotivo: {$observacoes}",
            ]);

            // Marcar todas as parcelas NÃO PAGAS como quitadas por garantia
            // Parcelas já pagas são preservadas (mantém histórico)
            foreach ($emprestimo->parcelas as $parcela) {
                if ($parcela->status !== 'paga') {
                    $oldStatusParcela = $parcela->status;
                    $oldValorPago = $parcela->valor_pago;
                    
                    $parcela->update([
                        'status' => 'quitada_garantia',
                        'valor_pago' => 0, // Não houve pagamento, apenas execução de garantia
                        'data_pagamento' => Carbon::now(),
                        'dias_atraso' => 0,
                    ]);
                    
                    // Auditoria da parcela
                    self::auditar(
                        'marcar_parcela_quitada_garantia',
                        $parcela,
                        [
                            'status' => $oldStatusParcela,
                            'valor_pago' => $oldValorPago,
                        ],
                        [
                            'status' => 'quitada_garantia',
                            'valor_pago' => 0,
                            'data_pagamento' => $parcela->data_pagamento,
                        ],
                        "Parcela quitada via execução da garantia. Motivo: {$observacoes}"
                    );
                }
            }

            // Finalizar empréstimo
            $emprestimo->update([
                'status' => 'finalizado',
                'motivo_rejeicao' => "Garantia executada: {$observacoes}",
            ]);

            // Auditoria da execução da garantia
            self::auditar(
                'executar_garantia',
                $garantia,
                [
                    'status' => $oldStatusGarantia,
                    'data_execucao' => null,
                ],
                [
                    'status' => 'executada',
                    'data_execucao' => $garantia->data_execucao,
                    'observacoes' => $observacoes,
                ],
                "Garantia executada pelo usuário ID {$executorId}. Motivo: {$observacoes}"
            );

            // Auditoria da finalização do empréstimo
            self::auditar(
                'finalizar_emprestimo',
                $emprestimo,
                ['status' => $oldStatusEmprestimo],
                ['status' => 'finalizado', 'motivo' => "Garantia executada"],
                "Empréstimo finalizado automaticamente devido à execução da garantia"
            );

            // Notificações
            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            $cliente = $emprestimo->cliente;

            // Notificar consultor
            if ($emprestimo->consultor_id) {
                $notificacaoService->criar([
                    'user_id' => $emprestimo->consultor_id,
                    'tipo' => 'garantia_executada',
                    'titulo' => 'Garantia Executada',
                    'mensagem' => "A garantia do empréstimo #{$emprestimo->id} do cliente {$cliente->nome} foi executada. Motivo: {$observacoes}",
                    'url' => route('emprestimos.show', $emprestimo->id),
                    'dados' => [
                        'emprestimo_id' => $emprestimo->id,
                        'garantia_id' => $garantia->id,
                    ],
                ]);
            }

            return $garantia->fresh();
        });
    }

    /**
     * Negociar empréstimo - cria novo empréstimo com saldo devedor e novas condições
     * 
     * @param int $emprestimoOrigemId ID do empréstimo sendo negociado
     * @param array $dadosNovoEmprestimo Dados do novo empréstimo (tipo, frequencia, taxa_juros, numero_parcelas, data_inicio, observacoes)
     * @param string $motivo Motivo da negociação
     * @return Emprestimo Novo empréstimo criado
     */
    public function negociar(int $emprestimoOrigemId, array $dadosNovoEmprestimo, string $motivo): Emprestimo
    {
        return DB::transaction(function () use ($emprestimoOrigemId, $dadosNovoEmprestimo, $motivo) {
            $emprestimoOrigem = Emprestimo::with(['parcelas', 'garantias.anexos', 'cliente', 'operacao'])->findOrFail($emprestimoOrigemId);

            if (!$emprestimoOrigem->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Apenas empréstimos ativos podem ser negociados.',
                ]);
            }

            if ($emprestimoOrigem->emprestimo_origem_id) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Este empréstimo já é resultado de uma negociação anterior e não pode ser negociado novamente.',
                ]);
            }

            $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
            $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimoOrigem);

            if ($saldoDevedor <= 0) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Este empréstimo não possui saldo devedor para negociação.',
                ]);
            }

            $jurosIncorporados = $this->calcularJurosIncorporados($emprestimoOrigem);

            $novaDataInicio = isset($dadosNovoEmprestimo['data_inicio']) 
                ? Carbon::parse($dadosNovoEmprestimo['data_inicio']) 
                : Carbon::today();

            $novoEmprestimo = Emprestimo::create([
                'operacao_id' => $emprestimoOrigem->operacao_id,
                'cliente_id' => $emprestimoOrigem->cliente_id,
                'consultor_id' => $emprestimoOrigem->consultor_id,
                'valor_total' => $saldoDevedor,
                'numero_parcelas' => (int) ($dadosNovoEmprestimo['numero_parcelas'] ?? 1),
                'frequencia' => $dadosNovoEmprestimo['frequencia'] ?? $emprestimoOrigem->frequencia,
                'data_inicio' => $novaDataInicio,
                'taxa_juros' => (float) ($dadosNovoEmprestimo['taxa_juros'] ?? $emprestimoOrigem->taxa_juros),
                'tipo' => $dadosNovoEmprestimo['tipo'] ?? $emprestimoOrigem->tipo,
                'status' => 'ativo',
                'observacoes' => $dadosNovoEmprestimo['observacoes'] ?? null,
                'empresa_id' => $emprestimoOrigem->empresa_id,
                'emprestimo_origem_id' => $emprestimoOrigem->id,
                'juros_incorporados' => $jurosIncorporados,
            ]);

            $this->gerarParcelas($novoEmprestimo);

            if ($emprestimoOrigem->garantias->isNotEmpty()) {
                foreach ($emprestimoOrigem->garantias as $garantiaOriginal) {
                    $novaGarantia = \App\Modules\Loans\Models\EmprestimoGarantia::create([
                        'emprestimo_id' => $novoEmprestimo->id,
                        'categoria' => $garantiaOriginal->categoria,
                        'descricao' => $garantiaOriginal->descricao,
                        'valor_avaliado' => $garantiaOriginal->valor_avaliado,
                        'localizacao' => $garantiaOriginal->localizacao,
                        'status' => 'ativa',
                        'observacoes' => ($garantiaOriginal->observacoes ?? '') . 
                            (empty($garantiaOriginal->observacoes) ? '' : "\n\n") . 
                            "Garantia transferida da negociação do empréstimo #{$emprestimoOrigem->id}",
                    ]);

                    foreach ($garantiaOriginal->anexos as $anexoOriginal) {
                        \App\Modules\Loans\Models\EmprestimoGarantiaAnexo::create([
                            'garantia_id' => $novaGarantia->id,
                            'nome_arquivo' => $anexoOriginal->nome_arquivo,
                            'caminho' => $anexoOriginal->caminho,
                            'tipo' => $anexoOriginal->tipo,
                            'tamanho' => $anexoOriginal->tamanho,
                        ]);
                    }

                    $garantiaOriginal->update(['status' => 'liberada']);
                }
            }

            $statusAnterior = $emprestimoOrigem->status;
            $emprestimoOrigem->update([
                'status' => 'finalizado',
            ]);

            self::auditar(
                'negociar_emprestimo',
                $novoEmprestimo,
                null,
                [
                    'novo_emprestimo_id' => $novoEmprestimo->id,
                    'emprestimo_origem_id' => $emprestimoOrigem->id,
                    'saldo_devedor' => $saldoDevedor,
                    'juros_incorporados' => $jurosIncorporados,
                    'status_origem_anterior' => $statusAnterior,
                    'status_origem_atual' => 'finalizado',
                    'motivo' => $motivo,
                    'novas_condicoes' => $dadosNovoEmprestimo,
                ],
                "Empréstimo #{$emprestimoOrigem->id} negociado para empréstimo #{$novoEmprestimo->id}. Juros incorporados: R$ " . number_format($jurosIncorporados, 2, ',', '.') . ". Motivo: {$motivo}"
            );

            return $novoEmprestimo;
        });
    }

    /**
     * Aprovar solicitação de negociação
     */
    public function aprovarNegociacao(int $solicitacaoId, int $aprovadorId, ?string $observacao = null): Emprestimo
    {
        $solicitacao = \App\Modules\Loans\Models\SolicitacaoNegociacao::with(['emprestimo'])->findOrFail($solicitacaoId);

        if (!$solicitacao->isPendente()) {
            throw ValidationException::withMessages([
                'solicitacao' => 'Esta solicitação já foi processada.',
            ]);
        }

        $novoEmprestimo = $this->negociar(
            $solicitacao->emprestimo_id,
            $solicitacao->dados_novo_emprestimo,
            $solicitacao->motivo
        );

        $solicitacao->update([
            'status' => 'aprovado',
            'aprovado_por' => $aprovadorId,
            'aprovado_em' => now(),
            'observacao_aprovador' => $observacao,
            'novo_emprestimo_id' => $novoEmprestimo->id,
        ]);

        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        $notificacaoService->criar([
            'user_id' => $solicitacao->consultor_id,
            'tipo' => 'negociacao_aprovada',
            'titulo' => 'Negociação Aprovada',
            'mensagem' => "Sua solicitação de negociação do empréstimo #{$solicitacao->emprestimo_id} foi aprovada. Novo empréstimo #{$novoEmprestimo->id} criado.",
            'url' => route('emprestimos.show', $novoEmprestimo->id),
            'dados' => [
                'solicitacao_id' => $solicitacao->id,
                'emprestimo_origem_id' => $solicitacao->emprestimo_id,
                'novo_emprestimo_id' => $novoEmprestimo->id,
            ],
        ]);

        return $novoEmprestimo;
    }

    /**
     * Rejeitar solicitação de negociação
     */
    public function rejeitarNegociacao(int $solicitacaoId, int $rejeitadorId, ?string $observacao = null): \App\Modules\Loans\Models\SolicitacaoNegociacao
    {
        $solicitacao = \App\Modules\Loans\Models\SolicitacaoNegociacao::with(['emprestimo'])->findOrFail($solicitacaoId);

        if (!$solicitacao->isPendente()) {
            throw ValidationException::withMessages([
                'solicitacao' => 'Esta solicitação já foi processada.',
            ]);
        }

        $solicitacao->update([
            'status' => 'rejeitado',
            'aprovado_por' => $rejeitadorId,
            'aprovado_em' => now(),
            'observacao_aprovador' => $observacao,
        ]);

        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        $notificacaoService->criar([
            'user_id' => $solicitacao->consultor_id,
            'tipo' => 'negociacao_rejeitada',
            'titulo' => 'Negociação Rejeitada',
            'mensagem' => "Sua solicitação de negociação do empréstimo #{$solicitacao->emprestimo_id} foi rejeitada." . ($observacao ? " Motivo: {$observacao}" : ''),
            'url' => route('emprestimos.show', $solicitacao->emprestimo_id),
            'dados' => [
                'solicitacao_id' => $solicitacao->id,
                'emprestimo_id' => $solicitacao->emprestimo_id,
            ],
        ]);

        return $solicitacao;
    }

    /**
     * Calcula os juros do empréstimo original que serão incorporados ao novo empréstimo.
     * Considera apenas os juros das parcelas em aberto (proporcionalmente ao que resta pagar).
     */
    protected function calcularJurosIncorporados(Emprestimo $emprestimo): float
    {
        $parcelasEmAberto = $emprestimo->parcelas()
            ->whereNotIn('status', ['paga', 'quitada_garantia'])
            ->get();

        $jurosIncorporados = 0;

        foreach ($parcelasEmAberto as $parcela) {
            $valorParcela = (float) $parcela->valor;
            $valorPago = (float) ($parcela->valor_pago ?? 0);
            $valorJurosParcela = (float) ($parcela->valor_juros ?? 0);

            if ($valorParcela <= 0) {
                continue;
            }

            $percentualRestante = ($valorParcela - $valorPago) / $valorParcela;
            $jurosIncorporados += $valorJurosParcela * $percentualRestante;
        }

        return round($jurosIncorporados, 2);
    }
}

