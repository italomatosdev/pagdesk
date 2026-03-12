<?php

namespace App\Console\Commands;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorrigirParcelasRenovadas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emprestimos:corrigir-parcelas-renovadas 
                            {--dry-run : Apenas mostrar o que seria feito, sem executar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige parcelas de empréstimos renovados que ainda estão pendentes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: Nenhuma alteração será feita no banco de dados.');
            $this->newLine();
        }

        // Buscar empréstimos que foram renovados (têm renovacoes) e estão finalizados
        $emprestimosRenovados = Emprestimo::where('status', 'finalizado')
            ->whereHas('renovacoes')
            ->with(['parcelas', 'renovacoes'])
            ->get();

        $this->info("📊 Encontrados " . $emprestimosRenovados->count() . " empréstimo(s) renovado(s).");
        $this->newLine();

        $corrigidas = 0;
        $jaCorrigidas = 0;

        foreach ($emprestimosRenovados as $emprestimo) {
            // Verificar se é mensal com 1 parcela
            if ($emprestimo->frequencia !== 'mensal' || $emprestimo->numero_parcelas !== 1) {
                continue;
            }

            $parcela = $emprestimo->parcelas->first();
            if (!$parcela) {
                continue;
            }

            // Verificar se a parcela já está paga
            if ($parcela->status === 'paga' && $parcela->isTotalmentePaga()) {
                $jaCorrigidas++;
                continue;
            }

            // Verificar se tem pagamento de juros registrado
            $valorJuros = $emprestimo->calcularValorJuros();
            $temPagamentoJuros = $parcela->valor_pago >= $valorJuros;

            if ($temPagamentoJuros) {
                $this->info("Empréstimo #{$emprestimo->id} - Parcela #{$parcela->numero}:");
                $this->line("  Valor Total: R$ " . number_format($parcela->valor, 2, ',', '.'));
                $this->line("  Valor Pago: R$ " . number_format($parcela->valor_pago, 2, ',', '.'));
                $this->line("  Status Atual: {$parcela->status}");
                $this->line("  → Será marcada como PAGA");

                if (!$dryRun) {
                    DB::transaction(function () use ($parcela) {
                        $parcela->update([
                            'valor_pago' => $parcela->valor, // Marca como totalmente paga
                            'status' => 'paga',
                            'data_pagamento' => $parcela->data_pagamento ?? now(),
                        ]);
                    });
                }

                $corrigidas++;
                $this->newLine();
            }
        }

        // Resumo
        $this->info('✅ Correção concluída!');
        $this->newLine();
        $this->table(
            ['Ação', 'Quantidade'],
            [
                ['Parcelas corrigidas', $corrigidas],
                ['Parcelas já corretas', $jaCorrigidas],
                ['Total processado', $emprestimosRenovados->count()],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  Este foi um DRY-RUN. Execute sem --dry-run para aplicar as alterações.');
        }

        return Command::SUCCESS;
    }
}
