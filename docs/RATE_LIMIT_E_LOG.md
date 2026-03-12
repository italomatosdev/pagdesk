# Rate Limit e Log Estruturado

## Rate Limit

### Login (anti brute-force)

- **5 tentativas** de login por **2 minutos** (por email + IP).
- Configurado em `LoginController` (`$maxAttempts`, `$decayMinutes`).
- Após exceder, o usuário vê a mensagem padrão do Laravel (throttle) e precisa aguardar.

### Ações sensíveis (POST/PUT/PATCH/DELETE)

- **40 requisições por minuto** por usuário (ou por IP se não autenticado).
- Aplica apenas a métodos que alteram dados; GET não conta.
- Middleware: `throttle.sensitive` (aplicado em todas as rotas autenticadas em `routes/web.php`).
- Em caso de excesso: resposta HTTP 429 (Too Many Requests).

### Ajustar limites

- **Login:** edite `$maxAttempts` e `$decayMinutes` em `app/Http/Controllers/Auth/LoginController.php`.
- **Sensível:** edite o limiter `sensitive` em `app/Providers/RouteServiceProvider.php` (ex.: `Limit::perMinute(60)`).

---

## Log Estruturado

### Canal `structured`

- Arquivo: `storage/logs/structured.log`.
- Formato: **uma linha JSON por evento** (fácil de ingerir em ferramentas como Kibana, Datadog, CloudWatch).
- Cada registro inclui automaticamente:
  - `user_id` (quando autenticado)
  - `empresa_id` (quando o usuário tem empresa)
  - `request_id` (quando o cliente envia `X-Request-ID` ou `X-Correlation-ID`)

### Uso no código

```php
use Illuminate\Support\Facades\Log;

// Erro com contexto (exceções já são enviadas ao canal structured pelo Handler)
Log::channel('structured')->error('Falha ao processar pagamento', [
    'emprestimo_id' => $emprestimoId,
    'parcela_id' => $parcelaId,
]);

// Info para auditoria / análise
Log::channel('structured')->info('Liberação aprovada', [
    'liberacao_id' => $liberacao->id,
    'valor' => $liberacao->valor,
]);
```

### Exemplo de linha no `structured.log`

```json
{"message":"Falha ao processar pagamento","context":{"emprestimo_id":123,"parcela_id":456},"level":400,"level_name":"ERROR","channel":"local","datetime":"2026-01-28T20:00:00.000000+00:00","extra":{"user_id":1,"empresa_id":1,"request_id":null}}
```

### Configuração

- Canal definido em `config/logging.php` (chave `structured`).
- Formato e contexto: `App\Logging\StructuredLogTap` e `App\Logging\StructuredLogProcessor`.

Para incluir o canal no stack padrão (além de `single`), adicione `'structured'` ao array `channels` do canal `stack` em `config/logging.php`.
