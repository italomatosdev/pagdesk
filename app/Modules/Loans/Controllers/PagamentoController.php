<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\DiariaPagamentoAntecipacaoService;
use App\Modules\Loans\Services\PagamentoService;
use App\Support\ClienteNomeExibicao;
use App\Support\NotificacaoClienteDisplayName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PagamentoController extends Controller
{
    protected PagamentoService $pagamentoService;

    public function __construct(PagamentoService $pagamentoService)
    {
        $this->middleware('auth');
        $this->pagamentoService = $pagamentoService;
    }

    /**
     * Mostrar formulário de registro de pagamento
     */
    public function create(Request $request): View
    {
        $parcelaId = $request->input('parcela_id');
        $parcela = null;
        $returnTo = $request->input('return_to'); // Para saber de onde veio
        $renovar = $request->input('renovar', false); // Parâmetro para pré-selecionar renovação
        $renovacaoTipo = $request->input('renovacao_tipo'); // 'nenhum' = só juros (sem atraso), 'com_abate' = renovar com abate no saldo
        $executarGarantia = $request->input('executar_garantia', false); // Parâmetro para pré-selecionar executar garantia
        $modoAdiantamentoValor = $request->boolean('adiantamento');

        if ($parcelaId) {
            $parcela = Parcela::with(['emprestimo.cliente', 'emprestimo.operacao'])->findOrFail($parcelaId);
            $user = auth()->user();
            if (! $user->isSuperAdmin()) {
                $opsIds = $user->getOperacoesIds();
                if (empty($opsIds) || ! in_array((int) $parcela->emprestimo->operacao_id, $opsIds, true)) {
                    abort(403, 'Sem acesso a esta operação.');
                }
            }
        }

        $nomeClienteExibicao = $parcela
            ? ClienteNomeExibicao::forEmprestimo($parcela->emprestimo)
            : null;

        $diariaMultiplasParcelas = false;
        $diariaValorDevido = null;
        $diariaMaxAntecipacao = null;
        if ($parcela) {
            $parcela->loadMissing(['emprestimo.parcelas', 'emprestimo.operacao']);
            $em = $parcela->emprestimo;
            if ($em->isFrequenciaDiaria() && (int) $em->numero_parcelas > 1) {
                $diariaMultiplasParcelas = true;
                $ctxDiariaCreate = [
                    'tipo_juros' => $parcela->isAtrasada() ? 'automatico' : 'nenhum',
                    'data_pagamento' => now()->format('Y-m-d'),
                ];
                $diariaSvcCreate = app(DiariaPagamentoAntecipacaoService::class);
                $diariaValorDevido = $diariaSvcCreate->valorDevidoNoPagamento($parcela, $ctxDiariaCreate);
                $diariaMaxAntecipacao = $diariaSvcCreate->calcularMaximoPermitido($parcela, $ctxDiariaCreate);
            }
        }

        if ($modoAdiantamentoValor && $parcela && ! $parcela->podeAdiantarValor()) {
            return redirect()
                ->route('pagamentos.create', ['parcela_id' => $parcela->id])
                ->with('error', 'Adiantamento de valor não está disponível para esta parcela.');
        }

        $faltaPagarParcelaCreate = null;
        if ($parcela) {
            $faltaPagarParcelaCreate = max(0.0, round((float) $parcela->valor - (float) ($parcela->valor_pago ?? 0), 2));
        }

        return view('pagamentos.create', compact(
            'parcela',
            'returnTo',
            'renovar',
            'renovacaoTipo',
            'executarGarantia',
            'nomeClienteExibicao',
            'diariaMultiplasParcelas',
            'diariaValorDevido',
            'diariaMaxAntecipacao',
            'modoAdiantamentoValor',
            'faltaPagarParcelaCreate'
        ));
    }

    /**
     * Registrar pagamento
     */
    public function store(Request $request): RedirectResponse
    {
        // Determinar se é execução de garantia para ajustar validação
        $isExecutarGarantia = $request->input('tipo_juros') === 'executar_garantia';

        $validated = $request->validate([
            'parcela_id' => 'required|exists:parcelas,id',
            'valor' => $isExecutarGarantia ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'metodo' => $isExecutarGarantia ? 'nullable|in:dinheiro,pix,transferencia,outro' : 'required|in:dinheiro,pix,transferencia,outro,produto_objeto',
            'data_pagamento' => $isExecutarGarantia ? 'nullable|date' : 'required|date',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'observacoes' => 'nullable|string',
            // Itens produto/objeto (quando metodo = produto_objeto): 1 pagamento = N itens
            'itens' => 'nullable|required_if:metodo,produto_objeto|array|min:1',
            'itens.*.nome' => 'required_with:itens|string|max:255',
            'itens.*.descricao' => 'nullable|string|max:2000',
            'itens.*.valor_estimado' => 'required_with:itens|string|max:50',
            'itens.*.quantidade' => 'nullable|integer|min:1',
            'itens.*.imagens' => 'required_with:itens|array|min:1',
            'itens.*.imagens.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            // Campos de juros
            'tipo_juros' => 'nullable|in:nenhum,automatico,manual,fixo,valor_inferior,renovacao,executar_garantia',
            'taxa_juros_manual' => 'nullable|numeric|min:0|max:100|required_if:tipo_juros,manual',
            'valor_juros_fixo' => 'nullable|numeric|min:0|required_if:tipo_juros,fixo',
            // Campo para execução de garantia
            'observacoes_executar_garantia' => 'nullable|string|min:10|max:1000|required_if:tipo_juros,executar_garantia',
            // Campos para sub-opções de renovação
            'tipo_juros_renovacao' => 'nullable|in:nenhum,automatico,manual,fixo,com_abate',
            'taxa_juros_renovacao_manual' => 'nullable|numeric|min:0|max:100',
            'valor_juros_renovacao_fixo' => 'nullable|numeric|min:0',
            'valor_renovacao_abate' => 'nullable|numeric|min:0', // valor total a pagar na renovação com abate
            'adiantamento_valor' => 'nullable|boolean',
        ], [
            'observacoes_executar_garantia.required_if' => 'O campo de observações/motivo é obrigatório ao executar garantia.',
            'observacoes_executar_garantia.min' => 'As observações devem ter pelo menos 10 caracteres.',
            'observacoes_executar_garantia.max' => 'As observações não podem ter mais de 1000 caracteres.',
            'itens.required_if' => 'Adicione pelo menos um item (produto/objeto).',
            'itens.min' => 'Adicione pelo menos um item (produto/objeto).',
            'itens.*.nome.required_with' => 'Nome do item é obrigatório.',
            'itens.*.valor_estimado.required_with' => 'Valor estimado do item é obrigatório.',
            'itens.*.imagens.required_with' => 'Envie pelo menos uma imagem por item.',
            'itens.*.imagens.min' => 'Cada item deve ter pelo menos uma imagem.',
        ]);

        // Upload de comprovante
        if ($request->hasFile('comprovante')) {
            try {
                $path = $request->file('comprovante')->store('comprovantes', 'public');
                $validated['comprovante_path'] = $path;
            } catch (\Exception $e) {
                \Log::error('Erro ao fazer upload do comprovante: '.$e->getMessage());

                return back()->with('error', 'Erro ao fazer upload do comprovante. Tente novamente.')->withInput();
            }
        }

        $user = auth()->user();
        $parcelaAcesso = Parcela::with('emprestimo')->findOrFail($validated['parcela_id']);
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $parcelaAcesso->emprestimo->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Sem acesso a esta operação.')->withInput();
            }
        }

        $validated['consultor_id'] = $user->id;

        // Se método é produto/objeto: operação deve permitir e processar itens (upload por item)
        if (($validated['metodo'] ?? '') === 'produto_objeto') {
            $parcelaCheck = Parcela::with('emprestimo.operacao')->findOrFail($validated['parcela_id']);
            if (! $parcelaCheck->emprestimo->operacao->requer_autorizacao_pagamento_produto) {
                return back()->with('error', 'A operação deste empréstimo não permite pagamento em produto/objeto.')->withInput();
            }
            $itensInput = $request->input('itens', []);
            $itensData = [];
            foreach ($itensInput as $idx => $item) {
                $files = $request->file("itens.{$idx}.imagens") ?? [];
                $paths = [];
                foreach ($files as $file) {
                    $paths[] = $file->store('pagamentos-produto-objeto', 'public');
                }
                $itensData[] = [
                    'nome' => $item['nome'] ?? '',
                    'descricao' => $item['descricao'] ?? null,
                    'valor_estimado' => $this->parseBrDecimal($item['valor_estimado'] ?? 0),
                    'quantidade' => (int) ($item['quantidade'] ?? 1),
                    'imagens' => $paths,
                    'ordem' => count($itensData),
                ];
            }
            $validated['itens'] = $itensData;
        }

        try {
            // Verificar se já existe um pagamento idêntico recente (últimos 5 segundos)
            // Isso previne duplo submit acidental
            $parcela = Parcela::findOrFail($validated['parcela_id']);
            $pagamentoRecente = \App\Modules\Loans\Models\Pagamento::where('parcela_id', $validated['parcela_id'])
                ->where('valor', $validated['valor'])
                ->where('data_pagamento', $validated['data_pagamento'])
                ->where('created_at', '>=', now()->subSeconds(5))
                ->first();

            if ($pagamentoRecente) {
                // Se já existe um pagamento idêntico recente, redireciona sem criar outro
                $emprestimoId = $parcela->emprestimo_id;
                $returnTo = $request->input('return_to');

                if ($returnTo === 'cobrancas') {
                    return redirect()->route('cobrancas.index')
                        ->with('success', 'Pagamento já foi registrado anteriormente.');
                } elseif ($returnTo === 'parcelas_atrasadas') {
                    return redirect()->route('parcelas.atrasadas')
                        ->with('success', 'Pagamento já foi registrado anteriormente.');
                } else {
                    return redirect()->route('emprestimos.show', $emprestimoId)
                        ->with('success', 'Pagamento já foi registrado anteriormente.');
                }
            }

            // Verificar se é execução de garantia
            if (isset($validated['tipo_juros']) && $validated['tipo_juros'] === 'executar_garantia') {
                // Carregar empréstimo com garantias
                $parcela = Parcela::with('emprestimo.garantias')->findOrFail($validated['parcela_id']);
                $emprestimo = $parcela->emprestimo;

                // Verificar condições
                if (! $emprestimo->isEmpenho()) {
                    return back()->with('error', 'Apenas empréstimos do tipo empenho podem ter garantias executadas.')->withInput();
                }

                if (! $emprestimo->isAtivo()) {
                    return back()->with('error', 'Apenas empréstimos ativos podem ter garantias executadas.')->withInput();
                }
                // Permite executar garantia mesmo sem parcela atrasada (cliente pode desistir de pagar antes)

                $garantiasAtivas = $emprestimo->garantias->where('status', 'ativa');
                if ($garantiasAtivas->count() === 0) {
                    return back()->with('error', 'Não há garantias ativas para executar.')->withInput();
                }

                // Executar a primeira garantia ativa (ou todas, dependendo da regra de negócio)
                // Por enquanto, vamos executar apenas a primeira
                $garantia = $garantiasAtivas->first();

                $emprestimoService = app(\App\Modules\Loans\Services\EmprestimoService::class);
                $emprestimoService->executarGarantia(
                    $emprestimo->id,
                    $garantia->id,
                    auth()->id(),
                    $validated['observacoes_executar_garantia']
                );

                $returnTo = $request->input('return_to');
                $mensagem = 'Garantia executada com sucesso! O empréstimo foi finalizado automaticamente.';

                if ($returnTo === 'cobrancas') {
                    return redirect()->route('cobrancas.index')
                        ->with('success', $mensagem);
                } elseif ($returnTo === 'parcelas_atrasadas') {
                    return redirect()->route('parcelas.atrasadas')
                        ->with('success', $mensagem);
                } else {
                    return redirect()->route('emprestimos.show', $emprestimo->id)
                        ->with('success', $mensagem);
                }
            }

            // Verificar se é renovação
            if (isset($validated['tipo_juros']) && $validated['tipo_juros'] === 'renovacao') {
                // Carregar empréstimo com parcelas para verificar se pode renovar
                $parcela = Parcela::with('emprestimo')->findOrFail($validated['parcela_id']);
                $emprestimo = $parcela->emprestimo;

                // Carregar parcelas se não estiverem carregadas
                if (! $emprestimo->relationLoaded('parcelas')) {
                    $emprestimo->load('parcelas');
                }

                // Verificar condições de renovação
                if ($emprestimo->status === 'ativo'
                    && $emprestimo->numero_parcelas === 1) {

                    // Verificar se os juros já foram pagos
                    if ($emprestimo->jurosJaForamPagos()) {
                        return back()->with('error', 'Os juros deste empréstimo já foram pagos. Não é necessário renovar.')->withInput();
                    }

                    // Obter sub-opção de renovação escolhida (default: automático se não informado)
                    $tipoJurosRenovacao = $validated['tipo_juros_renovacao'] ?? 'automatico';
                    $taxaJurosRenovacaoManual = isset($validated['taxa_juros_renovacao_manual']) && $validated['taxa_juros_renovacao_manual'] > 0
                        ? (float) $validated['taxa_juros_renovacao_manual']
                        : null;
                    $valorJurosRenovacaoFixo = isset($validated['valor_juros_renovacao_fixo']) && $validated['valor_juros_renovacao_fixo'] > 0
                        ? (float) $validated['valor_juros_renovacao_fixo']
                        : null;
                    $valorRenovacaoAbate = null;
                    if ($tipoJurosRenovacao === 'com_abate') {
                        $valorRenovacaoAbate = isset($validated['valor_renovacao_abate']) ? (float) $validated['valor_renovacao_abate'] : null;
                        if ($valorRenovacaoAbate === null || $valorRenovacaoAbate <= 0) {
                            $valorRenovacaoAbate = is_numeric($validated['valor'] ?? null) ? (float) $validated['valor'] : $this->parseBrDecimal($validated['valor'] ?? 0);
                        }
                    }
                    $metodoPagamento = $validated['metodo'] ?? 'dinheiro';
                    $dataPagamento = $validated['data_pagamento'] ?? null;

                    $valorPrincipalParcela = $parcela->valor_amortizacao !== null && (float) $parcela->valor_amortizacao > 0
                        ? (float) $parcela->valor_amortizacao
                        : (float) $parcela->valor;
                    $valorParcelaTotalRenov = (float) $parcela->valor;

                    if ($tipoJurosRenovacao === 'com_abate' && $valorRenovacaoAbate !== null && $valorRenovacaoAbate < $valorPrincipalParcela) {
                        $solicitacao = \App\Modules\Loans\Models\SolicitacaoRenovacaoAbate::create([
                            'parcela_id' => $parcela->id,
                            'consultor_id' => auth()->id(),
                            'valor' => $valorRenovacaoAbate,
                            'valor_principal' => $valorPrincipalParcela,
                            'valor_parcela_total' => $valorParcelaTotalRenov,
                            'metodo' => $metodoPagamento,
                            'data_pagamento' => $dataPagamento ?? now()->format('Y-m-d'),
                            'comprovante_path' => $validated['comprovante_path'] ?? null,
                            'observacoes' => $validated['observacoes'] ?? null,
                            'status' => 'aguardando',
                            'empresa_id' => $parcela->empresa_id ?? $parcela->emprestimo->operacao->empresa_id ?? null,
                        ]);
                        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                        $operacaoId = (int) $emprestimo->operacao_id;
                        $dadosNotif = [
                            'tipo' => 'renovacao_abate_valor_inferior_pendente',
                            'titulo' => 'Renovação com abate (valor inferior ao principal) aguardando aprovação',
                            'mensagem' => sprintf('Empréstimo #%d - %s. Valor R$ %s (principal R$ %s). Aguardando em Liberações.', $emprestimo->id, NotificacaoClienteDisplayName::forEmprestimo($emprestimo), number_format($valorRenovacaoAbate, 2, ',', '.'), number_format($valorPrincipalParcela, 2, ',', '.')),
                            'url' => route('liberacoes.renovacao-abate'),
                            'dados' => ['solicitacao_id' => $solicitacao->id, 'emprestimo_id' => $emprestimo->id],
                        ];
                        $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
                        $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);

                        return redirect()->route('emprestimos.show', $emprestimo->id)
                            ->with('success', 'Solicitação de renovação com abate enviada para aprovação do gestor/administrador. Acompanhe em Liberações.');
                    }

                    $emprestimoService = app(\App\Modules\Loans\Services\EmprestimoService::class);
                    $novoEmprestimo = $emprestimoService->renovar(
                        $emprestimo->id,
                        $tipoJurosRenovacao !== 'com_abate',
                        $tipoJurosRenovacao === 'com_abate' ? null : $tipoJurosRenovacao,
                        $taxaJurosRenovacaoManual,
                        $valorJurosRenovacaoFixo,
                        $tipoJurosRenovacao === 'com_abate' ? $valorRenovacaoAbate : null,
                        $metodoPagamento,
                        $dataPagamento,
                        false
                    );

                    $returnTo = $request->input('return_to');
                    $mensagem = $tipoJurosRenovacao === 'com_abate'
                        ? 'Empréstimo renovado com abate! O pagamento foi registrado e o novo empréstimo foi gerado com o saldo devedor restante.'
                        : 'Empréstimo renovado com sucesso! O pagamento dos juros foi registrado automaticamente.';

                    if ($returnTo === 'cobrancas') {
                        return redirect()->route('cobrancas.index')
                            ->with('success', $mensagem);
                    } elseif ($returnTo === 'parcelas_atrasadas') {
                        return redirect()->route('parcelas.atrasadas')
                            ->with('success', $mensagem);
                    } else {
                        return redirect()->route('emprestimos.show', $novoEmprestimo->id)
                            ->with('success', $mensagem." Este empréstimo é a renovação do empréstimo #{$emprestimo->id}.");
                    }
                } else {
                    return back()->with('error', 'Não é possível renovar este empréstimo. Verifique se é mensal com 1 parcela e está ativo.')->withInput();
                }
            }

            $parcela = Parcela::with(['emprestimo.operacao', 'emprestimo.parcelas'])->findOrFail($validated['parcela_id']);
            $valorPagamento = is_numeric($validated['valor']) ? (float) $validated['valor'] : $this->parseBrDecimal($validated['valor'] ?? 0);
            $valorPrincipal = $parcela->valor_amortizacao !== null && (float) $parcela->valor_amortizacao > 0
                ? (float) $parcela->valor_amortizacao
                : (float) $parcela->valor;
            $valorParcelaTotal = (float) $parcela->valor;
            $tipoJurosForm = $validated['tipo_juros'] ?? 'nenhum';

            $faltaNominal = max(0, round($valorParcelaTotal - (float) ($parcela->valor_pago ?? 0), 2));
            $solicitouAdiantamento = $request->boolean('adiantamento_valor');

            if ($solicitouAdiantamento) {
                if (($validated['metodo'] ?? '') === Pagamento::METODO_PRODUTO_OBJETO) {
                    return back()->with('error', 'Adiantamento de valor não está disponível para pagamento em produto/objeto.')->withInput();
                }
                if (! $parcela->podeAdiantarValor()) {
                    return back()->with('error', 'Adiantamento de valor não está disponível para esta parcela.')->withInput();
                }
                if ($valorPagamento <= 0 || round($valorPagamento, 2) > round($faltaNominal, 2)) {
                    return back()->with('error', 'Informe um valor maior que zero e no máximo igual ao que falta pagar na parcela (R$ '.number_format($faltaNominal, 2, ',', '.').').')->withInput();
                }
                $obs = trim((string) ($validated['observacoes'] ?? ''));
                $validated['observacoes'] = 'Adiantamento de valor'.($obs !== '' ? ': '.$obs : '.');
                $validated['tipo_juros'] = 'nenhum';
                $validated['adiantamento_valor'] = true;
            } else {
                $pisoEfetivo = $tipoJurosForm === 'valor_inferior'
                    ? $valorPrincipal
                    : ($faltaNominal > 0 ? min($valorPrincipal, $faltaNominal) : $valorPrincipal);

                if (round($valorPagamento, 2) < round($pisoEfetivo, 2)) {
                    $msg = round($pisoEfetivo, 2) < round($valorPrincipal, 2)
                        ? 'O valor do pagamento não pode ser menor que o mínimo permitido (R$ '.number_format($pisoEfetivo, 2, ',', '.').'; principal da parcela R$ '.number_format($valorPrincipal, 2, ',', '.').').'
                        : 'O valor do pagamento não pode ser menor que o principal (R$ '.number_format($pisoEfetivo, 2, ',', '.').').';

                    return back()->with('error', $msg)->withInput();
                }
            }

            $isConsultor = empty(auth()->user()->getOperacoesIdsOndeTemPapel(['gestor', 'administrador']));
            $isProdutoObjetoMetodo = ($validated['metodo'] ?? '') === \App\Modules\Loans\Models\Pagamento::METODO_PRODUTO_OBJETO;
            $isRenovacao = ($validated['tipo_juros'] ?? '') === 'renovacao';
            $isExecutarGarantia = ($validated['tipo_juros'] ?? '') === 'executar_garantia';

            $emprestimoPg = $parcela->emprestimo;
            $isDiariaVariasParcelas = $emprestimoPg->isFrequenciaDiaria() && (int) $emprestimoPg->numero_parcelas > 1
                && ! $isProdutoObjetoMetodo && ! $isRenovacao && ! $isExecutarGarantia && $tipoJurosForm !== 'valor_inferior';

            if ($isDiariaVariasParcelas) {
                $diariaSvc = app(DiariaPagamentoAntecipacaoService::class);
                $dadosBase = array_merge($validated, ['consultor_id' => $validated['consultor_id']]);
                $valorDevido = $diariaSvc->valorDevidoNoPagamento($parcela, $dadosBase);

                if ($parcela->hasSolicitacaoDiariaParcialPendente()) {
                    return back()->with('error', 'Já existe uma solicitação de pagamento parcial (diária) aguardando aprovação para esta parcela.')->withInput();
                }

                if (round($valorPagamento, 2) < round($valorDevido, 2)) {
                    $faltante = round($valorDevido - $valorPagamento, 2);
                    if ($isConsultor) {
                        $this->pagamentoService->criarPagamentoDiariaParcialAguardandoAprovacao([
                            'parcela_id' => $parcela->id,
                            'consultor_id' => $validated['consultor_id'],
                            'valor' => $valorPagamento,
                            'valor_recebido' => $valorPagamento,
                            'valor_esperado' => $valorDevido,
                            'faltante' => $faltante,
                            'metodo' => $validated['metodo'],
                            'data_pagamento' => $validated['data_pagamento'],
                            'comprovante_path' => $validated['comprovante_path'] ?? null,
                            'observacoes' => $validated['observacoes'] ?? null,
                        ]);
                        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                        $operacaoId = (int) $emprestimoPg->operacao_id;
                        $clienteNome = NotificacaoClienteDisplayName::forEmprestimo($emprestimoPg);
                        $dadosNotif = [
                            'tipo' => 'pagamento_diaria_parcial_pendente',
                            'titulo' => 'Pagamento parcial (diária) – aguardando aprovação',
                            'mensagem' => sprintf(
                                'Empréstimo #%d – %s. Parcela #%d. Recebido R$ %s | Devido R$ %s. A parcela permanece em atraso até a aprovação.',
                                $emprestimoPg->id,
                                $clienteNome,
                                $parcela->numero,
                                number_format($valorPagamento, 2, ',', '.'),
                                number_format($valorDevido, 2, ',', '.')
                            ),
                            'url' => route('liberacoes.diaria-parcial'),
                            'dados' => ['emprestimo_id' => $emprestimoPg->id, 'parcela_id' => $parcela->id],
                        ];
                        $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
                        $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);
                    } else {
                        $this->pagamentoService->registrarDiarioParcialGestorDireto([
                            'parcela_id' => $parcela->id,
                            'consultor_id' => $validated['consultor_id'],
                            'valor' => $valorPagamento,
                            'valor_esperado' => $valorDevido,
                            'metodo' => $validated['metodo'],
                            'data_pagamento' => $validated['data_pagamento'],
                            'comprovante_path' => $validated['comprovante_path'] ?? null,
                            'observacoes' => $validated['observacoes'] ?? null,
                        ]);
                    }
                    $returnTo = $request->input('return_to');
                    $msg = $isConsultor
                        ? 'Pagamento parcial registrado e enviado para aprovação. A parcela permanece em atraso até o gestor ou administrador aprovar em Liberações (Diária parcial).'
                        : 'Pagamento parcial aplicado. A parcela foi marcada como paga (parcial) e o faltante foi acrescido à última parcela.';
                    if ($returnTo === 'cobrancas') {
                        return redirect()->route('cobrancas.index')->with('success', $msg);
                    }
                    if ($returnTo === 'parcelas_atrasadas') {
                        return redirect()->route('parcelas.atrasadas')->with('success', $msg);
                    }

                    return redirect()->route('emprestimos.show', $parcela->emprestimo_id)->with('success', $msg);
                }

                $diariaSvc->executarAntecipacao($parcela, $valorPagamento, $dadosBase);
                $returnTo = $request->input('return_to');
                $msgOk = 'Pagamento registrado com sucesso (antecipação nas parcelas finais quando aplicável).';
                if ($returnTo === 'cobrancas') {
                    return redirect()->route('cobrancas.index')->with('success', $msgOk);
                }
                if ($returnTo === 'parcelas_atrasadas') {
                    return redirect()->route('parcelas.atrasadas')->with('success', $msgOk);
                }

                return redirect()->route('emprestimos.show', $parcela->emprestimo_id)->with('success', $msgOk);
            }

            // Se consultor está pagando valor inferior ao devido (juros do contrato reduzido), exige aprovação de gestor/admin
            if ($isConsultor && ! $isProdutoObjetoMetodo && ! $isRenovacao && ! $isExecutarGarantia && ! $isDiariaVariasParcelas && $valorPagamento >= $valorPrincipal && $valorPagamento < $valorParcelaTotal) {
                $solicitacao = \App\Modules\Loans\Models\SolicitacaoPagamentoJurosContratoReduzido::create([
                    'parcela_id' => $parcela->id,
                    'consultor_id' => auth()->id(),
                    'valor' => $valorPagamento,
                    'valor_principal' => $valorPrincipal,
                    'valor_parcela_total' => $valorParcelaTotal,
                    'metodo' => $validated['metodo'],
                    'data_pagamento' => $validated['data_pagamento'],
                    'comprovante_path' => $validated['comprovante_path'] ?? null,
                    'observacoes' => $validated['observacoes'] ?? null,
                    'status' => 'aguardando',
                    'empresa_id' => $parcela->empresa_id ?? $parcela->emprestimo->operacao->empresa_id ?? null,
                ]);
                $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                $emprestimo = $parcela->emprestimo;
                $operacaoId = (int) $emprestimo->operacao_id;
                $clienteNome = NotificacaoClienteDisplayName::forEmprestimo($emprestimo);
                $dadosNotif = [
                    'tipo' => 'pagamento_juros_contrato_reduzido_pendente',
                    'titulo' => 'Pagamento com valor inferior (juros do contrato reduzido) – aguardando aprovação',
                    'mensagem' => sprintf('Empréstimo #%d – %s. Parcela #%d. Valor solicitado: R$ %s | Valor da parcela: R$ %s.', $emprestimo->id, $clienteNome, $parcela->numero, number_format($valorPagamento, 2, ',', '.'), number_format($valorParcelaTotal, 2, ',', '.')),
                    'url' => route('liberacoes.juros-contrato-reduzido'),
                    'dados' => ['solicitacao_id' => $solicitacao->id, 'emprestimo_id' => $emprestimo->id],
                ];
                $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
                $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);
                $returnTo = $request->input('return_to');
                $msg = 'Pagamento com valor inferior ao devido (juros do contrato reduzido) foi enviado para aprovação do gestor ou administrador em Liberações.';
                if ($returnTo === 'cobrancas') {
                    return redirect()->route('cobrancas.index')->with('success', $msg);
                }
                if ($returnTo === 'parcelas_atrasadas') {
                    return redirect()->route('parcelas.atrasadas')->with('success', $msg);
                }

                return redirect()->route('emprestimos.show', $parcela->emprestimo_id)->with('success', $msg);
            }

            // Se consultor está pagando juros de atraso abaixo do devido, exige aprovação de gestor/admin
            if ($isConsultor && ! $isProdutoObjetoMetodo && ! $isDiariaVariasParcelas && $parcela->isAtrasada()) {
                $dataPagamentoRef = isset($validated['data_pagamento']) ? $validated['data_pagamento'] : null;
                $jurosDevido = $this->pagamentoService->getJurosDevidoAutomatico($parcela, $dataPagamentoRef);
                if ($jurosDevido > 0) {
                    $dadosJuros = $this->pagamentoService->getDadosJuros($parcela, $validated);
                    $valorJurosSolicitado = (float) ($dadosJuros['valor_juros'] ?? 0);
                    if ($valorJurosSolicitado < $jurosDevido) {
                        $solicitacao = \App\Modules\Loans\Models\SolicitacaoPagamentoJurosParcial::create([
                            'parcela_id' => $parcela->id,
                            'consultor_id' => auth()->id(),
                            'valor' => $validated['valor'],
                            'metodo' => $validated['metodo'],
                            'data_pagamento' => $validated['data_pagamento'],
                            'comprovante_path' => $validated['comprovante_path'] ?? null,
                            'observacoes' => $validated['observacoes'] ?? null,
                            'tipo_juros' => $dadosJuros['tipo_juros'] ?? $validated['tipo_juros'] ?? null,
                            'taxa_juros_aplicada' => $dadosJuros['taxa_juros_aplicada'] ?? null,
                            'valor_juros_solicitado' => $valorJurosSolicitado,
                            'valor_juros_devido' => $jurosDevido,
                            'status' => 'aguardando',
                            'empresa_id' => $parcela->empresa_id ?? $parcela->emprestimo->operacao->empresa_id ?? null,
                        ]);
                        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                        $emprestimo = $parcela->emprestimo;
                        $operacaoId = (int) $emprestimo->operacao_id;
                        $clienteNome = NotificacaoClienteDisplayName::forEmprestimo($emprestimo);
                        $dadosNotif = [
                            'tipo' => 'pagamento_juros_parcial_pendente',
                            'titulo' => 'Pagamento com juros abaixo do devido – aguardando aprovação',
                            'mensagem' => sprintf('Empréstimo #%d – %s. Parcela #%d. Juros solicitado: R$ %s | Devido: R$ %s.', $emprestimo->id, $clienteNome, $parcela->numero, number_format($valorJurosSolicitado, 2, ',', '.'), number_format($jurosDevido, 2, ',', '.')),
                            'url' => route('liberacoes.juros-parcial'),
                            'dados' => ['solicitacao_id' => $solicitacao->id, 'emprestimo_id' => $emprestimo->id],
                        ];
                        $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
                        $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);
                        $returnTo = $request->input('return_to');
                        $msg = 'Pagamento com juros abaixo do devido foi enviado para aprovação do gestor ou administrador em Liberações.';
                        if ($returnTo === 'cobrancas') {
                            return redirect()->route('cobrancas.index')->with('success', $msg);
                        }
                        if ($returnTo === 'parcelas_atrasadas') {
                            return redirect()->route('parcelas.atrasadas')->with('success', $msg);
                        }

                        return redirect()->route('emprestimos.show', $parcela->emprestimo_id)->with('success', $msg);
                    }
                }
            }

            $validated['valor'] = $valorPagamento;
            $pagamento = $this->pagamentoService->registrar($validated);

            // Obter o empréstimo da parcela para redirecionar
            $parcela = Parcela::findOrFail($validated['parcela_id']);
            $emprestimoId = $parcela->emprestimo_id;

            $isProdutoObjeto = ($validated['metodo'] ?? '') === \App\Modules\Loans\Models\Pagamento::METODO_PRODUTO_OBJETO;
            $mensagemSucesso = $isProdutoObjeto
                ? 'Pagamento em produto/objeto registrado. Ele será creditado na parcela após aceite de um gestor ou administrador em Liberações.'
                : 'Pagamento registrado com sucesso!';

            // Verificar se há um return_to específico
            $returnTo = $request->input('return_to');

            if ($returnTo === 'cobrancas') {
                return redirect()->route('cobrancas.index')
                    ->with('success', $mensagemSucesso);
            } elseif ($returnTo === 'parcelas_atrasadas') {
                return redirect()->route('parcelas.atrasadas')
                    ->with('success', $mensagemSucesso);
            } else {
                // Por padrão, redireciona para a tela do empréstimo
                return redirect()->route('emprestimos.show', $emprestimoId)
                    ->with('success', $mensagemSucesso);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $mensagem)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao registrar pagamento: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Formulário para quitar todas as parcelas diárias de um empréstimo (um pagamento, um comprovante).
     */
    public function quitarDiariasCreate($emprestimo): View|RedirectResponse
    {
        $emprestimo = \App\Modules\Loans\Models\Emprestimo::with(['parcelas', 'operacao', 'cliente'])->findOrFail($emprestimo);

        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $emprestimo->operacao_id, $opsIds, true)) {
                abort(403, 'Sem acesso a esta operação.');
            }
        }

        if (! $emprestimo->isFrequenciaDiaria()) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'Quitação em lote só está disponível para empréstimos de frequência diária.');
        }

        $parcelasPendentes = $emprestimo->parcelas
            ->filter(fn ($p) => $p->faltaPagar() > 0)
            ->sortBy('numero')
            ->values();
        if ($parcelasPendentes->isEmpty()) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'Não há parcelas pendentes para quitar.');
        }

        $operacao = $emprestimo->operacao;
        $dataRef = now();
        $totalPrincipal = round($parcelasPendentes->sum(fn ($p) => $p->faltaPagar()), 2);
        $jurosAutomaticoTotal = 0.0;
        if ($operacao->taxa_juros_atraso > 0) {
            $taxa = (float) $operacao->taxa_juros_atraso;
            $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
            foreach ($parcelasPendentes as $p) {
                $dias = $p->calcularDiasAtraso($dataRef);
                $falta = $p->faltaPagar();
                $jurosAutomaticoTotal += $tipoCalculo === 'por_dia' ? $falta * ($taxa / 100) * $dias : $falta * ($taxa / 100) * ($dias / 30);
            }
            $jurosAutomaticoTotal = round($jurosAutomaticoTotal, 2);
        }

        $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
        $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimo);

        return view('pagamentos.quitar-diarias', [
            'emprestimo' => $emprestimo,
            'parcelasPendentes' => $parcelasPendentes,
            'totalPrincipal' => $totalPrincipal,
            'jurosAutomaticoTotal' => $jurosAutomaticoTotal,
            'operacao' => $operacao,
            'saldoDevedor' => $saldoDevedor,
            'valorEmprestado' => (float) $emprestimo->valor_total,
            'nomeClienteExibicao' => ClienteNomeExibicao::forEmprestimo($emprestimo),
        ]);
    }

    /**
     * Registrar quitação de todas as parcelas diárias.
     * Se informar valor_solicitado inferior ao total devido, envia para aprovação (como no mensal).
     */
    public function quitarDiariasStore(Request $request, $emprestimo): RedirectResponse
    {
        $emprestimoModel = \App\Modules\Loans\Models\Emprestimo::findOrFail($emprestimo);
        $user = auth()->user();
        if (! $user->isSuperAdmin()) {
            $opsIds = $user->getOperacoesIds();
            if (empty($opsIds) || ! in_array((int) $emprestimoModel->operacao_id, $opsIds, true)) {
                return back()->with('error', 'Sem acesso a esta operação.')->withInput();
            }
        }

        $validated = $request->validate([
            'metodo' => 'required|in:dinheiro,pix,transferencia,outro',
            'data_pagamento' => 'required|date',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'observacoes' => 'nullable|string',
            'tipo_juros' => 'required|in:nenhum,automatico,manual,fixo',
            'taxa_juros_manual' => 'nullable|numeric|min:0|max:100|required_if:tipo_juros,manual',
            'valor_juros_fixo' => 'nullable|numeric|min:0|required_if:tipo_juros,fixo',
            'valor_solicitado' => 'nullable|string|max:50',
            'motivo_desconto' => 'nullable|string|min:10|max:1000',
        ], [
            'motivo_desconto.min' => 'O motivo é obrigatório quando o valor a pagar é inferior ao total (mín. 10 caracteres).',
        ]);

        if ($request->hasFile('comprovante')) {
            try {
                $validated['comprovante_path'] = $request->file('comprovante')->store('comprovantes', 'public');
            } catch (\Exception $e) {
                \Log::error('Erro upload comprovante quitação diárias: '.$e->getMessage());

                return back()->with('error', 'Erro ao enviar comprovante.')->withInput();
            }
        } else {
            $validated['comprovante_path'] = null;
        }

        $validated['consultor_id'] = auth()->id();

        $valorSolicitado = null;
        if (isset($validated['valor_solicitado']) && $validated['valor_solicitado'] !== '' && $validated['valor_solicitado'] !== null) {
            $valorSolicitado = is_numeric($validated['valor_solicitado']) ? (float) $validated['valor_solicitado'] : $this->parseBrDecimal($validated['valor_solicitado']);
        }
        if ($valorSolicitado !== null && $valorSolicitado <= 0) {
            $valorSolicitado = null;
        }
        $totalDue = $this->pagamentoService->calcularTotalQuitacaoDiarias((int) $emprestimo, $validated);
        $quitacaoService = app(\App\Modules\Loans\Services\QuitacaoService::class);
        $saldoDevedor = $quitacaoService->getSaldoDevedor($emprestimoModel);

        if ($valorSolicitado !== null && $valorSolicitado < $saldoDevedor) {
            if (empty(trim($validated['motivo_desconto'] ?? ''))) {
                return back()->with('error', 'Ao pagar valor inferior ao saldo devedor, o motivo é obrigatório (mínimo 10 caracteres).')->withInput();
            }
            try {
                $quitacaoService->solicitarQuitacaoComDesconto(
                    $emprestimoModel,
                    [
                        'valor_solicitado' => $valorSolicitado,
                        'metodo' => $validated['metodo'],
                        'data_pagamento' => $validated['data_pagamento'],
                        'comprovante_path' => $validated['comprovante_path'] ?? null,
                        'observacoes' => $validated['observacoes'] ?? null,
                        'motivo_desconto' => $validated['motivo_desconto'],
                    ]
                );

                return redirect()->route('emprestimos.show', $emprestimo)
                    ->with('success', 'Solicitação de quitação com valor inferior enviada. Aguarde a aprovação do gestor ou administrador em Liberações.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

                return back()->with('error', $msg)->withInput();
            } catch (\Exception $e) {
                return back()->with('error', 'Erro ao enviar solicitação: '.$e->getMessage())->withInput();
            }
        }

        try {
            $this->pagamentoService->registrarQuitacaoDiarias((int) $emprestimo, $validated);

            return redirect()->route('emprestimos.show', $emprestimo)
                ->with('success', 'Todas as parcelas diárias foram quitadas com sucesso. Um único comprovante foi associado a todos os pagamentos.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $msg)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao quitar parcelas: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Formulário para pagar mais de uma parcela de uma vez (checkboxes + juros + comprovante único ou por parcela).
     */
    public function multiParcelasCreate($emprestimo): View|RedirectResponse
    {
        $emprestimo = \App\Modules\Loans\Models\Emprestimo::with(['parcelas', 'operacao', 'cliente'])->findOrFail($emprestimo);

        if (! $emprestimo->isAtivo()) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'O empréstimo não está ativo.');
        }

        $parcelasAbertas = $emprestimo->parcelas->filter(function ($p) {
            if ($p->isQuitada()) {
                return false;
            }
            $falta = (float) $p->valor - (float) ($p->valor_pago ?? 0);

            return $falta > 0;
        })->sortBy('numero')->values();

        if ($parcelasAbertas->count() < 2) {
            return redirect()->route('emprestimos.show', $emprestimo->id)
                ->with('error', 'É necessário ter pelo menos duas parcelas em aberto para usar o pagamento em lote.');
        }

        $operacao = $emprestimo->operacao;

        return view('pagamentos.multi-parcelas', [
            'emprestimo' => $emprestimo,
            'parcelasAbertas' => $parcelasAbertas,
            'operacao' => $operacao,
            'nomeClienteExibicao' => ClienteNomeExibicao::forEmprestimo($emprestimo),
        ]);
    }

    /**
     * Registrar pagamento de múltiplas parcelas selecionadas.
     */
    public function multiParcelasStore(Request $request, $emprestimo): RedirectResponse
    {
        $validated = $request->validate([
            'parcela_ids' => 'required|array|min:2',
            'parcela_ids.*' => 'integer|exists:parcelas,id',
            'modo_comprovante' => 'required|in:unico,por_parcela',
            'metodo' => 'required|in:dinheiro,pix,transferencia,outro',
            'data_pagamento' => 'required|date',
            'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'observacoes' => 'nullable|string',
            'tipo_juros' => 'required|in:nenhum,automatico,manual,fixo',
            'taxa_juros_manual' => 'nullable|numeric|min:0|max:100|required_if:tipo_juros,manual',
            'valor_juros_fixo' => 'nullable|string|max:50|required_if:tipo_juros,fixo',
        ]);

        $validated['comprovantes_por_parcela'] = [];
        $validated['comprovante_path'] = null;

        if ($validated['modo_comprovante'] === 'unico') {
            if ($request->hasFile('comprovante')) {
                try {
                    $validated['comprovante_path'] = $request->file('comprovante')->store('comprovantes', 'public');
                } catch (\Exception $e) {
                    \Log::error('Erro upload comprovante multi-parcelas: '.$e->getMessage());

                    return back()->with('error', 'Erro ao enviar comprovante.')->withInput();
                }
            }
        } else {
            foreach ($validated['parcela_ids'] as $pid) {
                $key = 'comprovante_parcela.'.$pid;
                if (! $request->hasFile($key)) {
                    throw ValidationException::withMessages([
                        $key => 'Anexe o comprovante da parcela selecionada (obrigatório neste modo).',
                    ]);
                }
                $file = $request->file($key);
                if (! $file->isValid()) {
                    throw ValidationException::withMessages([
                        $key => 'Arquivo de comprovante inválido.',
                    ]);
                }
                $ext = strtolower($file->getClientOriginalExtension());
                if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                    throw ValidationException::withMessages([
                        $key => 'Use PDF, JPG, JPEG ou PNG (máx. 2MB).',
                    ]);
                }
                if ($file->getSize() > 2048 * 1024) {
                    throw ValidationException::withMessages([
                        $key => 'O arquivo deve ter no máximo 2MB.',
                    ]);
                }
                try {
                    $validated['comprovantes_por_parcela'][(int) $pid] = $file->store('comprovantes', 'public');
                } catch (\Exception $e) {
                    \Log::error('Erro upload comprovante multi-parcelas (por parcela): '.$e->getMessage());

                    return back()->with('error', 'Erro ao enviar um dos comprovantes.')->withInput();
                }
            }
        }

        if (! empty($validated['valor_juros_fixo']) && $validated['tipo_juros'] === 'fixo') {
            $validated['valor_juros_fixo'] = is_numeric($validated['valor_juros_fixo'])
                ? (float) $validated['valor_juros_fixo']
                : $this->parseBrDecimal($validated['valor_juros_fixo']);
        }

        $validated['consultor_id'] = auth()->id();

        $msgSucesso = $validated['modo_comprovante'] === 'por_parcela'
            ? 'Pagamento registrado. Cada parcela recebeu o respectivo comprovante.'
            : 'Pagamento registrado para as parcelas selecionadas. O comprovante único foi associado a todos os pagamentos.';

        try {
            $this->pagamentoService->registrarPagamentoMultiplasParcelas((int) $emprestimo, $validated['parcela_ids'], $validated);

            return redirect()->route('emprestimos.show', $emprestimo)->with('success', $msgSucesso);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $msg)->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao registrar pagamento: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Converte valor em formato BR (1.234,56) para float.
     */
    /**
     * Anexar comprovante a um pagamento já registrado (somente se ainda não tiver).
     * Não permite editar/substituir comprovante existente.
     */
    public function anexarComprovante(Request $request, int $id): RedirectResponse
    {
        $pagamento = Pagamento::with('parcela')->findOrFail($id);

        if ($pagamento->hasComprovante()) {
            return back()->with('error', 'Este pagamento já possui comprovante. Não é possível substituir.');
        }

        $request->validate([
            'comprovante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        try {
            $comprovantePath = $request->file('comprovante')->store('comprovantes', 'public');
            $this->pagamentoService->anexarComprovante($id, $comprovantePath);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensagem = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return back()->with('error', $mensagem)->withInput();
        }

        $emprestimoId = $pagamento->parcela->emprestimo_id;

        return redirect()->route('emprestimos.show', $emprestimoId)
            ->with('success', 'Comprovante anexado ao pagamento com sucesso.');
    }

    private function parseBrDecimal($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $str = preg_replace('/\s+/', '', (string) $value);
        $str = str_replace('.', '', $str);
        $str = str_replace(',', '.', $str);

        return (float) $str;
    }
}
