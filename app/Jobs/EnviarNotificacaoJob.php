<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job para enviar notificações em massa ou notificações pesadas em background.
 * 
 * Exemplo de uso:
 * EnviarNotificacaoJob::dispatch($userIds, 'emprestimo_aprovado', ['emprestimo_id' => 123]);
 */
class EnviarNotificacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120; // 2 minutos máximo
    public $backoff = [30, 60]; // Esperar 30s e 1min entre tentativas

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $userIds, // IDs dos usuários que receberão a notificação
        public string $tipo,
        public string $titulo,
        public string $mensagem,
        public ?string $url = null,
        public ?array $dados = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notificacaoService = app(\App\Modules\Core\Services\NotificacaoService::class);
        
        $notificacaoService->criarParaMultiplos($this->userIds, [
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'url' => $this->url,
            'dados' => $this->dados,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log do erro (o Laravel já registra em failed_jobs)
        \Log::error('Falha ao enviar notificação em massa', [
            'user_ids' => $this->userIds,
            'tipo' => $this->tipo,
            'erro' => $exception->getMessage(),
        ]);
    }
}
