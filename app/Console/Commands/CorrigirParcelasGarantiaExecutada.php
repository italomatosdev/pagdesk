<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Auditoria;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorrigirParcelasGarantiaExecutada extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emprestimos:corrigir-parcelas-garantia-executada 
                            {--dry-run : Apenas simula, não faz alterações}
                            {--force : Executa as alterações (sem dry-run)}
                            {--empresa-id= : Filtrar por empresa específica}
                            {--emprestimo-id= : Corrigir apenas um empréstimo específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige registros retroativos: marca parcelas não pagas como quitada_garantia em empréstimos tipo empenho finalizados com garantia executada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run') && !$this->option('force');
        $empresaId = $this->option('empresa-id');
        $emprestimoId = $this->option('emprestimo-id');

        $this->info('');
        $this->info('===========================================');
        $this->info('  CORRIGIR PARCELAS - GARANTIA EXECUTADA');
        $this->info('===========================================');
        $this->info('');

        if ($isDryRun) {
            $this->warn('🔍 MODO SIMULAÇÃO - Nenhuma alteração será feita');
            $this->info('');
        } else {
            $this->warn('⚠️  MODO EXECUÇÃO - Alterações serão aplicadas!');
            $this->info('');
        }

        // Buscar empréstimos que atendem aos critérios
        $query = Emprestimo::withoutGlobalScopes()
            ->where('tipo', 'empenho')
            ->where('status', 'finalizado')
            ->whereHas('garantias', function ($q) {
                $q->where('status', 'executada')
                  ->whereNotNull('data_execucao');
            })
            ->whereHas('parcelas', function ($q) {
                // Parcelas que não estão pagas E não estão já como quitada_garantia
                $q->where('status', '!=', 'paga')
                  ->where('status', '!=', 'quitada_garantia')
                  ->where(function ($q2) {
                      $q2->where('valor_pago', 0)
                         ->orWhereColumn('valor_pago', '<', 'valor');
                  });
            })
            ->with([
                'parcelas' => function ($q) {
                    $q->where('status', '!=', 'paga')
                      ->where('status', '!=', 'quitada_garantia');
                },
                'garantias' => function ($q) {
                    $q->where('status', 'executada');
                },
                'cliente',
                'operacao'
            ]);

        // Filtros opcionais
        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($emprestimoId) {
            $query->where('id', $emprestimoId);
        }

        $emprestimos = $query->get();

        if ($emprestimos->isEmpty()) {
            $this->info('✅ Nenhum empréstimo encontrado para correção.');
            $this->info('');
            return 0;
        }

        $this->info("📊 Encontrados {$emprestimos->count()} empréstimo(s) para correção:");
        $this->info('');

        $totalCorrigidos = 0;
        $totalParcelasCorrigidas = 0;
        $totalParcelasPreservadas = 0;
        $erros = [];

        foreach ($emprestimos as $emprestimo) {
            try {
                $resultado = $this->processarEmprestimo($emprestimo, $isDryRun);
                
                if ($resultado['corrigido']) {
                    $totalCorrigidos++;
                    $totalParcelasCorrigidas += $resultado['parcelas_corrigidas'];
                    $totalParcelasPreservadas += $resultado['parcelas_preservadas'];
                }
            } catch (\Exception $e) {
                $erros[] = [
                    'emprestimo_id' => $emprestimo->id,
                    'erro' => $e->getMessage()
                ];
                $this->error("❌ Erro ao processar empréstimo #{$emprestimo->id}: {$e->getMessage()}");
            }
        }

        // Resumo final
        $this->info('');
        $this->info('===========================================');
        $this->info('  RESUMO');
        $this->info('===========================================');
        $this->info("Empréstimos processados: {$emprestimos->count()}");
        $this->info("Empréstimos corrigidos: {$totalCorrigidos}");
        $this->info("Parcelas corrigidas: {$totalParcelasCorrigidas}");
        $this->info("Parcelas preservadas (já pagas): {$totalParcelasPreservadas}");
        
        if (!empty($erros)) {
            $this->warn("Erros encontrados: " . count($erros));
            foreach ($erros as $erro) {
                $this->error("  - Empréstimo #{$erro['emprestimo_id']}: {$erro['erro']}");
            }
        }

        $this->info('');

        if ($isDryRun) {
            $this->info('💡 Para executar as alterações, rode o comando com --force');
        }

        return 0;
    }

    /**
     * Processa um empréstimo específico
     */
    private function processarEmprestimo(Emprestimo $emprestimo, bool $isDryRun): array
    {
        // Recarregar todas as parcelas (não apenas as não pagas)
        $emprestimo->load('parcelas');
        
        // Buscar garantia executada
        $garantiaExecutada = $emprestimo->garantias
            ->where('status', 'executada')
            ->whereNotNull('data_execucao')
            ->first();

        if (!$garantiaExecutada) {
            return [
                'corrigido' => false,
                'parcelas_corrigidas' => 0,
                'parcelas_preservadas' => 0,
                'motivo' => 'Garantia executada não encontrada'
            ];
        }

        // Separar parcelas: pagas vs não pagas
        $parcelasPagas = $emprestimo->parcelas->where('status', 'paga');
        $parcelasNaoPagas = $emprestimo->parcelas->filter(function ($parcela) {
            return $parcela->status !== 'paga' 
                && $parcela->status !== 'quitada_garantia'
                && ($parcela->valor_pago == 0 || $parcela->valor_pago < $parcela->valor);
        });

        // Verificar se há parcelas para corrigir
        if ($parcelasNaoPagas->isEmpty()) {
            return [
                'corrigido' => false,
                'parcelas_corrigidas' => 0,
                'parcelas_preservadas' => $parcelasPagas->count(),
                'motivo' => 'Todas as parcelas já estão pagas ou quitadas por garantia'
            ];
        }

        // Validar: não deve ter pagamentos registrados para parcelas não pagas
        foreach ($parcelasNaoPagas as $parcela) {
            $parcela->load('pagamentos');
            if ($parcela->pagamentos->count() > 0) {
                return [
                    'corrigido' => false,
                    'parcelas_corrigidas' => 0,
                    'parcelas_preservadas' => $parcelasPagas->count(),
                    'motivo' => "Parcela #{$parcela->numero} possui pagamentos registrados"
                ];
            }
        }

        // Exibir informações
        $clienteNome = $emprestimo->cliente->nome ?? 'N/A';
        $operacaoNome = $emprestimo->operacao->nome ?? 'N/A';
        $dataExecucao = $garantiaExecutada->data_execucao 
            ? Carbon::parse($garantiaExecutada->data_execucao)->format('d/m/Y H:i')
            : 'N/A';

        $this->info("📋 Empréstimo #{$emprestimo->id}");
        $this->line("   Cliente: {$clienteNome}");
        $this->line("   Operação: {$operacaoNome}");
        $this->line("   Garantia executada em: {$dataExecucao}");
        $this->line("   Total de parcelas: {$emprestimo->parcelas->count()}");
        $this->line("   Parcelas já pagas: {$parcelasPagas->count()} (serão preservadas)");
        $this->line("   Parcelas a corrigir: {$parcelasNaoPagas->count()}");

        // Listar parcelas a corrigir
        foreach ($parcelasNaoPagas as $parcela) {
            $statusAtual = $parcela->status;
            $valorPago = number_format($parcela->valor_pago, 2, ',', '.');
            $valorTotal = number_format($parcela->valor, 2, ',', '.');
            
            $this->line("     → Parcela #{$parcela->numero}:");
            $this->line("        Status atual: {$statusAtual}");
            $this->line("        Valor: R$ {$valorTotal} (pago: R$ {$valorPago})");
            $this->line("        Será marcada como: quitada_garantia");
        }

        if ($isDryRun) {
            $this->info("   ✅ [SIMULAÇÃO] Seria corrigido");
            $this->info('');
            
            return [
                'corrigido' => true,
                'parcelas_corrigidas' => $parcelasNaoPagas->count(),
                'parcelas_preservadas' => $parcelasPagas->count()
            ];
        }

        // Executar correção
        try {
            DB::transaction(function () use ($emprestimo, $parcelasNaoPagas, $garantiaExecutada) {
                $dataExecucao = $garantiaExecutada->data_execucao 
                    ? Carbon::parse($garantiaExecutada->data_execucao)
                    : Carbon::now();

                foreach ($parcelasNaoPagas as $parcela) {
                    $oldStatus = $parcela->status;
                    $oldValorPago = $parcela->valor_pago;

                    $parcela->update([
                        'status' => 'quitada_garantia',
                        'valor_pago' => 0,
                        'data_pagamento' => $dataExecucao,
                        'dias_atraso' => 0,
                    ]);

                    // Registrar auditoria
                    $this->auditar(
                        'corrigir_parcela_retroativa_garantia',
                        $parcela,
                        [
                            'status' => $oldStatus,
                            'valor_pago' => $oldValorPago,
                        ],
                        [
                            'status' => 'quitada_garantia',
                            'valor_pago' => 0,
                            'data_pagamento' => $parcela->data_pagamento,
                        ],
                        "Parcela corrigida retroativamente - Garantia executada em {$dataExecucao->format('d/m/Y H:i')} (comando artisan)"
                    );
                }
            });

            $this->info("   ✅ Corrigido com sucesso!");
            $this->info('');

            return [
                'corrigido' => true,
                'parcelas_corrigidas' => $parcelasNaoPagas->count(),
                'parcelas_preservadas' => $parcelasPagas->count()
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Registrar auditoria (método auxiliar)
     * Adaptado para funcionar em comandos CLI (sem request HTTP)
     */
    private function auditar(
        string $tipo,
        $model,
        array $antes,
        array $depois,
        string $observacoes = ''
    ): void {
        // Registrar auditoria diretamente (comandos CLI não têm request HTTP)
        Auditoria::create([
            'user_id' => null, // Comando CLI não tem usuário autenticado
            'action' => $tipo,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'old_values' => $antes,
            'new_values' => $depois,
            'ip_address' => null, // Comando CLI não tem IP
            'user_agent' => 'Artisan Command: emprestimos:corrigir-parcelas-garantia-executada',
            'observacoes' => $observacoes,
        ]);
    }
}
