<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Job para gerar relatórios pesados em background (Excel/PDF).
 * 
 * Exemplo de uso:
 * GerarRelatorioJob::dispatch($userId, 'parcelas_atrasadas', ['operacao_id' => 1]);
 */
class GerarRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Tentar até 3 vezes
    public $timeout = 300; // 5 minutos máximo
    public $backoff = [60, 120]; // Esperar 1min e 2min entre tentativas

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $tipoRelatorio, // 'parcelas_atrasadas', 'pagamentos_mes', 'emprestimos_ativos', etc.
        public array $filtros = [], // Filtros específicos (operacao_id, data_inicio, etc.)
        public ?string $formato = 'excel' // 'excel' ou 'pdf'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        
        try {
            $arquivo = $this->gerarRelatorio();
            
            // Criar notificação para o usuário informando que o relatório está pronto
            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            $notificacaoService->criar([
                'user_id' => $this->userId,
                'tipo' => 'relatorio_gerado',
                'titulo' => 'Relatório gerado com sucesso',
                'mensagem' => "Seu relatório de {$this->tipoRelatorio} está pronto para download.",
                'url' => null, // TODO: Criar rota para download de relatórios
                'dados' => [
                    'tipo' => $this->tipoRelatorio,
                    'arquivo' => $arquivo,
                    'formato' => $this->formato,
                ],
            ]);
        } catch (\Exception $e) {
            // Notificar usuário sobre falha
            $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
            $notificacaoService->criar([
                'user_id' => $this->userId,
                'tipo' => 'erro',
                'titulo' => 'Erro ao gerar relatório',
                'mensagem' => "Não foi possível gerar o relatório: {$e->getMessage()}",
            ]);
            
            // Re-throw para que o job seja marcado como falho
            throw $e;
        }
    }

    /**
     * Gerar o relatório baseado no tipo
     */
    protected function gerarRelatorio(): string
    {
        // Por enquanto, apenas simula a geração (você pode integrar com bibliotecas como PhpSpreadsheet ou DomPDF)
        $nomeArquivo = "relatorio_{$this->tipoRelatorio}_" . Carbon::now()->format('Ymd_His') . '.' . ($this->formato === 'pdf' ? 'pdf' : 'xlsx');
        $caminho = "relatorios/{$this->userId}/{$nomeArquivo}";
        
        // Criar diretório se não existir
        Storage::disk('public')->makeDirectory("relatorios/{$this->userId}");
        
        // TODO: Implementar geração real do relatório usando PhpSpreadsheet ou DomPDF
        // Por enquanto, cria um arquivo placeholder
        Storage::disk('public')->put($caminho, "Relatório: {$this->tipoRelatorio}\nFiltros: " . json_encode($this->filtros));
        
        return $caminho;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Notificar usuário sobre falha permanente
        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        $notificacaoService->criar([
            'user_id' => $this->userId,
            'tipo' => 'erro',
            'titulo' => 'Falha ao gerar relatório',
            'mensagem' => "O relatório não pôde ser gerado após várias tentativas. Entre em contato com o suporte.",
        ]);
    }
}
