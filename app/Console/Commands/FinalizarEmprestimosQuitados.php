<?php

namespace App\Console\Commands;

use App\Modules\Core\Traits\Auditable;
use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizarEmprestimosQuitados extends Command
{
    use Auditable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emprestimos:finalizar-quitados 
                            {--dry-run : Apenas simula, não faz alterações}
                            {--empresa-id= : Filtrar por empresa específica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finaliza automaticamente empréstimos ativos que já tiveram todas as parcelas pagas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $empresaId = $this->option('empresa-id');

        $this->info('');
        $this->info('===========================================');
        $this->info('  FINALIZAR EMPRÉSTIMOS QUITADOS');
        $this->info('===========================================');
        $this->info('');

        if ($isDryRun) {
            $this->warn('🔍 MODO SIMULAÇÃO - Nenhuma alteração será feita');
            $this->info('');
        }

        // Buscar empréstimos ativos (sem global scope para pegar de todas as empresas)
        $query = Emprestimo::withoutGlobalScopes()
            ->where('status', 'ativo')
            ->with(['parcelas', 'cliente', 'operacao']);

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
            $this->info("📌 Filtrando por empresa_id: {$empresaId}");
        }

        $emprestimosAtivos = $query->get();

        $this->info("📊 Total de empréstimos ativos encontrados: {$emprestimosAtivos->count()}");
        $this->info('');

        $finalizados = 0;
        $jaFinalizados = [];

        foreach ($emprestimosAtivos as $emprestimo) {
            $totalParcelas = $emprestimo->parcelas->count();
            // Considerar parcelas pagas OU quitadas por garantia
            $parcelasQuitadas = $emprestimo->parcelas->filter(function ($parcela) {
                return $parcela->status === 'paga' || $parcela->status === 'quitada_garantia';
            })->count();

            // Verificar se todas as parcelas estão quitadas (pagas ou quitadas por garantia)
            if ($totalParcelas > 0 && $parcelasQuitadas === $totalParcelas) {
                $clienteNome = $emprestimo->cliente->nome ?? 'N/A';
                $operacaoNome = $emprestimo->operacao->nome ?? 'N/A';
                $valorTotal = number_format($emprestimo->valor_total, 2, ',', '.');

                $this->info("✅ Empréstimo #{$emprestimo->id}");
                $this->info("   Cliente: {$clienteNome}");
                $this->info("   Operação: {$operacaoNome}");
                $this->info("   Valor: R$ {$valorTotal}");
                $this->info("   Parcelas: {$parcelasQuitadas}/{$totalParcelas} quitadas");

                if (!$isDryRun) {
                    DB::transaction(function () use ($emprestimo) {
                        $statusAnterior = $emprestimo->status;
                        
                        $emprestimo->update([
                            'status' => 'finalizado',
                        ]);

                        // Auditoria
                        self::auditar(
                            'finalizar_emprestimo',
                            $emprestimo,
                            ['status' => $statusAnterior],
                            ['status' => 'finalizado'],
                            'Empréstimo finalizado via comando artisan - Todas as parcelas já estavam pagas'
                        );
                    });

                    $this->info("   ➡️  Status alterado para: FINALIZADO");
                } else {
                    $this->info("   ➡️  [SIMULAÇÃO] Seria finalizado");
                }

                $this->info('');
                $finalizados++;
                
                $jaFinalizados[] = [
                    'id' => $emprestimo->id,
                    'cliente' => $clienteNome,
                    'valor' => "R$ {$valorTotal}",
                    'parcelas' => "{$parcelasPagas}/{$totalParcelas}",
                ];
            }
        }

        // Resumo final
        $this->info('===========================================');
        $this->info('  RESUMO');
        $this->info('===========================================');
        $this->info('');
        $this->info("📊 Empréstimos ativos analisados: {$emprestimosAtivos->count()}");
        
        if ($isDryRun) {
            $this->warn("🔍 Empréstimos que SERIAM finalizados: {$finalizados}");
        } else {
            $this->info("✅ Empréstimos finalizados: {$finalizados}");
        }

        $this->info("⏳ Empréstimos ainda pendentes: " . ($emprestimosAtivos->count() - $finalizados));
        $this->info('');

        if ($finalizados > 0) {
            $this->table(
                ['ID', 'Cliente', 'Valor', 'Parcelas'],
                $jaFinalizados
            );
        }

        if ($isDryRun && $finalizados > 0) {
            $this->info('');
            $this->warn('💡 Para executar de verdade, rode sem --dry-run:');
            $this->warn('   php artisan emprestimos:finalizar-quitados');
        }

        $this->info('');
        $this->info('Concluído!');
        
        return Command::SUCCESS;
    }
}
