<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\OperationClient;
use App\Modules\Loans\Models\Emprestimo;
use Illuminate\Console\Command;

class SincronizarVinculosClientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clientes:sincronizar-vinculos 
                            {--dry-run : Apenas mostrar o que seria feito, sem executar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria vínculos entre clientes e operações baseado nos empréstimos existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Modo DRY-RUN: Nenhuma alteração será feita no banco de dados.');
            $this->newLine();
        }

        // Buscar todas as combinações únicas de cliente + operação
        $combinacoes = Emprestimo::select('cliente_id', 'operacao_id')
            ->selectRaw('MAX(consultor_id) as consultor_id')
            ->groupBy('cliente_id', 'operacao_id')
            ->get();

        $this->info("📊 Encontradas " . $combinacoes->count() . " combinações únicas de cliente + operação.");
        $this->newLine();

        $criados = 0;
        $atualizados = 0;
        $jaExistentes = 0;

        $bar = $this->output->createProgressBar($combinacoes->count());
        $bar->start();

        foreach ($combinacoes as $combinacao) {
            $vinculo = OperationClient::withTrashed()
                ->where('cliente_id', $combinacao->cliente_id)
                ->where('operacao_id', $combinacao->operacao_id)
                ->first();

            if (! $vinculo) {
                // Criar novo vínculo
                if (! $dryRun) {
                    OperationClient::create([
                        'cliente_id' => $combinacao->cliente_id,
                        'operacao_id' => $combinacao->operacao_id,
                        'limite_credito' => 0, // Sem limite definido
                        'status' => 'ativo',
                        'consultor_id' => $combinacao->consultor_id,
                    ]);
                }
                $criados++;
            } else {
                if ($vinculo->trashed() && ! $dryRun) {
                    $vinculo->restore();
                }
                // Vínculo já existe (ou foi restaurado)
                if (! $vinculo->consultor_id && $combinacao->consultor_id) {
                    // Atualizar consultor se não estiver definido
                    if (! $dryRun) {
                        $vinculo->update(['consultor_id' => $combinacao->consultor_id]);
                    }
                    $atualizados++;
                } else {
                    $jaExistentes++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Resumo
        $this->info('✅ Sincronização concluída!');
        $this->newLine();
        $this->table(
            ['Ação', 'Quantidade'],
            [
                ['Novos vínculos criados', $criados],
                ['Vínculos atualizados', $atualizados],
                ['Vínculos já existentes', $jaExistentes],
                ['Total processado', $combinacoes->count()],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  Este foi um DRY-RUN. Execute sem --dry-run para aplicar as alterações.');
        }

        return Command::SUCCESS;
    }
}
