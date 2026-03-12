# Filas e Laravel Horizon

Este documento explica como usar filas (queues) e Laravel Horizon no sistema para processar tarefas pesadas em background.

---

## 📦 Instalação

### 1. Instalar dependências

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

### 2. Publicar assets do Horizon

```bash
php artisan vendor:publish --tag=horizon-assets
```

### 3. Configurar .env

```env
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Nota:** Se não tiver Redis instalado, você pode usar `QUEUE_CONNECTION=database` temporariamente, mas Redis é recomendado para produção.

---

## 🚀 Como usar

### Jobs criados

#### 1. **GerarRelatorioJob** - Gerar relatórios pesados

```php
use App\Jobs\GerarRelatorioJob;

// Disparar geração de relatório em background
GerarRelatorioJob::dispatch(
    userId: auth()->id(),
    tipoRelatorio: 'parcelas_atrasadas',
    filtros: ['operacao_id' => 1, 'data_inicio' => '2024-01-01'],
    formato: 'excel' // ou 'pdf'
);

// O usuário receberá uma notificação quando o relatório estiver pronto
```

**Parâmetros:**
- `userId` (int): ID do usuário que solicitou o relatório
- `tipoRelatorio` (string): Tipo do relatório ('parcelas_atrasadas', 'pagamentos_mes', 'emprestimos_ativos', etc.)
- `filtros` (array): Filtros específicos (operacao_id, data_inicio, data_fim, etc.)
- `formato` (string): 'excel' ou 'pdf' (padrão: 'excel')

**Configuração:**
- Tenta até 3 vezes em caso de falha
- Timeout de 5 minutos
- Backoff: 1min e 2min entre tentativas

#### 2. **EnviarNotificacaoJob** - Enviar notificações em massa

```php
use App\Jobs\EnviarNotificacaoJob;

// Enviar notificação para múltiplos usuários
EnviarNotificacaoJob::dispatch(
    userIds: [1, 2, 3, 4, 5],
    tipo: 'emprestimo_aprovado',
    titulo: 'Empréstimo Aprovado',
    mensagem: 'Seu empréstimo foi aprovado com sucesso!',
    url: route('emprestimos.show', 123),
    dados: ['emprestimo_id' => 123]
);
```

**Parâmetros:**
- `userIds` (array): IDs dos usuários que receberão a notificação
- `tipo` (string): Tipo da notificação
- `titulo` (string): Título da notificação
- `mensagem` (string): Mensagem da notificação
- `url` (string|null): URL opcional para redirecionamento
- `dados` (array|null): Dados adicionais em JSON

---

## 🎯 Laravel Horizon

### Acessar o dashboard

Após instalar e iniciar o Horizon, acesse:

```
http://seu-dominio.com/horizon
```

**Acesso:** Apenas usuários com papel de **administrador** podem acessar.

### Iniciar o Horizon

```bash
# Desenvolvimento (modo watch)
php artisan horizon

# Produção (modo daemon)
php artisan horizon
```

### Parar o Horizon

```bash
php artisan horizon:terminate
```

### Configuração

O arquivo `config/horizon.php` já está configurado com:

- **Produção:** Auto-scaling com até 10 processos, balanceamento automático
- **Local:** 3 processos fixos, balanceamento simples
- **Retenção:** Jobs recentes por 60min, falhas por 7 dias
- **Timeout:** 300 segundos (5 minutos) por job

---

## 📝 Exemplos de uso

### Exemplo 1: Gerar relatório de parcelas atrasadas

```php
// No controller
public function gerarRelatorioParcelasAtrasadas(Request $request)
{
    GerarRelatorioJob::dispatch(
        userId: auth()->id(),
        tipoRelatorio: 'parcelas_atrasadas',
        filtros: [
            'operacao_id' => $request->operacao_id,
            'dias_atraso_min' => $request->dias_atraso_min,
        ],
        formato: $request->formato ?? 'excel'
    );
    
    return redirect()->back()->with('success', 'Relatório sendo gerado. Você receberá uma notificação quando estiver pronto.');
}
```

### Exemplo 2: Notificar todos os gestores sobre nova liberação

```php
use App\Models\User;

$gestoresIds = User::whereHas('roles', function ($q) {
    $q->where('name', 'gestor');
})->pluck('id')->toArray();

EnviarNotificacaoJob::dispatch(
    userIds: $gestoresIds,
    tipo: 'nova_liberacao',
    titulo: 'Nova Liberação Pendente',
    mensagem: 'Há uma nova liberação aguardando aprovação.',
    url: route('liberacoes.index')
);
```

### Exemplo 3: Criar seu próprio Job

```php
php artisan make:job ProcessarConciliacaoJob
```

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessarConciliacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600; // 10 minutos

    public function __construct(
        public int $operacaoId,
        public string $dataInicio,
        public string $dataFim
    ) {}

    public function handle(): void
    {
        // Sua lógica aqui
        // Exemplo: conciliar pagamentos com movimentações de caixa
    }

    public function failed(\Throwable $exception): void
    {
        // Tratar falha permanente
        \Log::error('Falha ao processar conciliação', [
            'operacao_id' => $this->operacaoId,
            'erro' => $exception->getMessage(),
        ]);
    }
}
```

---

## 🔧 Configuração de Produção

### 1. Supervisor (recomendado)

Crie `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /caminho/do/projeto/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/caminho/do/projeto/storage/logs/horizon.log
stopwaitsecs=3600
```

Depois:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

### 2. Monitoramento

O Horizon já registra métricas automaticamente. Você pode ver:
- Jobs processados por segundo
- Tempo médio de processamento
- Taxa de falhas
- Uso de memória

### 3. Dead Letter Queue

Jobs que falharem após todas as tentativas serão registrados em `failed_jobs`. Você pode:

```bash
# Ver jobs falhados
php artisan queue:failed

# Reexecutar um job falhado
php artisan queue:retry {id}

# Limpar jobs falhados antigos
php artisan queue:flush
```

---

## ⚠️ Boas Práticas

1. **Idempotência:** Garanta que seu job pode ser executado múltiplas vezes sem causar problemas (ex: verificar se já processou antes de processar).

2. **Timeout:** Configure timeout adequado para cada job. Jobs muito longos podem travar workers.

3. **Retries:** Use `$tries` e `$backoff` para tentativas automáticas em caso de falha temporária.

4. **Falhas:** Sempre implemente `failed()` para notificar usuários ou registrar erros críticos.

5. **Monitoramento:** Use o dashboard do Horizon para monitorar saúde da fila.

---

## 📊 Monitoramento

### Métricas importantes no Horizon:

- **Throughput:** Jobs processados por segundo
- **Wait Time:** Tempo de espera na fila
- **Process Time:** Tempo de processamento
- **Failed Jobs:** Quantidade de falhas

### Alertas recomendados:

- Se wait time > 60s: fila está sobrecarregada
- Se failed jobs > 5%: investigar erros
- Se memory usage > 80%: considerar aumentar workers

---

## 🔐 Segurança

- O Horizon está protegido por um Gate (`viewHorizon`) no `HorizonServiceProvider` que verifica se o usuário tem o papel de administrador.
- Em produção, considere proteger também por IP ou VPN.
- Não exponha o Horizon publicamente sem autenticação adequada.

---

## 📚 Referências

- [Laravel Queues](https://laravel.com/docs/queues)
- [Laravel Horizon](https://laravel.com/docs/horizon)
- [Redis](https://redis.io/docs/)
