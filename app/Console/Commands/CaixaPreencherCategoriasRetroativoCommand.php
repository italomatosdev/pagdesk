<?php

namespace App\Console\Commands;

use App\Modules\Cash\Models\CashLedgerEntry;
use App\Modules\Cash\Services\CashCategoriaAutomaticaService;
use Illuminate\Console\Command;

/**
 * Preenche categoria_id em cash_ledger_entries onde está nulo,
 * usando referencia_tipo + tipo (entrada/saida) e o mesmo mapeamento do CashService.
 */
class CaixaPreencherCategoriasRetroativoCommand extends Command
{
    protected $signature = 'caixa:preencher-categorias-retroativo
                            {--dry-run : Apenas mostra quantos seriam atualizados, sem gravar}';

    protected $description = 'Preenche retroativamente categoria_id nas movimentações de caixa pelo referencia_tipo';

    public function handle(CashCategoriaAutomaticaService $service): int
    {
        $dryRun = $this->option('dry-run');

        $query = CashLedgerEntry::withoutGlobalScopes()
            ->with('operacao')
            ->whereNull('categoria_id')
            ->whereNotNull('referencia_tipo')
            ->whereIn('tipo', ['entrada', 'saida']);

        $total = $query->count();
        if ($total === 0) {
            $this->info('Nenhuma movimentação sem categoria com referencia_tipo encontrada.');
            return self::SUCCESS;
        }

        $this->info("Encontradas {$total} movimentações sem categoria.");
        if ($dryRun) {
            $this->warn('Modo --dry-run: nenhuma alteração será gravada.');
        }

        $atualizadas = 0;
        $ignoradas = 0;

        $query->orderBy('id')->chunkById(500, function ($entries) use ($service, $dryRun, &$atualizadas, &$ignoradas) {
            foreach ($entries as $entry) {
                $empresaId = $entry->empresa_id ?? $entry->operacao?->empresa_id;
                if ($empresaId === null) {
                    $ignoradas++;
                    continue;
                }
                $categoriaId = $service->resolverCategoriaId(
                    (int) $empresaId,
                    $entry->referencia_tipo,
                    $entry->tipo
                );
                if ($categoriaId === null) {
                    $ignoradas++;
                    continue;
                }
                if (!$dryRun) {
                    CashLedgerEntry::withoutGlobalScopes()
                        ->where('id', $entry->id)
                        ->update(['categoria_id' => $categoriaId]);
                }
                $atualizadas++;
            }
        });

        $this->info("Atualizadas (com mapeamento): {$atualizadas}");
        if ($ignoradas > 0) {
            $this->comment("Sem mapeamento para referencia_tipo|tipo (ignoradas): {$ignoradas}");
        }

        return self::SUCCESS;
    }
}
