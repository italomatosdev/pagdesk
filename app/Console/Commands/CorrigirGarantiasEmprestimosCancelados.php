<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Auditoria;
use App\Modules\Loans\Models\Emprestimo;
use App\Modules\Loans\Models\EmprestimoGarantia;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorrigirGarantiasEmprestimosCancelados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emprestimos:corrigir-garantias-cancelados 
                            {--dry-run : Apenas simula, não faz alterações}
                            {--force : Executa as alterações (sem dry-run)}
                            {--empresa-id= : Filtrar por empresa específica}
                            {--emprestimo-id= : Corrigir apenas um empréstimo específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige registros retroativos: marca garantias como canceladas em empréstimos tipo empenho que foram cancelados';

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
        $this->info('  CORRIGIR GARANTIAS - EMPRÉSTIMOS CANCELADOS');
        $this->info('===========================================');
        $this->info('');

        if ($isDryRun) {
            $this->warn('🔍 MODO SIMULAÇÃO - Nenhuma alteração será feita');
            $this->info('');
        } else {
            $this->warn('⚠️  MODO EXECUÇÃO - Alterações serão aplicadas!');
            $this->info('');
        }

        // Buscar empréstimos cancelados tipo empenho com garantias ativas
        $query = Emprestimo::withoutGlobalScopes()
            ->where('tipo', 'empenho')
            ->where('status', 'cancelado')
            ->whereHas('garantias', function ($q) {
                // Garantias que ainda estão ativas (não foram canceladas)
                $q->where('status', 'ativa');
            })
            ->with([
                'garantias' => function ($q) {
                    $q->where('status', 'ativa');
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
        $totalGarantiasCorrigidas = 0;
        $totalGarantiasPreservadas = 0;
        $erros = [];

        foreach ($emprestimos as $emprestimo) {
            try {
                $resultado = $this->processarEmprestimo($emprestimo, $isDryRun);
                
                if ($resultado['corrigido']) {
                    $totalCorrigidos++;
                    $totalGarantiasCorrigidas += $resultado['garantias_corrigidas'];
                    $totalGarantiasPreservadas += $resultado['garantias_preservadas'];
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
        $this->info("Garantias corrigidas: {$totalGarantiasCorrigidas}");
        $this->info("Garantias preservadas (já canceladas/liberadas/executadas): {$totalGarantiasPreservadas}");
        
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
        // Recarregar todas as garantias
        $emprestimo->load('garantias');
        
        // Separar garantias: ativas vs outras
        $garantiasAtivas = $emprestimo->garantias->where('status', 'ativa');
        $garantiasOutras = $emprestimo->garantias->where('status', '!=', 'ativa');

        // Verificar se há garantias para corrigir
        if ($garantiasAtivas->isEmpty()) {
            return [
                'corrigido' => false,
                'garantias_corrigidas' => 0,
                'garantias_preservadas' => $garantiasOutras->count(),
                'motivo' => 'Todas as garantias já estão canceladas, liberadas ou executadas'
            ];
        }

        // Exibir informações
        $clienteNome = $emprestimo->cliente->nome ?? 'N/A';
        $operacaoNome = $emprestimo->operacao->nome ?? 'N/A';
        $dataCancelamento = $emprestimo->aprovado_em 
            ? Carbon::parse($emprestimo->aprovado_em)->format('d/m/Y H:i')
            : 'N/A';
        $motivoCancelamento = $emprestimo->motivo_rejeicao ?? 'Não informado';

        $this->info("📋 Empréstimo #{$emprestimo->id}");
        $this->line("   Cliente: {$clienteNome}");
        $this->line("   Operação: {$operacaoNome}");
        $this->line("   Cancelado em: {$dataCancelamento}");
        $this->line("   Motivo: {$motivoCancelamento}");
        $this->line("   Total de garantias: {$emprestimo->garantias->count()}");
        $this->line("   Garantias já processadas: {$garantiasOutras->count()} (serão preservadas)");
        $this->line("   Garantias a corrigir: {$garantiasAtivas->count()}");

        // Listar garantias a corrigir
        foreach ($garantiasAtivas as $garantia) {
            $categoria = $garantia->categoria_nome;
            $descricao = $garantia->descricao;
            $valor = $garantia->valor_avaliado 
                ? 'R$ ' . number_format($garantia->valor_avaliado, 2, ',', '.')
                : 'Não informado';
            
            $this->line("     → Garantia #{$garantia->id}:");
            $this->line("        Categoria: {$categoria}");
            $this->line("        Descrição: {$descricao}");
            $this->line("        Valor: {$valor}");
            $this->line("        Status atual: ativa");
            $this->line("        Será marcada como: cancelada");
        }

        if ($isDryRun) {
            $this->info("   ✅ [SIMULAÇÃO] Seria corrigido");
            $this->info('');
            
            return [
                'corrigido' => true,
                'garantias_corrigidas' => $garantiasAtivas->count(),
                'garantias_preservadas' => $garantiasOutras->count()
            ];
        }

        // Executar correção
        try {
            DB::transaction(function () use ($emprestimo, $garantiasAtivas, $motivoCancelamento) {
                $dataCancelamento = $emprestimo->aprovado_em 
                    ? Carbon::parse($emprestimo->aprovado_em)
                    : Carbon::now();

                foreach ($garantiasAtivas as $garantia) {
                    $oldStatus = $garantia->status;
                    $observacoesAnteriores = $garantia->observacoes ?? '';
                    $observacaoCancelamento = "\n\n[CANCELADA EM " . Carbon::now()->format('d/m/Y H:i') . " - CORREÇÃO RETROATIVA]\n" .
                                               "Empréstimo #{$emprestimo->id} foi cancelado em {$dataCancelamento->format('d/m/Y H:i')}.\n" .
                                               "Motivo do cancelamento: {$motivoCancelamento}";

                    $garantia->update([
                        'status' => 'cancelada',
                        'observacoes' => $observacoesAnteriores . $observacaoCancelamento,
                    ]);

                    // Registrar auditoria
                    $this->auditar(
                        'corrigir_garantia_retroativa_cancelamento',
                        $garantia,
                        [
                            'status' => $oldStatus,
                        ],
                        [
                            'status' => 'cancelada',
                            'observacoes' => $garantia->observacoes,
                        ],
                        "Garantia corrigida retroativamente - Empréstimo #{$emprestimo->id} foi cancelado (comando artisan)"
                    );
                }
            });

            $this->info("   ✅ Corrigido com sucesso!");
            $this->info('');

            return [
                'corrigido' => true,
                'garantias_corrigidas' => $garantiasAtivas->count(),
                'garantias_preservadas' => $garantiasOutras->count()
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
            'user_agent' => 'Artisan Command: emprestimos:corrigir-garantias-cancelados',
            'observacoes' => $observacoes,
        ]);
    }
}
