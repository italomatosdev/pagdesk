<?php

namespace App\Modules\Loans\Services;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\PagamentoProdutoObjetoItem;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Models\SolicitacaoPagamentoDiariaParcial;
use App\Support\NotificacaoClienteDisplayName;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagamentoService
{
    use Auditable;

    protected CashService $cashService;

    public function __construct(CashService $cashService)
    {
        $this->cashService = $cashService;
    }

    /**
     * Registrar pagamento
     */
    public function registrar(array $dados): Pagamento
    {
        return DB::transaction(function () use ($dados) {
            // Lock da parcela para evitar processamento simultâneo
            $parcela = Parcela::lockForUpdate()->with('emprestimo.liberacao')->findOrFail($dados['parcela_id']);
            $emprestimo = $parcela->emprestimo;

            // VALIDAÇÃO 1: Empréstimo deve estar ATIVO
            if (! $emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível registrar pagamento. O empréstimo precisa estar ATIVO. Status atual: '.$emprestimo->status,
                ]);
            }

            // VALIDAÇÃO 2: Verificar se dinheiro foi liberado e pago ao cliente
            // EXCEÇÃO: Empréstimos renovados não precisam de liberação (dinheiro já foi pago no empréstimo original)
            if (! $emprestimo->isRenovacao()) {
                $liberacao = $emprestimo->liberacao;
                if (! $liberacao) {
                    throw ValidationException::withMessages([
                        'liberacao' => 'Não é possível registrar pagamento. O dinheiro ainda não foi liberado pelo gestor.',
                    ]);
                }

                if ($liberacao->status !== 'pago_ao_cliente') {
                    $statusLiberacao = $liberacao->status;
                    $mensagem = match ($statusLiberacao) {
                        'aguardando' => 'Não é possível registrar pagamento. O dinheiro ainda não foi liberado pelo gestor.',
                        'liberado' => 'Não é possível registrar pagamento. Você precisa confirmar o pagamento ao cliente primeiro.',
                        default => 'Não é possível registrar pagamento. Status da liberação: '.$statusLiberacao
                    };
                    throw ValidationException::withMessages([
                        'liberacao' => $mensagem,
                    ]);
                }
            }

            // VALIDAÇÃO 3: Verificar se a parcela já está totalmente paga ou quitada por garantia
            if ($parcela->isQuitada()) {
                $mensagem = $parcela->isQuitadaGarantia()
                    ? 'Esta parcela foi quitada via execução de garantia. Não é possível registrar novos pagamentos.'
                    : 'Esta parcela já está totalmente paga.';

                throw ValidationException::withMessages([
                    'parcela' => $mensagem,
                ]);
            }

            // VALIDAÇÃO 4: Verificar se o consultor é o dono do empréstimo (ou admin/gestor na operação)
            $user = auth()->user();
            if (! $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
                throw ValidationException::withMessages([
                    'permissao' => 'Você não tem permissão para registrar pagamento deste empréstimo.',
                ]);
            }

            // Calcular juros se aplicável
            $dadosJuros = $this->calcularJuros($parcela, $dados);
            $metodo = $dados['metodo'] ?? 'dinheiro';
            $isProdutoObjeto = $metodo === Pagamento::METODO_PRODUTO_OBJETO;

            $createData = [
                'parcela_id' => $dados['parcela_id'],
                'consultor_id' => $dados['consultor_id'],
                'valor' => $dados['valor'],
                'metodo' => $metodo,
                'data_pagamento' => $dados['data_pagamento'] ?? Carbon::today(),
                'comprovante_path' => $dados['comprovante_path'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'tipo_juros' => $dadosJuros['tipo_juros'] ?? null,
                'taxa_juros_aplicada' => $dadosJuros['taxa_juros_aplicada'] ?? null,
                'valor_juros' => $dadosJuros['valor_juros'] ?? 0,
                'aceite_gestor_id' => null,
                'aceite_gestor_em' => null,
                'lote_id' => $dados['lote_id'] ?? null,
                'aguardando_aprovacao_diaria_parcial' => ! empty($dados['aguardando_aprovacao_diaria_parcial']),
            ];
            $itens = $dados['itens'] ?? null;
            if ($isProdutoObjeto && empty($itens)) {
                // Fluxo antigo: dados no próprio pagamento
                $createData['produto_nome'] = $dados['produto_nome'] ?? null;
                $createData['produto_descricao'] = $dados['produto_descricao'] ?? null;
                $createData['produto_valor'] = isset($dados['produto_valor']) ? (float) $dados['produto_valor'] : null;
                $createData['produto_imagens'] = $dados['produto_imagens'] ?? [];
            }
            $pagamento = Pagamento::create($createData);

            // Itens produto/objeto (fluxo novo: 1 pagamento = N itens)
            if ($isProdutoObjeto && ! empty($itens)) {
                foreach ($itens as $ordem => $item) {
                    PagamentoProdutoObjetoItem::create([
                        'pagamento_id' => $pagamento->id,
                        'nome' => $item['nome'] ?? '',
                        'descricao' => $item['descricao'] ?? null,
                        'valor_estimado' => $item['valor_estimado'] ?? null,
                        'quantidade' => (int) ($item['quantidade'] ?? 1),
                        'imagens' => $item['imagens'] ?? [],
                        'ordem' => $ordem,
                    ]);
                }
            }

            // Pagamento em produto/objeto: não atualiza parcela nem gera caixa; aguarda aceite de gestor/adm
            if ($isProdutoObjeto) {
                self::auditar('registrar_pagamento_produto_objeto', $pagamento, null, $pagamento->toArray());
                $emprestimo = $parcela->emprestimo;
                $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                $titulo = 'Pagamento em produto/objeto aguardando aceite';
                $mensagem = sprintf(
                    'Empréstimo #%d - %s. Valor R$ %s. Aguardando aceite em Liberações.',
                    $emprestimo->id,
                    NotificacaoClienteDisplayName::forEmprestimo($emprestimo),
                    number_format($pagamento->valor, 2, ',', '.')
                );
                $operacaoId = (int) $emprestimo->operacao_id;
                $dadosNotif = [
                    'tipo' => 'pagamento_produto_objeto_pendente',
                    'titulo' => $titulo,
                    'mensagem' => $mensagem,
                    'url' => route('liberacoes.pagamentos-produto-objeto'),
                    'dados' => ['pagamento_id' => $pagamento->id, 'emprestimo_id' => $emprestimo->id],
                ];
                $notificacaoService->criarParaRoleComOperacao('gestor', $operacaoId, $dadosNotif);
                $notificacaoService->criarParaRoleComOperacao('administrador', $operacaoId, $dadosNotif);

                return $pagamento->fresh();
            }

            // Atualizar valor pago da parcela diretamente
            // O valor do pagamento já inclui os juros
            $novoValorPago = $parcela->valor_pago + $dados['valor'];
            $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::today());

            // Verificar se está totalmente paga: valor alcançou o total OU é pagamento aprovado "valor inferior" OU renovação com abate
            $encerraValorInferior = ! empty($dados['encerra_parcela_valor_inferior']);
            $encerraRenovacaoAbate = ! empty($dados['encerra_parcela_renovacao_abate']);
            $estaTotalmentePaga = $encerraValorInferior || $encerraRenovacaoAbate || ($novoValorPago >= $parcela->valor);

            // Atualizar parcela
            $parcela->update([
                'valor_pago' => $novoValorPago,
                'data_pagamento' => $dataPagamento,
                'status' => $estaTotalmentePaga ? 'paga' : 'pendente',
                'dias_atraso' => 0, // Resetar atraso quando há pagamento
            ]);

            // Criar movimentação de caixa (entrada) — produto/objeto não gera caixa
            $this->criarMovimentacaoCaixa($pagamento);

            // Auditoria
            self::auditar('registrar_pagamento', $pagamento, null, $pagamento->toArray());

            // Verificar se todas as parcelas foram pagas e finalizar empréstimo
            $this->verificarFinalizacaoEmprestimo($emprestimo);

            return $pagamento->fresh();
        });
    }

    /**
     * Calcula o total que seria pago na quitação diária (soma parcelas + juros conforme opções).
     * Usado para comparar com valor_solicitado: se valor inferior, envia para aprovação (como no mensal).
     */
    public function calcularTotalQuitacaoDiarias(int $emprestimoId, array $dados): float
    {
        $emprestimo = \App\Modules\Loans\Models\Emprestimo::with(['parcelas', 'operacao'])->findOrFail($emprestimoId);
        $parcelasPendentes = $emprestimo->parcelas->filter(fn (Parcela $p) => $p->faltaPagar() > 0)->values();
        if ($parcelasPendentes->isEmpty()) {
            return 0.0;
        }
        $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::today());
        $tipoJuros = $dados['tipo_juros'] ?? 'nenhum';
        $operacao = $emprestimo->operacao;
        $totalFalta = $parcelasPendentes->sum(fn (Parcela $p) => $p->faltaPagar());
        $valorJurosPorParcela = [];
        if ($tipoJuros === 'nenhum' || empty($tipoJuros)) {
            foreach ($parcelasPendentes as $p) {
                $valorJurosPorParcela[$p->id] = 0.0;
            }
        } elseif ($tipoJuros === 'fixo') {
            $valorJurosFixoTotal = (float) ($dados['valor_juros_fixo'] ?? 0);
            foreach ($parcelasPendentes as $p) {
                $faltaP = $p->faltaPagar();
                $valorJurosPorParcela[$p->id] = $totalFalta > 0
                    ? round($valorJurosFixoTotal * ($faltaP / $totalFalta), 2)
                    : 0.0;
            }
            $somaJuros = array_sum($valorJurosPorParcela);
            if (abs($somaJuros - $valorJurosFixoTotal) > 0.02 && $parcelasPendentes->isNotEmpty()) {
                $ultimoId = $parcelasPendentes->last()->id;
                $valorJurosPorParcela[$ultimoId] = round(($valorJurosPorParcela[$ultimoId] ?? 0) + ($valorJurosFixoTotal - $somaJuros), 2);
            }
        } else {
            $taxa = $tipoJuros === 'automatico'
                ? (float) ($operacao->taxa_juros_atraso ?? 0)
                : (float) ($dados['taxa_juros_manual'] ?? 0);
            $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
            foreach ($parcelasPendentes as $p) {
                $diasAtraso = $p->calcularDiasAtraso($dataPagamento);
                $faltaP = $p->faltaPagar();
                $valorJurosPorParcela[$p->id] = $tipoCalculo === 'por_dia'
                    ? round($faltaP * ($taxa / 100) * $diasAtraso, 2)
                    : round($faltaP * ($taxa / 100) * ($diasAtraso / 30), 2);
            }
        }
        $total = 0.0;
        foreach ($parcelasPendentes as $parcela) {
            $total += $parcela->faltaPagar() + ($valorJurosPorParcela[$parcela->id] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Registrar quitação de todas as parcelas diárias de um empréstimo em um único ato.
     * Um comprovante único vale para todos os pagamentos; opções de juros: sem juros ou com juros (automático, manual, fixo).
     *
     * @param  array  $dados  metodo, data_pagamento, comprovante_path, observacoes, tipo_juros, taxa_juros_manual?, valor_juros_fixo?, consultor_id
     * @return array Lista de Pagamento criados
     */
    public function registrarQuitacaoDiarias(int $emprestimoId, array $dados): array
    {
        return DB::transaction(function () use ($emprestimoId, $dados) {
            $emprestimo = \App\Modules\Loans\Models\Emprestimo::with(['parcelas', 'operacao', 'liberacao'])->lockForUpdate()->findOrFail($emprestimoId);

            if (! $emprestimo->isFrequenciaDiaria()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Quitação em lote só é permitida para empréstimos de frequência diária.',
                ]);
            }

            if (! $emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'O empréstimo não está ativo.',
                ]);
            }

            $liberacao = $emprestimo->liberacao;
            if (! $emprestimo->isRenovacao() && (! $liberacao || $liberacao->status !== 'pago_ao_cliente')) {
                throw ValidationException::withMessages([
                    'liberacao' => 'O dinheiro do empréstimo ainda não foi liberado/pago ao cliente.',
                ]);
            }

            $parcelasPendentes = $emprestimo->parcelas->filter(fn (Parcela $p) => $p->faltaPagar() > 0)->sortBy('numero')->values();
            if ($parcelasPendentes->isEmpty()) {
                throw ValidationException::withMessages([
                    'parcelas' => 'Não há parcelas pendentes para quitar.',
                ]);
            }

            $user = auth()->user();
            if (! $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
                throw ValidationException::withMessages([
                    'permissao' => 'Você não tem permissão para quitar este empréstimo.',
                ]);
            }

            $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::today());
            $tipoJuros = $dados['tipo_juros'] ?? 'nenhum';
            $operacao = $emprestimo->operacao;
            $comprovantePath = $dados['comprovante_path'] ?? null;
            $consultorId = $dados['consultor_id'] ?? $user->id;

            $totalFalta = $parcelasPendentes->sum(fn (Parcela $p) => $p->faltaPagar());
            $valorJurosPorParcela = [];
            if ($tipoJuros === 'nenhum' || empty($tipoJuros)) {
                foreach ($parcelasPendentes as $p) {
                    $valorJurosPorParcela[$p->id] = 0.0;
                }
            } elseif ($tipoJuros === 'fixo') {
                $valorJurosFixoTotal = (float) ($dados['valor_juros_fixo'] ?? 0);
                foreach ($parcelasPendentes as $p) {
                    $faltaP = $p->faltaPagar();
                    $valorJurosPorParcela[$p->id] = $totalFalta > 0
                        ? round($valorJurosFixoTotal * ($faltaP / $totalFalta), 2)
                        : 0.0;
                }
                $somaJuros = array_sum($valorJurosPorParcela);
                if (abs($somaJuros - $valorJurosFixoTotal) > 0.02 && $parcelasPendentes->isNotEmpty()) {
                    $ultimoId = $parcelasPendentes->last()->id;
                    $valorJurosPorParcela[$ultimoId] = round(($valorJurosPorParcela[$ultimoId] ?? 0) + ($valorJurosFixoTotal - $somaJuros), 2);
                }
            } else {
                $taxa = $tipoJuros === 'automatico'
                    ? (float) ($operacao->taxa_juros_atraso ?? 0)
                    : (float) ($dados['taxa_juros_manual'] ?? 0);
                $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
                foreach ($parcelasPendentes as $p) {
                    $diasAtraso = $p->calcularDiasAtraso($dataPagamento);
                    $faltaP = $p->faltaPagar();
                    if ($tipoCalculo === 'por_dia') {
                        $valorJurosPorParcela[$p->id] = round($faltaP * ($taxa / 100) * $diasAtraso, 2);
                    } else {
                        $valorJurosPorParcela[$p->id] = round($faltaP * ($taxa / 100) * ($diasAtraso / 30), 2);
                    }
                }
            }

            $tipoJurosRegistro = $tipoJuros === 'fixo' ? 'fixo' : ($tipoJuros === 'manual' ? 'manual' : $tipoJuros);
            $taxaRegistro = $tipoJuros === 'automatico' ? ($operacao->taxa_juros_atraso ?? null) : ($tipoJuros === 'manual' ? ($dados['taxa_juros_manual'] ?? null) : null);

            $pagamentosCriados = [];
            foreach ($parcelasPendentes as $parcelaRef) {
                $parcela = Parcela::lockForUpdate()->findOrFail($parcelaRef->id);
                if ($parcela->faltaPagar() <= 0) {
                    continue;
                }
                $falta = $parcela->faltaPagar();
                $valorJuros = $valorJurosPorParcela[$parcela->id] ?? 0;
                $valorTotal = round($falta + $valorJuros, 2);
                $createData = [
                    'parcela_id' => $parcela->id,
                    'consultor_id' => $consultorId,
                    'valor' => $valorTotal,
                    'metodo' => $dados['metodo'] ?? 'dinheiro',
                    'data_pagamento' => $dataPagamento,
                    'comprovante_path' => $comprovantePath,
                    'observacoes' => $dados['observacoes'] ?? null,
                    'tipo_juros' => $tipoJuros === 'nenhum' ? null : $tipoJurosRegistro,
                    'taxa_juros_aplicada' => $taxaRegistro,
                    'valor_juros' => $valorJuros,
                ];
                $pagamento = Pagamento::create($createData);
                $pagamentosCriados[] = $pagamento;

                $novoValorPago = round((float) $parcela->valor_pago + $falta, 2);
                $estaTotalmentePaga = $novoValorPago >= (float) $parcela->valor;
                $parcela->update([
                    'valor_pago' => $estaTotalmentePaga ? $parcela->valor : $novoValorPago,
                    'data_pagamento' => $dataPagamento,
                    'status' => $estaTotalmentePaga ? 'paga' : 'pendente',
                    'dias_atraso' => 0,
                ]);
                $this->criarMovimentacaoCaixa($pagamento);
                self::auditar('registrar_pagamento_quitacao_diarias', $pagamento, null, $pagamento->toArray());
            }

            if ($pagamentosCriados === []) {
                throw ValidationException::withMessages([
                    'parcelas' => 'Nenhum pagamento foi registrado (parcelas podem ter sido atualizadas). Atualize a página e tente novamente.',
                ]);
            }

            // Relação parcelas foi eager load no início: após os updates no loop fica obsoleta;
            // todasParcelasPagas() não recarrega se já estiver loaded — sem fresh(), não finaliza o empréstimo.
            $this->verificarFinalizacaoEmprestimo($emprestimo->fresh(['parcelas']));

            return $pagamentosCriados;
        });
    }

    /**
     * Registrar pagamento de múltiplas parcelas em um único ato (um comprovante).
     * Parcelas devem estar em aberto; usa a mesma lógica de juros de atraso da quitação diária.
     *
     * @param  array  $parcelaIds  IDs das parcelas a pagar (mínimo 2), mesma ordem de vencimento/número
     * @param  array  $dados  metodo, data_pagamento, comprovante_path, observacoes, tipo_juros, taxa_juros_manual?, valor_juros_fixo?, consultor_id
     * @return array Lista de Pagamento criados
     */
    public function registrarPagamentoMultiplasParcelas(int $emprestimoId, array $parcelaIds, array $dados): array
    {
        $parcelaIds = array_values(array_unique(array_map('intval', $parcelaIds)));
        if (count($parcelaIds) < 2) {
            throw ValidationException::withMessages([
                'parcelas' => 'Selecione pelo menos duas parcelas para pagamento em lote.',
            ]);
        }

        return DB::transaction(function () use ($emprestimoId, $parcelaIds, $dados) {
            $emprestimo = \App\Modules\Loans\Models\Emprestimo::with(['parcelas', 'operacao', 'liberacao'])->lockForUpdate()->findOrFail($emprestimoId);

            if (! $emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'O empréstimo não está ativo.',
                ]);
            }

            $liberacao = $emprestimo->liberacao;
            if (! $emprestimo->isRenovacao() && (! $liberacao || $liberacao->status !== 'pago_ao_cliente')) {
                throw ValidationException::withMessages([
                    'liberacao' => 'O dinheiro do empréstimo ainda não foi liberado/pago ao cliente.',
                ]);
            }

            $user = auth()->user();
            if (! $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
                throw ValidationException::withMessages([
                    'permissao' => 'Você não tem permissão para registrar pagamento neste empréstimo.',
                ]);
            }

            $parcelasSelecionadas = collect();
            foreach ($parcelaIds as $pid) {
                $parcela = $emprestimo->parcelas->firstWhere('id', $pid);
                if (! $parcela) {
                    throw ValidationException::withMessages([
                        'parcelas' => 'Parcela #'.$pid.' não pertence a este empréstimo.',
                    ]);
                }
                if ($parcela->isQuitada()) {
                    throw ValidationException::withMessages([
                        'parcelas' => 'A parcela #'.$parcela->numero.' já está quitada e não pode ser selecionada.',
                    ]);
                }
                $falta = (float) $parcela->valor - (float) ($parcela->valor_pago ?? 0);
                if ($falta <= 0) {
                    throw ValidationException::withMessages([
                        'parcelas' => 'A parcela #'.$parcela->numero.' não possui saldo em aberto.',
                    ]);
                }
                $parcelasSelecionadas->push($parcela);
            }

            $parcelasSelecionadas = $parcelasSelecionadas->sortBy('numero')->values();

            $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::today());
            $tipoJuros = $dados['tipo_juros'] ?? 'nenhum';
            $operacao = $emprestimo->operacao;
            $comprovantePath = $dados['comprovante_path'] ?? null;
            $consultorId = $dados['consultor_id'] ?? $user->id;

            $totalFalta = $parcelasSelecionadas->sum(fn (Parcela $p) => (float) $p->valor - (float) ($p->valor_pago ?? 0));
            $valorJurosPorParcela = [];

            if ($tipoJuros === 'nenhum' || empty($tipoJuros)) {
                foreach ($parcelasSelecionadas as $p) {
                    $valorJurosPorParcela[$p->id] = 0.0;
                }
            } elseif ($tipoJuros === 'fixo') {
                $valorJurosFixoTotal = (float) ($dados['valor_juros_fixo'] ?? 0);
                foreach ($parcelasSelecionadas as $p) {
                    $faltaP = (float) $p->valor - (float) ($p->valor_pago ?? 0);
                    $valorJurosPorParcela[$p->id] = $totalFalta > 0
                        ? round($valorJurosFixoTotal * ($faltaP / $totalFalta), 2)
                        : 0.0;
                }
                $somaJuros = array_sum($valorJurosPorParcela);
                if (abs($somaJuros - $valorJurosFixoTotal) > 0.02 && count($parcelasSelecionadas) > 0) {
                    $ultimoId = $parcelasSelecionadas->last()->id;
                    $valorJurosPorParcela[$ultimoId] = round(($valorJurosPorParcela[$ultimoId] ?? 0) + ($valorJurosFixoTotal - $somaJuros), 2);
                }
            } else {
                $taxa = $tipoJuros === 'automatico'
                    ? (float) ($operacao->taxa_juros_atraso ?? 0)
                    : (float) ($dados['taxa_juros_manual'] ?? 0);
                $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
                foreach ($parcelasSelecionadas as $p) {
                    $diasAtraso = $p->calcularDiasAtraso($dataPagamento);
                    $valorP = (float) $p->valor;
                    if ($tipoCalculo === 'por_dia') {
                        $valorJurosPorParcela[$p->id] = round($valorP * ($taxa / 100) * $diasAtraso, 2);
                    } else {
                        $valorJurosPorParcela[$p->id] = round($valorP * ($taxa / 100) * ($diasAtraso / 30), 2);
                    }
                }
            }

            $tipoJurosRegistro = $tipoJuros === 'fixo' ? 'fixo' : ($tipoJuros === 'manual' ? 'manual' : $tipoJuros);
            $taxaRegistro = $tipoJuros === 'automatico' ? ($operacao->taxa_juros_atraso ?? null) : ($tipoJuros === 'manual' ? ($dados['taxa_juros_manual'] ?? null) : null);

            $pagamentosCriados = [];
            foreach ($parcelasSelecionadas as $parcela) {
                $parcela = Parcela::lockForUpdate()->findOrFail($parcela->id);
                if ($parcela->isQuitada()) {
                    throw ValidationException::withMessages([
                        'parcelas' => 'A parcela #'.$parcela->numero.' foi quitada durante o processamento.',
                    ]);
                }
                $falta = (float) $parcela->valor - (float) ($parcela->valor_pago ?? 0);
                if ($falta <= 0) {
                    continue;
                }
                $valorJuros = $valorJurosPorParcela[$parcela->id] ?? 0;
                $valorTotal = round($falta + $valorJuros, 2);

                $createData = [
                    'parcela_id' => $parcela->id,
                    'consultor_id' => $consultorId,
                    'valor' => $valorTotal,
                    'metodo' => $dados['metodo'] ?? 'dinheiro',
                    'data_pagamento' => $dataPagamento,
                    'comprovante_path' => $comprovantePath,
                    'observacoes' => isset($dados['observacoes']) ? ('Pagamento em lote. '.$dados['observacoes']) : 'Pagamento em lote (múltiplas parcelas).',
                    'tipo_juros' => $tipoJuros === 'nenhum' ? null : $tipoJurosRegistro,
                    'taxa_juros_aplicada' => $taxaRegistro,
                    'valor_juros' => $valorJuros,
                ];
                $pagamento = Pagamento::create($createData);
                $pagamentosCriados[] = $pagamento;

                $novoValorPago = (float) $parcela->valor_pago + $falta;
                $estaTotalmentePaga = $novoValorPago >= (float) $parcela->valor;
                $parcela->update([
                    'valor_pago' => $estaTotalmentePaga ? $parcela->valor : $novoValorPago,
                    'data_pagamento' => $dataPagamento,
                    'status' => $estaTotalmentePaga ? 'paga' : 'pendente',
                    'dias_atraso' => 0,
                ]);
                $this->criarMovimentacaoCaixa($pagamento);
                self::auditar('registrar_pagamento_multiplas_parcelas', $pagamento, null, $pagamento->toArray());
            }

            $this->verificarFinalizacaoEmprestimo($emprestimo->fresh());

            return $pagamentosCriados;
        });
    }

    /**
     * Aceitar pagamento em produto/objeto (gestor ou administrador).
     * Atualiza a parcela, não gera caixa.
     */
    public function aceitarPagamentoProdutoObjeto(int $pagamentoId, int $gestorId): Pagamento
    {
        return DB::transaction(function () use ($pagamentoId, $gestorId) {
            $pagamento = Pagamento::with('parcela.emprestimo')->findOrFail($pagamentoId);

            if ($pagamento->metodo !== Pagamento::METODO_PRODUTO_OBJETO) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Este pagamento não é em produto/objeto.',
                ]);
            }

            if ($pagamento->aceite_gestor_id !== null) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Este pagamento em produto/objeto já foi aceito.',
                ]);
            }

            $parcela = $pagamento->parcela;
            $emprestimo = $parcela->emprestimo;

            if ($parcela->isQuitada()) {
                throw ValidationException::withMessages([
                    'parcela' => 'Esta parcela já está quitada.',
                ]);
            }

            $pagamento->update([
                'aceite_gestor_id' => $gestorId,
                'aceite_gestor_em' => Carbon::now(),
            ]);

            $novoValorPago = $parcela->valor_pago + $pagamento->valor;
            $estaTotalmentePaga = $novoValorPago >= $parcela->valor;

            $parcela->update([
                'valor_pago' => $novoValorPago,
                'data_pagamento' => $pagamento->data_pagamento,
                'status' => $estaTotalmentePaga ? 'paga' : 'pendente',
                'dias_atraso' => 0,
            ]);

            self::auditar('aceitar_pagamento_produto_objeto', $pagamento, null, ['aceite_gestor_id' => $gestorId]);
            $this->verificarFinalizacaoEmprestimo($emprestimo);

            return $pagamento->fresh();
        });
    }

    /**
     * Rejeitar pagamento em produto/objeto (gestor ou administrador).
     * O pagamento continua pendente (parcela não é creditada).
     */
    public function rejeitarPagamentoProdutoObjeto(int $pagamentoId, int $gestorId): Pagamento
    {
        $pagamento = Pagamento::findOrFail($pagamentoId);

        if ($pagamento->metodo !== Pagamento::METODO_PRODUTO_OBJETO) {
            throw ValidationException::withMessages([
                'pagamento' => 'Este pagamento não é em produto/objeto.',
            ]);
        }

        if ($pagamento->aceite_gestor_id !== null) {
            throw ValidationException::withMessages([
                'pagamento' => 'Este pagamento já foi aceito.',
            ]);
        }

        if ($pagamento->rejeitado_por_id !== null) {
            throw ValidationException::withMessages([
                'pagamento' => 'Este pagamento já foi rejeitado.',
            ]);
        }

        $pagamento->update([
            'rejeitado_por_id' => $gestorId,
            'rejeitado_em' => Carbon::now(),
        ]);

        self::auditar('rejeitar_pagamento_produto_objeto', $pagamento, null, ['rejeitado_por_id' => $gestorId]);

        return $pagamento->fresh();
    }

    /**
     * Anexar comprovante a um pagamento já registrado (apenas se ainda não tiver).
     * Não permite editar/substituir comprovante existente.
     */
    public function anexarComprovante(int $pagamentoId, string $comprovantePath): Pagamento
    {
        $pagamento = Pagamento::findOrFail($pagamentoId);

        if ($pagamento->hasComprovante()) {
            throw ValidationException::withMessages([
                'comprovante' => 'Este pagamento já possui comprovante. Não é possível substituir.',
            ]);
        }

        $pagamento->update(['comprovante_path' => $comprovantePath]);
        self::auditar('anexar_comprovante_pagamento', $pagamento, null, ['comprovante_path' => $comprovantePath]);

        return $pagamento->fresh();
    }

    /**
     * Verificar se o empréstimo está totalmente quitado e finalizá-lo (uso externo, ex: QuitacaoService).
     *
     * @param  \App\Modules\Loans\Models\Emprestimo  $emprestimo
     */
    public function verificarEFinalizarEmprestimo($emprestimo): void
    {
        $this->verificarFinalizacaoEmprestimo($emprestimo);
    }

    /**
     * Verificar se todas as parcelas do empréstimo foram pagas
     * Se sim, finaliza o empréstimo automaticamente
     *
     * Usa Strategy Pattern para verificar finalização baseada no tipo
     *
     * @param  \App\Modules\Loans\Models\Emprestimo  $emprestimo
     */
    public function verificarFinalizacaoEmprestimo($emprestimo): void
    {
        // Usar Strategy Pattern para verificar se pode finalizar
        $strategy = \App\Modules\Loans\Services\Strategies\LoanStrategyFactory::create($emprestimo);

        if ($strategy->podeFinalizar($emprestimo)) {
            $statusAnterior = $emprestimo->status;

            // Finalizar empréstimo
            $emprestimo->update([
                'status' => 'finalizado',
            ]);

            // Ações específicas por tipo
            // Se for empréstimo tipo empenho, liberar garantias automaticamente
            if ($emprestimo->isEmpenho()) {
                $emprestimo->load('garantias');

                foreach ($emprestimo->garantias as $garantia) {
                    if ($garantia->isAtiva()) {
                        $oldStatusGarantia = $garantia->status;

                        $garantia->update([
                            'status' => 'liberada',
                            'data_liberacao' => \Carbon\Carbon::now(),
                        ]);

                        // Auditoria da liberação da garantia
                        self::auditar(
                            'liberar_garantia',
                            $garantia,
                            ['status' => $oldStatusGarantia],
                            ['status' => 'liberada', 'data_liberacao' => $garantia->data_liberacao],
                            'Garantia liberada automaticamente - Todas as parcelas do empréstimo foram quitadas'
                        );

                        // Notificar consultor sobre liberação
                        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
                        $nomeCliente = NotificacaoClienteDisplayName::forEmprestimo($emprestimo);

                        if ($emprestimo->consultor_id) {
                            $notificacaoService->criar([
                                'user_id' => $emprestimo->consultor_id,
                                'tipo' => 'garantia_liberada',
                                'titulo' => 'Garantia Liberada',
                                'mensagem' => "A garantia do empréstimo #{$emprestimo->id} do cliente {$nomeCliente} foi liberada. Todas as parcelas foram quitadas.",
                                'url' => route('emprestimos.show', $emprestimo->id),
                                'dados' => [
                                    'emprestimo_id' => $emprestimo->id,
                                    'garantia_id' => $garantia->id,
                                ],
                            ]);
                        }
                    }
                }
            }

            // Mensagem de auditoria baseada no tipo
            $mensagemAuditoria = match ($emprestimo->tipo) {
                'troca_cheque' => 'Empréstimo finalizado automaticamente - Todos os cheques foram compensados',
                default => 'Empréstimo finalizado automaticamente - Todas as parcelas foram quitadas'
            };

            // Auditoria da finalização
            self::auditar(
                'finalizar_emprestimo',
                $emprestimo,
                ['status' => $statusAnterior],
                ['status' => 'finalizado'],
                $mensagemAuditoria
            );
        }
    }

    /**
     * Criar movimentação de caixa a partir de um pagamento
     */
    private function criarMovimentacaoCaixa(Pagamento $pagamento): CashLedgerEntry
    {
        $emprestimo = $pagamento->parcela->emprestimo;

        // Usar CashService para garantir que empresa_id seja preenchido corretamente
        return $this->cashService->registrarMovimentacao([
            'operacao_id' => $emprestimo->operacao_id,
            'consultor_id' => $pagamento->consultor_id,
            'pagamento_id' => $pagamento->id,
            'tipo' => 'entrada',
            'origem' => 'automatica',
            'valor' => $pagamento->valor,
            'descricao' => 'Pagamento de parcela #'.$pagamento->parcela->numero.' - Empréstimo #'.$emprestimo->id,
            'data_movimentacao' => $pagamento->data_pagamento,
            'referencia_tipo' => 'pagamento_parcela',
            'referencia_id' => $pagamento->parcela_id,
        ]);
    }

    /**
     * Calcular juros para uma parcela atrasada
     */
    private function calcularJuros(Parcela $parcela, array $dados): array
    {
        $resultado = [
            'tipo_juros' => null,
            'taxa_juros_aplicada' => null,
            'valor_juros' => 0,
        ];

        // Só calcula juros se a parcela estiver atrasada
        if (! $parcela->isAtrasada()) {
            return $resultado;
        }

        $tipoJuros = $dados['tipo_juros'] ?? 'nenhum';

        // Se não há juros, retorna vazio
        if ($tipoJuros === 'nenhum' || empty($tipoJuros)) {
            return $resultado;
        }

        // Valor inferior (juros do contrato reduzido): valor total já informado; juros = valor - principal
        if ($tipoJuros === 'valor_inferior') {
            $principal = $parcela->valor_amortizacao !== null && (float) $parcela->valor_amortizacao > 0
                ? (float) $parcela->valor_amortizacao
                : (float) $parcela->valor;
            $valorTotal = (float) ($dados['valor'] ?? 0);
            $valorJuros = $valorTotal - $principal;

            return [
                'tipo_juros' => 'fixo',
                'taxa_juros_aplicada' => null,
                'valor_juros' => round(max(0, $valorJuros), 2),
            ];
        }

        // Usar data do pagamento como referência para dias de atraso (ex.: quitação diária no último vencimento)
        $dataRef = isset($dados['data_pagamento']) ? Carbon::parse($dados['data_pagamento'])->startOfDay() : null;
        $diasAtraso = $parcela->calcularDiasAtraso($dataRef);
        $valorParcela = $parcela->valor;
        $operacao = $parcela->emprestimo->operacao;

        switch ($tipoJuros) {
            case 'automatico':
                // Usa a taxa configurada na operação
                if ($operacao->taxa_juros_atraso > 0) {
                    $taxa = $operacao->taxa_juros_atraso;
                    $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';

                    if ($tipoCalculo === 'por_dia') {
                        $valorJuros = $valorParcela * ($taxa / 100) * $diasAtraso;
                    } else { // por_mes
                        $valorJuros = $valorParcela * ($taxa / 100) * ($diasAtraso / 30);
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
                    $valorJuros = $valorParcela * ($taxa / 100) * $diasAtraso;

                    $resultado = [
                        'tipo_juros' => 'manual',
                        'taxa_juros_aplicada' => $taxa,
                        'valor_juros' => round($valorJuros, 2),
                    ];
                }
                break;

            case 'fixo':
                // Usa o valor fixo informado
                $valorFixo = $dados['valor_juros_fixo'] ?? 0;
                if ($valorFixo > 0) {
                    $resultado = [
                        'tipo_juros' => 'fixo',
                        'taxa_juros_aplicada' => null,
                        'valor_juros' => round($valorFixo, 2),
                    ];
                }
                break;
        }

        return $resultado;
    }

    /**
     * Valor de juros devido pelo atraso (cálculo automático da operação).
     * Usado para verificar se o consultor está pagando menos que o devido.
     *
     * Para empréstimo diário: não gera "multa" (juros devido = 0) quando o pagamento
     * está dentro do prazo da última parcela (data_pagamento <= data_vencimento da última).
     *
     * @param  \Carbon\Carbon|null  $dataPagamento  Data do pagamento (quando null, usa hoje).
     */
    public function getJurosDevidoAutomatico(Parcela $parcela, $dataPagamento = null): float
    {
        $parcela->load('emprestimo.operacao');
        $emprestimo = $parcela->emprestimo;
        $operacao = $emprestimo->operacao;

        $dataRef = $dataPagamento ? Carbon::parse($dataPagamento)->startOfDay() : Carbon::today();

        // Diária: dentro do prazo da última parcela = não cobrar juros de atraso (multa)
        if ($emprestimo->isFrequenciaDiaria()) {
            $ultimaParcela = $emprestimo->getUltimaParcela();
            if ($ultimaParcela && $dataRef <= $ultimaParcela->data_vencimento->startOfDay()) {
                return 0.0;
            }
        }

        if (! $parcela->isAtrasada()) {
            return 0.0;
        }
        if ($operacao->taxa_juros_atraso <= 0) {
            return 0.0;
        }
        $diasAtraso = $parcela->calcularDiasAtraso($dataRef);
        $valorParcela = (float) $parcela->valor;
        $taxa = (float) $operacao->taxa_juros_atraso;
        $tipoCalculo = $operacao->tipo_calculo_juros ?? 'por_dia';
        if ($tipoCalculo === 'por_dia') {
            return round($valorParcela * ($taxa / 100) * $diasAtraso, 2);
        }

        return round($valorParcela * ($taxa / 100) * ($diasAtraso / 30), 2);
    }

    /**
     * Retorna os dados de juros que seriam aplicados (para comparação com juros devido).
     */
    public function getDadosJuros(Parcela $parcela, array $dados): array
    {
        return $this->calcularJuros($parcela, $dados);
    }

    /**
     * Consultor: pagamento diário abaixo do devido — gera entrada em caixa, parcela permanece em atraso até aprovação.
     *
     * @param  array<string, mixed>  $dados
     */
    public function criarPagamentoDiariaParcialAguardandoAprovacao(array $dados): SolicitacaoPagamentoDiariaParcial
    {
        return DB::transaction(function () use ($dados) {
            $parcela = Parcela::lockForUpdate()->with(['emprestimo.liberacao', 'emprestimo.operacao'])->findOrFail($dados['parcela_id']);
            $emprestimo = $parcela->emprestimo;

            if (! $emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível registrar pagamento. O empréstimo precisa estar ATIVO.',
                ]);
            }

            if (! $emprestimo->isRenovacao()) {
                $liberacao = $emprestimo->liberacao;
                if (! $liberacao || $liberacao->status !== 'pago_ao_cliente') {
                    throw ValidationException::withMessages([
                        'liberacao' => 'Não é possível registrar pagamento. O dinheiro ainda não foi liberado pelo gestor.',
                    ]);
                }
            }

            $user = auth()->user();
            if (! $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor']) && $emprestimo->consultor_id !== $user->id) {
                throw ValidationException::withMessages([
                    'permissao' => 'Você não tem permissão para registrar pagamento deste empréstimo.',
                ]);
            }

            $valor = round((float) $dados['valor'], 2);
            $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::today());

            $pagamento = Pagamento::create([
                'parcela_id' => $parcela->id,
                'consultor_id' => $dados['consultor_id'],
                'valor' => $valor,
                'metodo' => $dados['metodo'] ?? 'dinheiro',
                'data_pagamento' => $dataPagamento,
                'comprovante_path' => $dados['comprovante_path'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'tipo_juros' => null,
                'taxa_juros_aplicada' => null,
                'valor_juros' => 0,
                'aguardando_aprovacao_diaria_parcial' => true,
            ]);

            $this->criarMovimentacaoCaixa($pagamento);

            $dias = $parcela->calcularDiasAtraso($dataPagamento);
            $parcela->update([
                'status' => 'atrasada',
                'dias_atraso' => max(1, $dias, (int) $parcela->dias_atraso),
            ]);

            self::auditar('registrar_pagamento_diaria_parcial_pendente', $pagamento, null, $pagamento->toArray());

            return SolicitacaoPagamentoDiariaParcial::create([
                'parcela_id' => $parcela->id,
                'emprestimo_id' => $emprestimo->id,
                'consultor_id' => $dados['consultor_id'],
                'valor_recebido' => $dados['valor_recebido'],
                'valor_esperado' => $dados['valor_esperado'],
                'faltante' => $dados['faltante'],
                'metodo' => $dados['metodo'] ?? 'dinheiro',
                'data_pagamento' => $dataPagamento->format('Y-m-d'),
                'comprovante_path' => $dados['comprovante_path'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'pagamento_id' => $pagamento->id,
                'status' => 'aguardando',
                'empresa_id' => $parcela->empresa_id ?? $emprestimo->operacao->empresa_id ?? null,
            ]);
        });
    }

    public function aprovarSolicitacaoPagamentoDiariaParcial(SolicitacaoPagamentoDiariaParcial $solicitacao, int $gestorId): void
    {
        DB::transaction(function () use ($solicitacao, $gestorId) {
            $solicitacao = SolicitacaoPagamentoDiariaParcial::lockForUpdate()->findOrFail($solicitacao->id);
            if (! $solicitacao->isAguardando()) {
                throw ValidationException::withMessages([
                    'solicitacao' => 'Esta solicitação já foi processada.',
                ]);
            }

            $parcela = Parcela::lockForUpdate()->with('emprestimo.parcelas')->findOrFail($solicitacao->parcela_id);
            $emprestimo = $parcela->emprestimo;
            $pagamento = Pagamento::lockForUpdate()->findOrFail($solicitacao->pagamento_id);

            $valorRecebido = (float) $solicitacao->valor_recebido;
            $faltante = (float) $solicitacao->faltante;
            $dataPagamento = Carbon::parse($solicitacao->data_pagamento);

            $novoValorPago = round((float) $parcela->valor_pago + $valorRecebido, 2);
            $parcela->update([
                'valor_pago' => $novoValorPago,
                'data_pagamento' => $dataPagamento,
                'status' => 'paga_parcial',
                'dias_atraso' => 0,
            ]);

            $ultima = Parcela::where('emprestimo_id', $emprestimo->id)
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->first();
            if ($ultima) {
                $ultima->update([
                    'valor' => round((float) $ultima->valor + $faltante, 2),
                ]);
            }

            $pagamento->update(['aguardando_aprovacao_diaria_parcial' => false]);

            $solicitacao->update([
                'status' => 'aprovado',
                'aprovado_por_id' => $gestorId,
                'aprovado_em' => now(),
            ]);

            self::auditar('aprovar_pagamento_diaria_parcial', $solicitacao, null, $solicitacao->fresh()->toArray());

            $this->verificarFinalizacaoEmprestimo($emprestimo->fresh());
        });
    }

    public function rejeitarSolicitacaoPagamentoDiariaParcial(SolicitacaoPagamentoDiariaParcial $solicitacao, int $gestorId): void
    {
        DB::transaction(function () use ($solicitacao, $gestorId) {
            $solicitacao = SolicitacaoPagamentoDiariaParcial::lockForUpdate()->findOrFail($solicitacao->id);
            if (! $solicitacao->isAguardando()) {
                throw ValidationException::withMessages([
                    'solicitacao' => 'Esta solicitação já foi processada.',
                ]);
            }

            $pagamento = Pagamento::lockForUpdate()->findOrFail($solicitacao->pagamento_id);
            $parcela = Parcela::lockForUpdate()->findOrFail($solicitacao->parcela_id);
            $emprestimo = $parcela->emprestimo;

            $this->cashService->registrarMovimentacao([
                'operacao_id' => $emprestimo->operacao_id,
                'consultor_id' => $pagamento->consultor_id,
                'pagamento_id' => $pagamento->id,
                'tipo' => 'saida',
                'origem' => 'automatica',
                'valor' => $pagamento->valor,
                'descricao' => 'Estorno – pagamento diário parcial rejeitado (solicitação #'.$solicitacao->id.')',
                'data_movimentacao' => Carbon::today(),
                'referencia_tipo' => 'pagamento_parcela',
                'referencia_id' => $parcela->id,
            ]);

            $solicitacao->update([
                'status' => 'rejeitado',
                'rejeitado_por_id' => $gestorId,
                'rejeitado_em' => now(),
            ]);

            $pagamento->delete();

            self::auditar('rejeitar_pagamento_diaria_parcial', $solicitacao, null, $solicitacao->fresh()->toArray());
        });
    }

    /**
     * Gestor/admin: registra parcial sem fila de aprovação (mesmo efeito da aprovação).
     *
     * @param  array<string, mixed>  $dados
     */
    public function registrarDiarioParcialGestorDireto(array $dados): Pagamento
    {
        return DB::transaction(function () use ($dados) {
            $parcela = Parcela::lockForUpdate()->with(['emprestimo.parcelas', 'emprestimo.liberacao', 'emprestimo.operacao'])->findOrFail($dados['parcela_id']);
            $emprestimo = $parcela->emprestimo;

            if (! $emprestimo->isAtivo()) {
                throw ValidationException::withMessages([
                    'emprestimo' => 'Não é possível registrar pagamento. O empréstimo precisa estar ATIVO.',
                ]);
            }

            if (! $emprestimo->isRenovacao()) {
                $liberacao = $emprestimo->liberacao;
                if (! $liberacao || $liberacao->status !== 'pago_ao_cliente') {
                    throw ValidationException::withMessages([
                        'liberacao' => 'Não é possível registrar pagamento. O dinheiro ainda não foi liberado pelo gestor.',
                    ]);
                }
            }

            $user = auth()->user();
            if (! $user->temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador', 'gestor'])) {
                throw ValidationException::withMessages([
                    'permissao' => 'Apenas gestor ou administrador podem usar este fluxo.',
                ]);
            }

            $valor = round((float) $dados['valor'], 2);
            $esperado = round((float) $dados['valor_esperado'], 2);
            $faltante = round($esperado - $valor, 2);
            if ($faltante < 0) {
                throw ValidationException::withMessages(['valor' => 'O valor recebido não pode ser maior que o devido.']);
            }

            $dataPagamento = Carbon::parse($dados['data_pagamento'] ?? Carbon::today());

            $pagamento = Pagamento::create([
                'parcela_id' => $parcela->id,
                'consultor_id' => $dados['consultor_id'],
                'valor' => $valor,
                'metodo' => $dados['metodo'] ?? 'dinheiro',
                'data_pagamento' => $dataPagamento,
                'comprovante_path' => $dados['comprovante_path'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'tipo_juros' => null,
                'taxa_juros_aplicada' => null,
                'valor_juros' => 0,
                'aguardando_aprovacao_diaria_parcial' => false,
            ]);

            $this->criarMovimentacaoCaixa($pagamento);

            $novoValorPago = round((float) $parcela->valor_pago + $valor, 2);
            $parcela->update([
                'valor_pago' => $novoValorPago,
                'data_pagamento' => $dataPagamento,
                'status' => 'paga_parcial',
                'dias_atraso' => 0,
            ]);

            $ultima = Parcela::where('emprestimo_id', $emprestimo->id)
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->first();
            if ($ultima) {
                $ultima->update([
                    'valor' => round((float) $ultima->valor + $faltante, 2),
                ]);
            }

            self::auditar('registrar_pagamento_diaria_parcial_gestor', $pagamento, null, $pagamento->toArray());
            $this->verificarFinalizacaoEmprestimo($emprestimo->fresh());

            return $pagamento->fresh();
        });
    }
}
