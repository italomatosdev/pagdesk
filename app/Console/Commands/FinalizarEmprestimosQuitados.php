<?php

namespace App\Console\Commands;

use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Services\PagamentoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizarEmprestimosQuitados extends Command
{
    /**
     * @var string
     */
    protected $signature = 'emprestimos:finalizar-quitados
                            {--dry-run : Apenas simula, não faz alterações}
                            {--empresa-id= : Filtrar por empresa específica}
                            {--id=* : Apenas estes IDs de empréstimo (pode repetir a opção)}';

    /**
     * @var string
     */
    protected $description = 'Finaliza empréstimos ativos cujas parcelas já estão todas pagas (usa PagamentoService: auditoria e empenho/garantias)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $empresaId = $this->option('empresa-id');
        $idsFiltro = array_filter(array_map('intval', (array) $this->option('id')));

        $this->newLine();
        $this->info('===========================================');
        $this->info('  FINALIZAR EMPRÉSTIMOS QUITADOS');
        $this->info('===========================================');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('MODO SIMULAÇÃO (--dry-run) — nenhuma alteração será feita.');
            $this->newLine();
        }

        $query = Emprestimo::withoutGlobalScopes()
            ->where('status', 'ativo')
            ->with(['parcelas', 'cliente', 'operacao']);

        if ($idsFiltro !== []) {
            $query->whereIn('id', $idsFiltro);
            $this->info('Filtrando IDs: '.implode(', ', $idsFiltro));
            $this->newLine();
        }

        if ($empresaId) {
            $query->where('empresa_id', (int) $empresaId);
            $this->info("Filtrando empresa_id: {$empresaId}");
            $this->newLine();
        }

        $emprestimosAtivos = $query->orderBy('id')->get();

        $this->info('Empréstimos ativos na seleção: '.$emprestimosAtivos->count());
        $this->newLine();

        $candidatos = 0;
        $linhasTabela = [];
        /** @var PagamentoService $pagamentoService */
        $pagamentoService = app(PagamentoService::class);

        foreach ($emprestimosAtivos as $emprestimo) {
            if (! $emprestimo->todasParcelasPagas()) {
                continue;
            }

            $candidatos++;
            $totalParcelas = $emprestimo->parcelas->count();
            $clienteNome = $emprestimo->cliente->nome ?? 'N/A';
            $operacaoNome = $emprestimo->operacao->nome ?? 'N/A';
            $valorTotal = number_format((float) $emprestimo->valor_total, 2, ',', '.');

            $this->info("Empréstimo #{$emprestimo->id}");
            $this->line("   Cliente: {$clienteNome}");
            $this->line("   Operação: {$operacaoNome}");
            $this->line("   Valor: R$ {$valorTotal}");
            $this->line("   Parcelas quitadas: {$totalParcelas}/{$totalParcelas}");

            if (! $isDryRun) {
                try {
                    DB::transaction(function () use ($pagamentoService, $emprestimo) {
                        $pagamentoService->verificarEFinalizarEmprestimo(
                            $emprestimo->fresh(['parcelas'])
                        );
                    });
                    $this->line('   Status: finalizado (via PagamentoService).');
                } catch (\Throwable $e) {
                    $this->error('   Erro: '.$e->getMessage());

                    return Command::FAILURE;
                }
            } else {
                $this->line('   [dry-run] Seria finalizado.');
            }

            $this->newLine();

            $linhasTabela[] = [
                'id' => $emprestimo->id,
                'cliente' => $clienteNome,
                'valor' => "R$ {$valorTotal}",
                'parcelas' => "{$totalParcelas}/{$totalParcelas}",
            ];
        }

        $this->info('===========================================');
        $this->info('  RESUMO');
        $this->info('===========================================');
        $this->newLine();

        if ($isDryRun) {
            $this->warn("Seriam finalizados: {$candidatos}");
        } else {
            $this->info("Finalizados (ou já elegíveis processados): {$candidatos}");
        }

        $this->info('Analisados na seleção: '.$emprestimosAtivos->count());
        $this->newLine();

        if ($linhasTabela !== []) {
            $this->table(
                ['ID', 'Cliente', 'Valor', 'Parcelas'],
                $linhasTabela
            );
        }

        if ($isDryRun && $candidatos > 0) {
            $this->newLine();
            $this->warn('Para executar de verdade, rode sem --dry-run:');
            $this->line('   php artisan emprestimos:finalizar-quitados');
            if ($idsFiltro !== []) {
                $idsStr = implode(' ', array_map(fn (int $i) => '--id='.$i, $idsFiltro));
                $this->line("   php artisan emprestimos:finalizar-quitados {$idsStr}");
            }
        }

        $this->newLine();
        $this->info('Concluído.');

        return Command::SUCCESS;
    }
}
