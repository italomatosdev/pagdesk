<?php

namespace App\Modules\Core\Services;

use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Models\Cliente;
use App\Modules\Core\Models\FormaPagamentoVenda;
use App\Modules\Core\Models\Operacao;
use App\Modules\Core\Models\OperationClient;
use App\Modules\Core\Models\Produto;
use App\Modules\Core\Models\Venda;
use App\Modules\Core\Models\VendaItem;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\EmprestimoService;
use App\Modules\Loans\Services\Strategies\LoanStrategyFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendaService
{
    use Auditable;

    /**
     * Registrar uma nova venda (itens + formas de pagamento). Se houver forma crediário, cria o empréstimo e as parcelas.
     *
     * @param  array  $dados  ['cliente_id', 'operacao_id', 'data_venda', 'observacoes', 'itens' => [...], 'formas' => [...]]
     *
     * @throws ValidationException
     */
    public function registrar(array $dados): Venda
    {
        return DB::transaction(function () use ($dados) {
            $user = auth()->user();
            $operacao = Operacao::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)
                ->find($dados['operacao_id']);
            if (! $operacao) {
                throw ValidationException::withMessages(['operacao_id' => 'Operação não encontrada.']);
            }
            $empresaId = $operacao->empresa_id ?? $user->empresa_id;
            if (! $empresaId) {
                throw ValidationException::withMessages(['operacao_id' => 'Operação sem empresa definida.']);
            }

            $cliente = Cliente::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find($dados['cliente_id']);
            if (! $cliente) {
                throw ValidationException::withMessages(['cliente_id' => 'Cliente não encontrado.']);
            }

            $dataVenda = isset($dados['data_venda']) ? Carbon::parse($dados['data_venda']) : now();
            $itens = $dados['itens'] ?? [];
            $formas = $dados['formas'] ?? [];

            if (empty($itens)) {
                throw ValidationException::withMessages(['itens' => 'Adicione pelo menos um item à venda.']);
            }
            if (empty($formas)) {
                throw ValidationException::withMessages(['formas' => 'Adicione pelo menos uma forma de pagamento.']);
            }

            // Validar estoque: somar quantidade por produto e verificar disponibilidade
            $quantidadePorProduto = [];
            foreach ($itens as $item) {
                $produtoId = $item['produto_id'] ?? null;
                if (! $produtoId) {
                    continue;
                }
                $qtd = (float) ($item['quantidade'] ?? 0);
                if ($qtd <= 0) {
                    continue;
                }
                $produtoLinha = Produto::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find($produtoId);
                if ($produtoLinha && $produtoLinha->unidadeContagemInteira() && abs($qtd - round($qtd)) > 1e-9) {
                    throw ValidationException::withMessages([
                        'itens' => 'O produto "'.$produtoLinha->nome.'" usa unidade de contagem (ex.: un, pc, peça). Informe quantidade inteira em cada item.',
                    ]);
                }
                $quantidadePorProduto[$produtoId] = ($quantidadePorProduto[$produtoId] ?? 0) + $qtd;
            }
            foreach ($quantidadePorProduto as $produtoId => $qtdNecessaria) {
                $produto = Produto::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find($produtoId);
                if (! $produto) {
                    throw ValidationException::withMessages(['itens' => 'Produto selecionado não encontrado.']);
                }
                if (! $produto->temEstoque($qtdNecessaria)) {
                    throw ValidationException::withMessages([
                        'itens' => 'Estoque insuficiente para o produto "'.$produto->nome.'". Disponível: '.$produto->formatarQuantidadeEstoque().', solicitado: '.$produto->formatarQuantidade($qtdNecessaria).'.',
                    ]);
                }
                if ((float) $produto->estoque <= 0) {
                    throw ValidationException::withMessages(['itens' => "O produto \"{$produto->nome}\" não possui estoque disponível para venda."]);
                }
            }

            $valorTotalBruto = 0;
            $valorTotalFinal = 0;
            foreach ($formas as $f) {
                $valorTotalFinal += (float) ($f['valor'] ?? 0);
            }

            foreach ($itens as $item) {
                $qtd = (float) ($item['quantidade'] ?? 1);
                $pv = (float) ($item['preco_unitario_vista'] ?? 0);
                $pc = (float) ($item['preco_unitario_crediario'] ?? 0);
                $subVista = round($qtd * $pv, 2);
                $subCrediario = round($qtd * $pc, 2);
                $valorTotalBruto += $subVista;
            }

            $valorDesconto = (float) ($dados['valor_desconto'] ?? 0);
            // Total da venda = soma das formas de pagamento (valor que o cliente paga). Não sobrescrever com bruto - desconto.

            $venda = Venda::create([
                'cliente_id' => $dados['cliente_id'],
                'operacao_id' => $dados['operacao_id'],
                'user_id' => $user->id,
                'empresa_id' => $empresaId,
                'data_venda' => $dataVenda,
                'status' => 'finalizada',
                'valor_total_bruto' => $valorTotalBruto,
                'valor_desconto' => $valorDesconto,
                'valor_total_final' => $valorTotalFinal,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            self::auditar('criar_venda', $venda, null, $venda->toArray(), 'Venda registrada com itens e formas de pagamento.');

            foreach ($itens as $item) {
                $qtd = (float) ($item['quantidade'] ?? 1);
                $pv = (float) ($item['preco_unitario_vista'] ?? 0);
                $pc = (float) ($item['preco_unitario_crediario'] ?? 0);
                $subVista = round($qtd * $pv, 2);
                $subCrediario = round($qtd * $pc, 2);
                VendaItem::create([
                    'venda_id' => $venda->id,
                    'produto_id' => $item['produto_id'] ?? null,
                    'descricao' => $item['descricao'] ?? null,
                    'quantidade' => $qtd,
                    'preco_unitario_vista' => $pv,
                    'preco_unitario_crediario' => $pc,
                    'subtotal_vista' => $subVista,
                    'subtotal_crediario' => $subCrediario,
                ]);
                // Baixar estoque do produto
                $produtoId = $item['produto_id'] ?? null;
                if ($produtoId && $qtd > 0) {
                    $produto = Produto::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->find($produtoId);
                    if ($produto) {
                        $produto->decrement('estoque', $qtd);
                    }
                }
            }

            $emprestimoService = app(EmprestimoService::class);
            $consultorId = $user->id;

            foreach ($formas as $f) {
                $formaTipo = $f['forma'] ?? '';
                $valor = (float) ($f['valor'] ?? 0);
                if ($valor <= 0) {
                    continue;
                }
                $numeroParcelas = isset($f['numero_parcelas']) ? (int) $f['numero_parcelas'] : null;
                $descricaoForma = isset($f['descricao']) ? trim((string) $f['descricao']) : null;
                $comprovantePath = isset($f['comprovante_path']) ? trim((string) $f['comprovante_path']) : null;

                $formaPagamento = FormaPagamentoVenda::create([
                    'venda_id' => $venda->id,
                    'forma' => $formaTipo,
                    'valor' => $valor,
                    'descricao' => $descricaoForma ?: null,
                    'comprovante_path' => $comprovantePath ?: null,
                    'numero_parcelas' => $numeroParcelas,
                    'emprestimo_id' => null,
                ]);

                // Formas à vista (dinheiro, pix, cartão) geram entrada no caixa da operação
                if ($formaTipo !== FormaPagamentoVenda::FORMA_CREDIARIO) {
                    $formaLabel = FormaPagamentoVenda::formasDisponiveis()[$formaTipo] ?? $formaTipo;
                    $descricaoMov = 'Venda #'.$venda->id.' - '.$formaLabel;
                    if ($descricaoForma) {
                        $descricaoMov .= ' ('.$descricaoForma.')';
                    }
                    $cashService = app(CashService::class);
                    $dadosMov = [
                        'operacao_id' => $venda->operacao_id,
                        'consultor_id' => null,
                        'tipo' => 'entrada',
                        'origem' => 'automatica',
                        'valor' => $valor,
                        'descricao' => $descricaoMov,
                        'data_movimentacao' => $dataVenda,
                        'referencia_tipo' => 'venda',
                        'referencia_id' => $venda->id,
                        'empresa_id' => $empresaId,
                    ];
                    if ($comprovantePath) {
                        $dadosMov['comprovante_path'] = $comprovantePath;
                    }
                    $cashService->registrarMovimentacao($dadosMov);
                }

                if ($formaTipo === FormaPagamentoVenda::FORMA_CREDIARIO) {
                    if ($numeroParcelas === null || $numeroParcelas < 1) {
                        throw ValidationException::withMessages(['formas' => 'Crediário deve informar o número de parcelas.']);
                    }
                    $frequenciaCrediario = in_array($f['frequencia'] ?? null, ['diaria', 'semanal', 'mensal'], true)
                        ? $f['frequencia']
                        : 'mensal';
                    $emprestimoService->garantirVinculoClienteOperacao(
                        $dados['cliente_id'],
                        $dados['operacao_id'],
                        $consultorId
                    );
                    $emprestimo = Emprestimo::withoutGlobalScope(\App\Models\Scopes\EmpresaScope::class)->create([
                        'operacao_id' => $dados['operacao_id'],
                        'cliente_id' => $dados['cliente_id'],
                        'consultor_id' => $consultorId,
                        'valor_total' => $valor,
                        'numero_parcelas' => $numeroParcelas,
                        'frequencia' => $frequenciaCrediario,
                        'data_inicio' => $dataVenda,
                        'taxa_juros' => 0,
                        'tipo' => 'crediario',
                        'status' => 'ativo',
                        'observacoes' => 'Crediário - Venda #'.$venda->id,
                        'empresa_id' => $empresaId,
                        'venda_id' => $venda->id,
                    ]);
                    $strategy = LoanStrategyFactory::create($emprestimo);
                    $strategy->gerarEstruturaPagamento($emprestimo);
                    $formaPagamento->update(['emprestimo_id' => $emprestimo->id]);

                    self::auditar('criar_emprestimo_crediario', $emprestimo, null, $emprestimo->toArray(), 'Crediário gerado pela venda #'.$venda->id.'.');
                }
            }

            return $venda->load(['itens.produto', 'formasPagamento.emprestimo']);
        });
    }

    /**
     * Garantir vínculo cliente-operacao (delega para EmprestimoService).
     */
    protected function garantirVinculoClienteOperacao(int $clienteId, int $operacaoId, ?int $consultorId = null): OperationClient
    {
        return app(EmprestimoService::class)->garantirVinculoClienteOperacao($clienteId, $operacaoId, $consultorId);
    }
}
