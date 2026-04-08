<?php

namespace App\Modules\Loans\Services;

use App\Models\User;
use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Services\CashService;
use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Pagamento;
use App\Modules\Loans\Models\Parcela;
use App\Modules\Loans\Services\Strategies\LoanStrategyFactory;
use App\Support\PagamentoEstornoFechamentoGate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagamentoEstornoService
{
    use Auditable;

    public function __construct(
        protected CashService $cashService
    ) {}

    public function estornar(Pagamento $pagamento, User $gestor, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo do estorno.']);
        }

        DB::transaction(function () use ($pagamento, $gestor, $motivo) {
            $pagamento = Pagamento::lockForUpdate()->with(['parcela.emprestimo'])->findOrFail($pagamento->id);

            $emprestimo = $pagamento->parcela->emprestimo;
            if (! $gestor->temAlgumPapelNaOperacao((int) $emprestimo->operacao_id, ['gestor', 'administrador'])) {
                throw ValidationException::withMessages([
                    'permissao' => 'Apenas gestor ou administrador da operação pode estornar pagamentos.',
                ]);
            }

            if ($pagamento->isEstornado()) {
                throw ValidationException::withMessages(['pagamento' => 'Este pagamento já foi estornado.']);
            }

            if ($pagamento->isProdutoObjeto()) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Estorno técnico não está disponível para pagamento em produto/objeto.',
                ]);
            }

            if ($pagamento->isPendenteAceite()) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Não é possível estornar um pagamento aguardando aceite em produto/objeto.',
                ]);
            }

            $ledgerEntrada = $this->resolverLedgerEntrada($pagamento);
            if (! $ledgerEntrada || ! $ledgerEntrada->isEntrada()) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Não há lançamento de caixa vinculado a este recebimento para estornar.',
                ]);
            }

            if (PagamentoEstornoFechamentoGate::lancamentoConsolidado($ledgerEntrada)) {
                throw ValidationException::withMessages([
                    'pagamento' => 'Este recebimento já consta em fechamento de caixa concluído. O estorno não é permitido.',
                ]);
            }

            $grupo = $this->pagamentosDoMesmoRecebimento($pagamento, $ledgerEntrada);

            foreach ($grupo as $p) {
                if ($p->isEstornado()) {
                    throw ValidationException::withMessages(['pagamento' => 'Um dos pagamentos do grupo já foi estornado.']);
                }
            }

            $parcelaIds = $grupo->pluck('parcela_id')->unique()->values()->all();
            Parcela::whereIn('id', $parcelaIds)->lockForUpdate()->get();

            foreach ($grupo as $p) {
                $this->reverterParcela($p);
            }

            $emprestimo->refresh();
            $this->reativarEmprestimoSeNecessario($emprestimo);

            $dataHoje = Carbon::today();
            $descricao = $ledgerEntrada->referencia_tipo === 'quitacao_emprestimo'
                ? 'Estorno de quitação – empréstimo #'.$emprestimo->id
                : 'Estorno de recebimento – pagamento #'.$pagamento->id;

            $saida = $this->cashService->registrarMovimentacao([
                'operacao_id' => $ledgerEntrada->operacao_id,
                'consultor_id' => $ledgerEntrada->consultor_id,
                'pagamento_id' => null,
                'tipo' => 'saida',
                'origem' => 'automatica',
                'valor' => $ledgerEntrada->valor,
                'data_movimentacao' => $dataHoje,
                'descricao' => $descricao,
                'referencia_tipo' => 'estorno_pagamento',
                'referencia_id' => $pagamento->id,
                'observacoes' => mb_substr($motivo, 0, 65000),
            ]);

            $now = now();
            foreach ($grupo as $p) {
                $p->update([
                    'estornado_em' => $now,
                    'estornado_por_user_id' => $gestor->id,
                    'estorno_motivo' => $motivo,
                    'estorno_cash_ledger_entry_id' => $saida->id,
                ]);
            }

            self::auditar('estornar_pagamento', $pagamento, null, [
                'pagamento_ids' => $grupo->pluck('id')->all(),
                'cash_ledger_entrada_id' => $ledgerEntrada->id,
                'cash_ledger_saida_id' => $saida->id,
                'motivo' => $motivo,
            ]);
        });
    }

    private function resolverLedgerEntrada(Pagamento $pagamento): ?CashLedgerEntry
    {
        $pagamento->loadMissing('cashLedgerEntry');

        if ($pagamento->cashLedgerEntry) {
            return $pagamento->cashLedgerEntry;
        }

        $emprestimoId = (int) $pagamento->parcela->emprestimo_id;

        $query = CashLedgerEntry::where('referencia_tipo', 'quitacao_emprestimo')
            ->where('referencia_id', $emprestimoId)
            ->where('consultor_id', $pagamento->consultor_id)
            ->whereDate('data_movimentacao', $pagamento->data_pagamento->format('Y-m-d'))
            ->where('tipo', 'entrada');

        if ($query->count() > 1) {
            throw ValidationException::withMessages([
                'pagamento' => 'Há mais de uma quitação nesta data; não é possível identificar o lançamento sem o vínculo de grupo. Contate o suporte.',
            ]);
        }

        return $query->first();
    }

    private function pagamentosDoMesmoRecebimento(Pagamento $pagamento, CashLedgerEntry $ledger): Collection
    {
        if ($pagamento->quitacao_grupo_id) {
            return Pagamento::where('quitacao_grupo_id', $pagamento->quitacao_grupo_id)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
        }

        if ($ledger->referencia_tipo === 'quitacao_emprestimo') {
            $ids = $this->legacyIdsPagamentosQuitacao($ledger);

            return Pagamento::whereIn('id', $ids)->lockForUpdate()->orderBy('id')->get();
        }

        return new Collection([$pagamento]);
    }

    /**
     * @return int[]
     */
    private function legacyIdsPagamentosQuitacao(CashLedgerEntry $ledger): array
    {
        $firstId = $ledger->pagamento_id;
        if (! $firstId) {
            throw ValidationException::withMessages([
                'pagamento' => 'Lançamento de quitação sem pagamento de referência.',
            ]);
        }

        $first = Pagamento::with('parcela')->findOrFail($firstId);
        $emprestimoId = (int) $first->parcela->emprestimo_id;
        $consultorId = (int) $first->consultor_id;
        $dataPag = $first->data_pagamento->format('Y-m-d');
        $target = (float) $ledger->valor;

        $candidatos = Pagamento::query()
            ->whereHas('parcela', fn ($q) => $q->where('emprestimo_id', $emprestimoId))
            ->where('consultor_id', $consultorId)
            ->whereDate('data_pagamento', $dataPag)
            ->where('id', '>=', $firstId)
            ->orderBy('id')
            ->get();

        $sum = 0;
        $ids = [];
        foreach ($candidatos as $c) {
            $ids[] = $c->id;
            $sum = round($sum + (float) $c->valor, 2);
            if (abs($sum - $target) < 0.02) {
                return $ids;
            }
            if ($sum - $target > 0.02) {
                break;
            }
        }

        throw ValidationException::withMessages([
            'pagamento' => 'Não foi possível determinar o grupo de pagamentos desta quitação para estorno.',
        ]);
    }

    private function reverterParcela(Pagamento $p): void
    {
        $parcela = Parcela::findOrFail($p->parcela_id);
        $novoValorPago = round((float) $parcela->valor_pago - (float) $p->valor, 2);
        if ($novoValorPago < 0) {
            throw ValidationException::withMessages([
                'parcela' => 'Inconsistência ao reverter a parcela #'.$parcela->numero.'.',
            ]);
        }

        $estaPaga = $novoValorPago >= (float) $parcela->valor - 0.01;
        $attrs = [
            'valor_pago' => max(0, $novoValorPago),
            'status' => $estaPaga ? 'paga' : 'pendente',
        ];
        if (! $estaPaga) {
            $attrs['data_pagamento'] = null;
        }

        $parcela->update($attrs);

        if (! $estaPaga) {
            $parcela->refresh();
            $parcela->update(['dias_atraso' => $parcela->calcularDiasAtraso()]);
        }
    }

    private function reativarEmprestimoSeNecessario(Emprestimo $emprestimo): void
    {
        $emprestimo->refresh();
        if ($emprestimo->status !== 'finalizado') {
            return;
        }

        $strategy = LoanStrategyFactory::create($emprestimo);
        if (! $strategy->podeFinalizar($emprestimo)) {
            $antes = $emprestimo->status;
            $emprestimo->update(['status' => 'ativo']);

            self::auditar(
                'reativar_emprestimo_estorno_pagamento',
                $emprestimo,
                ['status' => $antes],
                ['status' => 'ativo']
            );
        }
    }
}
