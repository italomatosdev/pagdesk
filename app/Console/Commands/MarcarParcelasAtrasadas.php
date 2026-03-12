<?php

namespace App\Console\Commands;

use App\Modules\Loans\Services\ParcelaService;
use App\Services\ScheduledTaskRunService;
use Illuminate\Console\Command;

class MarcarParcelasAtrasadas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parcelas:marcar-atrasadas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marca parcelas pendentes como atrasadas baseado na data de vencimento';

    /**
     * Execute the console command.
     */
    public function handle(ParcelaService $parcelaService, ScheduledTaskRunService $taskRunService): int
    {
        $taskName = 'parcelas:marcar-atrasadas';
        $run = $taskRunService->start($taskName);

        try {
            $this->info('Marcando parcelas atrasadas...');

            $count = $parcelaService->marcarAtrasadas();

            $taskRunService->success($run, "{$count} parcela(s) marcada(s) como atrasada(s).");
            $this->info("{$count} parcela(s) marcada(s) como atrasada(s).");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $taskRunService->fail($run, $e->getMessage());
            $this->error('Erro: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
