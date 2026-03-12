# Backup e Saúde da Aplicação

**Melhores práticas (tecnologia e segurança):** veja **`docs/MELHORES_PRATICAS_BANCO_DADOS.md`** para recomendações atuais sobre backup, off-site, criptografia, teste de restore e banco separado para testes.

---

## Health check

A aplicação expõe um endpoint de saúde para load balancers e ferramentas de monitoramento.

### Endpoint

```
GET /api/health
```

- **Sem autenticação** – pode ser chamado por sistemas externos.
- **Resposta 200:** todos os recursos verificados estão ok.
- **Resposta 503:** algum recurso crítico falhou (ex.: banco ou cache indisponível).

### Exemplo de resposta (200)

```json
{
  "status": "ok",
  "app": "pagdesk",
  "environment": "production",
  "checks": {
    "database": true,
    "cache": true,
    "queue": true
  },
  "timestamp": "2026-01-28T21:00:00.000000Z"
}
```

### O que é verificado

| Recurso   | Descrição                                      |
|----------|--------------------------------------------------|
| database | Conexão com o banco de dados (MySQL)            |
| cache    | Leitura/gravação no driver configurado (file/redis) |
| queue    | Apenas quando `QUEUE_CONNECTION=redis` (ping no Redis) |

Quando a fila usa `sync` ou `database`, o campo `queue` pode não aparecer na resposta.

### Uso em produção

- **Load balancer:** usar `/health` como health check; remover do pool se retornar 503.
- **Monitoramento (Uptime Robot, Pingdom, etc.):** verificar se retorna 200 em intervalo definido.
- **Docker healthcheck:** usar `/health/ready` para verificar se a aplicação está pronta.

---

## Estratégia de backup

### Banco de dados (MySQL)

- **Frequência:** diário no mínimo; em produção, considerar incremental ou várias vezes ao dia.
- **Retenção:** manter pelo menos 7 dias; 30 dias é recomendado para auditoria.
- **Teste de restore:** executar restore em ambiente de teste pelo menos uma vez por mês.

#### Exemplo com mysqldump (cron)

```bash
# Backup diário às 2h (credenciais em ~/.my.cnf)
0 2 * * * mysqldump --defaults-file=/root/.my.cnf cred | gzip > /backups/cred_$(date +\%Y\%m\%d).sql.gz
```

> **Configuração segura:** Crie `/root/.my.cnf` com permissão 600 contendo as credenciais.

#### Exemplo com Laravel (scheduler + comando)

Você pode criar um comando Artisan que chama `mysqldump` ou usa um pacote (ex.: `spatie/laravel-backup`) e agendar no `app/Console/Kernel.php`:

```php
$schedule->command('backup:run')->daily()->at('02:00');
```

### Arquivos da aplicação

- **O que fazer backup:** `storage/app` (uploads, comprovantes, anexos). Código pode ser recuperado do repositório.
- **Frequência:** alinhar com o backup do banco (ex.: diário).
- **Retenção:** pelo menos 7 dias.

### Redis (quando usado)

- Se usar Redis para cache/filas, habilitar **RDB ou AOF** no `redis.conf` conforme documentação do Redis.
- Backup do Redis não substitui o do MySQL; o crítico para negócio é o banco de dados.

### Checklist

- [ ] Backup automático do MySQL (diário ou mais frequente)
- [ ] Backup de `storage/app` (uploads)
- [ ] Backups armazenados fora do servidor (outro disco, S3, etc.)
- [ ] Teste de restore realizado periodicamente
- [ ] Documentar onde estão os backups e quem tem acesso
