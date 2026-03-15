<?php

namespace App\Modules\Loans\Services;

use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ParcelaService
{
    use Auditable;

    /**
     * Listar cobranças do dia (vencendo hoje e atrasadas)
     *
     * @param int|null $operacaoId Filtrar por uma operação
     * @param int|null $consultorId Filtrar por consultor
     * @param array|null $operacaoIds Restringir a essas operações (usado quando $operacaoId é null)
     * @return Collection
     */
    public function cobrancasDoDia(?int $operacaoId = null, ?int $consultorId = null, ?array $operacaoIds = null): Collection
    {
        $query = Parcela::with(['emprestimo.cliente', 'emprestimo.consultor', 'emprestimo.operacao'])
            ->whereIn('status', ['pendente', 'atrasada'])
            ->where(function ($q) {
                $q->where('data_vencimento', '<=', Carbon::today())
                    ->orWhere('data_vencimento', Carbon::today());
            });

        if ($operacaoId) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoId) {
                $q->where('operacao_id', $operacaoId);
            });
        } elseif (!empty($operacaoIds)) {
            $query->whereHas('emprestimo', function ($q) use ($operacaoIds) {
                $q->whereIn('operacao_id', $operacaoIds);
            });
        }

        if ($consultorId) {
            $query->whereHas('emprestimo', function ($q) use ($consultorId) {
                $q->where('consultor_id', $consultorId);
            });
        }

        return $query->orderBy('data_vencimento', 'asc')->get();
    }

    /**
     * Marcar parcela como paga
     *
     * @param int $parcelaId
     * @param float $valorPago
     * @param Carbon|null $dataPagamento
     * @return Parcela
     */
    public function marcarComoPaga(
        int $parcelaId,
        float $valorPago,
        ?Carbon $dataPagamento = null
    ): Parcela {
        $parcela = Parcela::findOrFail($parcelaId);

        if ($parcela->isQuitada()) {
            $mensagem = $parcela->isQuitadaGarantia()
                ? 'Parcela já foi quitada via execução de garantia.'
                : 'Parcela já está paga.';
            throw new \Exception($mensagem);
        }

        $oldStatus = $parcela->status;
        $oldValorPago = $parcela->valor_pago;

        $parcela->update([
            'valor_pago' => $valorPago,
            'data_pagamento' => $dataPagamento ?? Carbon::today(),
            'status' => $parcela->isTotalmentePaga() ? 'paga' : 'pendente',
            'dias_atraso' => 0, // Resetar atraso
        ]);

        // Auditoria
        self::auditar(
            'marcar_parcela_paga',
            $parcela,
            [
                'status' => $oldStatus,
                'valor_pago' => $oldValorPago,
            ],
            [
                'status' => $parcela->status,
                'valor_pago' => $valorPago,
            ]
        );

        return $parcela->fresh();
    }

    /**
     * Marcar parcelas atrasadas (chamado por job/scheduler)
     *
     * @return int Número de parcelas marcadas
     */
    public function marcarAtrasadas(): int
    {
        $hoje = Carbon::today();
        
        $parcelas = Parcela::where('status', 'pendente')
            ->where('data_vencimento', '<', $hoje)
            ->get();

        $count = 0;
        foreach ($parcelas as $parcela) {
            $diasAtraso = $parcela->calcularDiasAtraso();
            
            $parcela->update([
                'status' => 'atrasada',
                'dias_atraso' => $diasAtraso,
            ]);
            
            $count++;
        }

        return $count;
    }

    /**
     * Marcar como atrasadas as parcelas vencidas de um empréstimo (ex.: retroativo ativado).
     * Evita esperar o cron para ver parcelas já vencidas como atrasadas.
     *
     * @param Emprestimo $emprestimo
     * @return int Número de parcelas marcadas como atrasada
     */
    public function marcarAtrasadasDoEmprestimo(Emprestimo $emprestimo): int
    {
        $hoje = Carbon::today();

        $parcelas = Parcela::where('emprestimo_id', $emprestimo->id)
            ->where('status', 'pendente')
            ->where('data_vencimento', '<', $hoje)
            ->get();

        $count = 0;
        foreach ($parcelas as $parcela) {
            $diasAtraso = $parcela->calcularDiasAtraso();
            $parcela->update([
                'status' => 'atrasada',
                'dias_atraso' => $diasAtraso,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Calcular dias de atraso de uma parcela
     *
     * @param int $parcelaId
     * @return int
     */
    public function calcularDiasAtraso(int $parcelaId): int
    {
        $parcela = Parcela::findOrFail($parcelaId);
        return $parcela->calcularDiasAtraso();
    }
}

