# Observabilidade - PagDesk

Guia completo de monitoramento e observabilidade da aplicação.

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Health Checks](#2-health-checks)
3. [Monitoramento do Scheduler](#3-monitoramento-do-scheduler)
4. [Monitoramento de Filas](#4-monitoramento-de-filas)
5. [Métricas de Infraestrutura](#5-métricas-de-infraestrutura)
6. [Alertas](#6-alertas)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Visão Geral

### Stack de Observabilidade

```
┌─────────────────────────────────────────────────────────────┐
│                        GRAFANA                               │
│                    (Dashboards/Alertas)                      │
│                     http://localhost:3000                    │
└─────────────────────────────┬───────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                       PROMETHEUS                             │
│                    (Coleta de Métricas)                      │
│                     http://localhost:9090                    │
└───────┬─────────────────────┬───────────────────────┬───────┘
        │                     │                       │
        ▼                     ▼                       ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────────┐
│ node-exporter │    │   cadvisor    │    │ Laravel /health   │
│ (Host)        │    │ (Containers)  │    │ (Aplicação)       │
└───────────────┘    └───────────────┘    └───────────────────┘
```

### Componentes

| Componente | Função | Porta |
|------------|--------|-------|
| Grafana | Dashboards e alertas | 3000 |
| Prometheus | Coleta e armazenamento de métricas | 9090 |
| Node Exporter | Métricas do host (CPU, RAM, disco) | 9100 |
| cAdvisor | Métricas dos containers Docker | 8080 |
| Laravel /health | Status da aplicação | 8080 |

---

## 2. Health Checks

### Endpoints Disponíveis

| Endpoint | Propósito | Quando usar |
|----------|-----------|-------------|
| `/health` | Status completo | Dashboards, monitoramento detalhado |
| `/health/live` | Aplicação está viva? | Verificação básica, restart automático |
| `/health/ready` | Pronta para tráfego? | Load balancers, validação pós-deploy |

### GET /health

Retorna status completo de todos os serviços.

**Exemplo de resposta:**
```json
{
  "status": "healthy",
  "app": "PagDesk",
  "version": "1.0.0",
  "environment": "production",
  "checks": {
    "database": {
      "status": "ok",
      "latency_ms": 5.02,
      "connection": "mysql"
    },
    "redis": {
      "status": "ok",
      "latency_ms": 0.19
    },
    "cache": {
      "status": "ok",
      "latency_ms": 1.17,
      "driver": "redis"
    },
    "queue": {
      "status": "ok",
      "driver": "redis",
      "size": 0,
      "failed": 0
    },
    "scheduler": {
      "status": "ok",
      "last_run": "2026-03-12 00:00:00",
      "last_task": "parcelas:marcar-atrasadas",
      "last_status": "success"
    }
  },
  "timestamp": "2026-03-12T00:32:50-03:00"
}
```

**Status possíveis:**
| Status | HTTP | Significado |
|--------|------|-------------|
| `healthy` | 200 | Tudo funcionando |
| `degraded` | 200 | Funcionando com warnings |
| `unhealthy` | 503 | Falha crítica |

### GET /health/live

Verifica apenas se o PHP está respondendo.

```json
{
  "status": "ok",
  "timestamp": "2026-03-12T00:33:06-03:00"
}
```

**Uso:** Verificação básica de disponibilidade. Se falhar, indica que o container deve ser reiniciado.

### GET /health/ready

Verifica serviços críticos (DB, Redis, Cache).

```json
{
  "status": "ready",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "cache": "ok"
  },
  "timestamp": "2026-03-12T00:33:20-03:00"
}
```

**Uso:** Load balancers e validação pós-deploy. Retorna 503 se não estiver pronto para receber tráfego.

### Arquitetura dos Health Checks

```
app/Services/Health/
├── HealthCheckInterface.php   # Interface que todos os checks implementam
├── HealthService.php          # Orquestra e combina resultados
├── DatabaseHealthCheck.php    # Testa conexão MySQL
├── RedisHealthCheck.php       # Testa conexão Redis
├── CacheHealthCheck.php       # Testa leitura/escrita de cache
├── QueueHealthCheck.php       # Conta jobs na fila e falhados
└── SchedulerHealthCheck.php   # Verifica última execução do scheduler
```

**Adicionando um novo health check:**

```php
// app/Services/Health/NovoHealthCheck.php
<?php

namespace App\Services\Health;

class NovoHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'novo_servico';
    }

    public function check(): array
    {
        try {
            // Sua lógica de verificação
            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    public function isCritical(): bool
    {
        return false; // true se deve afetar /health/ready
    }
}
```

Depois, adicione ao `HealthService.php` no construtor.

---

## 3. Monitoramento do Scheduler

### Como Funciona

O scheduler registra automaticamente cada execução de tarefa:

1. **Antes de executar:** `SchedulerLogger::start('tarefa')`
2. **Sucesso:** `SchedulerLogger::success('tarefa', 'mensagem')`
3. **Falha:** `SchedulerLogger::failed('tarefa', 'erro')`

### Tabela scheduled_task_runs

```sql
SELECT * FROM scheduled_task_runs ORDER BY started_at DESC LIMIT 10;
```

| Coluna | Descrição |
|--------|-----------|
| task_name | Nome da tarefa (ex: parcelas:marcar-atrasadas) |
| started_at | Início da execução |
| finished_at | Fim da execução |
| status | running, success, failed |
| message | Mensagem ou erro |

### Heartbeat

O scheduler escreve um arquivo de heartbeat a cada minuto:

```bash
# Verificar última atividade
cat storage/framework/scheduler-heartbeat
# Saída: 2026-03-12T00:35:00-03:00
```

### Verificar no /health

```bash
curl -s http://localhost:8080/health | jq '.checks.scheduler'
```

```json
{
  "status": "ok",
  "last_run": "2026-03-12 00:00:00",
  "last_task": "parcelas:marcar-atrasadas",
  "last_status": "success"
}
```

**Alertas:**
- `status: warning` se última execução > 5 minutos
- `status: error` se ocorreu erro ao verificar

---

## 4. Monitoramento de Filas

### Métricas Disponíveis

| Métrica | Descrição | Onde verificar |
|---------|-----------|----------------|
| queue.size | Jobs pendentes na fila | /health |
| queue.failed | Jobs que falharam | /health |
| queue.driver | Driver em uso (redis) | /health |

### Verificar via /health

```bash
curl -s http://localhost:8080/health | jq '.checks.queue'
```

```json
{
  "status": "ok",
  "driver": "redis",
  "size": 0,
  "failed": 0
}
```

### Verificar via CLI

```bash
# Ver tamanho da fila
docker exec pagdesk-app php artisan queue:work --once --dry-run

# Ver jobs falhados
docker exec pagdesk-app php artisan queue:failed

# Reprocessar falhados
docker exec pagdesk-app php artisan queue:retry all

# Limpar falhados
docker exec pagdesk-app php artisan queue:flush
```

### Via Redis

```bash
# Tamanho da fila
docker exec pagdesk-redis redis-cli LLEN queues:default

# Ver jobs na fila
docker exec pagdesk-redis redis-cli LRANGE queues:default 0 10
```

### Status da Queue

| Status | Significado | Ação |
|--------|-------------|------|
| `ok` | Tudo normal | - |
| `attention` | Tem jobs falhados | Verificar queue:failed |
| `warning` | Fila > 100 jobs | Verificar se worker está rodando |
| `error` | Erro ao verificar | Verificar conexão Redis |

---

## 5. Métricas de Infraestrutura

### Node Exporter (Host)

| Métrica | Descrição |
|---------|-----------|
| node_cpu_seconds_total | Uso de CPU |
| node_memory_MemAvailable_bytes | Memória disponível |
| node_filesystem_avail_bytes | Espaço em disco |
| node_network_receive_bytes_total | Tráfego de rede |

### cAdvisor (Containers)

| Métrica | Descrição |
|---------|-----------|
| container_cpu_usage_seconds_total | CPU por container |
| container_memory_usage_bytes | Memória por container |
| container_network_receive_bytes_total | Rede por container |

### Prometheus Queries Úteis

```promql
# CPU do container app
rate(container_cpu_usage_seconds_total{name="pagdesk-app"}[5m])

# Memória do container app
container_memory_usage_bytes{name="pagdesk-app"}

# Containers reiniciando
changes(container_start_time_seconds{name=~"pagdesk.*"}[1h])
```

---

## 6. Alertas

### Alertas Recomendados

| Alerta | Condição | Severidade |
|--------|----------|------------|
| App Down | /health retorna 503 por 2 min | Crítico |
| App Degraded | /health retorna degraded por 5 min | Warning |
| Queue Acumulando | queue.size > 100 por 5 min | Warning |
| Jobs Falhando | queue.failed > 0 | Warning |
| Scheduler Parado | scheduler.last_run > 5 min | Warning |
| Container Reiniciando | restart > 3 em 10 min | Warning |
| CPU Alta | > 80% por 5 min | Warning |
| Disco Cheio | > 90% | Crítico |

### Configurar Alertas no Grafana

1. Acessar Grafana: http://localhost:3000
2. Menu → Alerting → Alert rules
3. Create alert rule
4. Configurar query e condição

### Exemplo de Alerta (Prometheus)

```yaml
# prometheus/alerts/app.yml
groups:
  - name: pagdesk
    rules:
      - alert: PagDeskDown
        expr: probe_success{job="pagdesk-health"} == 0
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "PagDesk está indisponível"

      - alert: PagDeskQueueHigh
        expr: pagdesk_queue_size > 100
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Fila com {{ $value }} jobs pendentes"
```

---

## 7. Troubleshooting

### Aplicação não responde

```bash
# Verificar containers
docker ps | grep pagdesk

# Logs do app
docker logs -f pagdesk-app

# Testar health
curl -v http://localhost:8080/health
```

### Redis não conecta

```bash
# Verificar container
docker exec pagdesk-redis redis-cli ping

# Logs
docker logs pagdesk-redis
```

### MySQL lento

```bash
# Verificar conexão
docker exec pagdesk-app php artisan tinker --execute="DB::select('SELECT 1')"

# Verificar /health para latência
curl -s http://localhost:8080/health | jq '.checks.database.latency_ms'
```

### Scheduler parado

```bash
# Verificar container
docker logs pagdesk-scheduler

# Verificar heartbeat
docker exec pagdesk-app cat storage/framework/scheduler-heartbeat

# Executar manualmente
docker exec pagdesk-app php artisan schedule:run
```

### Queue não processa

```bash
# Verificar worker
docker logs pagdesk-queue

# Testar processamento
docker exec pagdesk-app php artisan queue:work --once

# Verificar Redis
docker exec pagdesk-redis redis-cli LLEN queues:default
```

---

## Comandos Úteis

```bash
# Status completo
curl -s http://localhost:8080/health | jq

# Apenas status
curl -s http://localhost:8080/health | jq '.status'

# Verificar se está pronto
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/health/ready

# Monitorar em loop
watch -n 5 'curl -s http://localhost:8080/health | jq ".checks | to_entries[] | {(.key): .value.status}"'
```

---

*Última atualização: Março 2026*
