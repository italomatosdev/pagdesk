# Infraestrutura PagDesk

Documentação completa da infraestrutura de produção do PagDesk.

## Índice

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Ambientes](#2-ambientes)
3. [Serviços Docker](#3-serviços-docker)
4. [Banco de Dados](#4-banco-de-dados)
5. [Redis e Filas](#5-redis-e-filas)
6. [Scheduler](#6-scheduler)
7. [Monitoramento](#7-monitoramento)
8. [Backup e Recuperação](#8-backup-e-recuperação)
9. [Segurança](#9-segurança)
10. [Deploy](#10-deploy)
11. [Troubleshooting](#11-troubleshooting)
12. [Checklist de Produção](#12-checklist-de-produção)

---

## 1. Visão Geral da Arquitetura

### Arquitetura de Produção

```
                                    INTERNET
                                        │
                                        ▼
                              ┌─────────────────┐
                              │   Cloudflare    │
                              │   (DNS/CDN)     │
                              └────────┬────────┘
                                       │
                                       ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                           SERVIDOR APP (VPS 1)                                │
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │                         DOCKER COMPOSE                                   │ │
│  │                                                                          │ │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐       │ │
│  │  │  nginx  │  │   app   │  │  queue  │  │scheduler│  │  redis  │       │ │
│  │  │  :80    │→ │ php-fpm │  │ worker  │  │  cron   │  │  :6379  │       │ │
│  │  │  :443   │  │  :9000  │  │         │  │         │  │         │       │ │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘       │ │
│  │                                                                          │ │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐                     │ │
│  │  │grafana  │  │promethe.│  │  node   │  │cadvisor │                     │ │
│  │  │  :3000  │  │  :9090  │  │exporter │  │         │                     │ │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘                     │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                               │
│  IP Público: 203.0.113.10                                                     │
│  IP Privado: 10.0.0.10                                                        │
└──────────────────────────────────────────────────────────────────────────────┘
                                       │
                          Rede Privada │ (10.0.0.0/24)
                                       │
              ┌────────────────────────┴────────────────────────┐
              │                                                  │
              ▼                                                  ▼
┌─────────────────────────────┐              ┌─────────────────────────────┐
│  SERVIDOR MySQL PRIMÁRIO    │              │  SERVIDOR MySQL RÉPLICA     │
│  (VPS 2)                    │              │  (VPS 3)                    │
│                             │              │                             │
│  IP Privado: 10.0.0.20      │   Replica    │  IP Privado: 10.0.0.21      │
│  Porta: 3306                │ ──────────▶  │  Porta: 3306                │
│                             │   (binlog)   │                             │
│  Função: Leitura/Escrita    │              │  Função: Standby/Leitura    │
│  (PRIMARY)                  │              │  (REPLICA)                  │
└─────────────────────────────┘              └─────────────────────────────┘
```

### Componentes Principais

| Componente | Função | Localização |
|------------|--------|-------------|
| **Nginx** | Servidor web, SSL termination | Docker (VPS 1) |
| **App (PHP-FPM)** | Aplicação Laravel | Docker (VPS 1) |
| **Queue Worker** | Processamento de filas | Docker (VPS 1) |
| **Scheduler** | Tarefas agendadas (cron) | Docker (VPS 1) |
| **Redis** | Cache, sessões, filas | Docker (VPS 1) |
| **MySQL Primário** | Banco principal (escrita) | VPS 2 (instalação nativa) |
| **MySQL Réplica** | Banco secundário (leitura/standby) | VPS 3 (instalação nativa) |
| **Prometheus** | Coleta de métricas | Docker (VPS 1) |
| **Grafana** | Dashboards e alertas | Docker (VPS 1) |

---

## 2. Ambientes

### 2.1 Local (Desenvolvimento)

**Propósito:** Desenvolvimento no computador do desenvolvedor.

**Características:**
- MySQL rodando localmente (fora do Docker)
- Código montado via volume (hot reload)
- Debug habilitado
- Mailpit para captura de e-mails
- Sem HTTPS

**Arquivos utilizados:**
```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

**Variáveis de ambiente:**
```env
APP_ENV=local
APP_DEBUG=true
DB_HOST=host.docker.internal  # Acessa MySQL local
REDIS_HOST=redis
```

**Acessos:**
| Serviço | URL |
|---------|-----|
| App | http://localhost:8080 |
| Grafana | http://localhost:3000 |
| Mailpit | http://localhost:8025 |

---

### 2.2 Homologação (Staging)

**Propósito:** Validação antes de ir para produção. Ambiente similar à produção.

**Características:**
- Banco de dados separado (staging)
- Deploy automático via branch `dev` ou `staging`
- Debug habilitado (opcional)
- HTTPS recomendado
- Sem Mailpit (usa serviço real ou desabilitado)

**Arquivos utilizados:**
```bash
docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d
```

**Variáveis de ambiente:**
```env
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://staging.pagdesk.com.br
DB_HOST=10.0.0.30              # IP do MySQL de staging
DB_DATABASE=pagdesk_staging
REDIS_HOST=redis
```

**Deploy:**
- Push para branch `dev` → GitHub Actions → Deploy automático em staging

---

### 2.3 Produção

**Propósito:** Ambiente de produção real com usuários finais.

**Características:**
- Banco de dados de produção (com réplica)
- Deploy automático via branch `main`
- Debug **desabilitado**
- HTTPS **obrigatório**
- Cache otimizado
- Monitoramento ativo
- Backups diários

**Arquivos utilizados:**
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

**Variáveis de ambiente:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pagdesk.com.br
DB_HOST=10.0.0.20              # IP do MySQL primário (rede privada)
DB_DATABASE=pagdesk_production
REDIS_HOST=redis
```

**Deploy:**
- Push para branch `main` → GitHub Actions → Deploy automático em produção

---

### Comparativo de Ambientes

| Aspecto | Local | Staging | Produção |
|---------|-------|---------|----------|
| **APP_ENV** | local | staging | production |
| **APP_DEBUG** | true | true/false | **false** |
| **DB_HOST** | host.docker.internal | IP staging | IP produção |
| **HTTPS** | Não | Recomendado | **Obrigatório** |
| **Mailpit** | Sim | Não | Não |
| **Cache Laravel** | Não | Opcional | **Sim** |
| **Réplica MySQL** | Não | Não | **Sim** |
| **Backups** | Não | Opcional | **Sim** |
| **Alertas** | Não | Opcional | **Sim** |
| **Deploy** | Manual | CI/CD (dev) | CI/CD (main) |

---

## 3. Serviços Docker

### Lista de Serviços

| Serviço | Imagem | Função | Porta Externa |
|---------|--------|--------|---------------|
| **app** | pagdesk-app (build) | PHP-FPM Laravel | - |
| **nginx** | nginx:1.25-alpine | Servidor web | 80, 443 |
| **queue** | pagdesk-queue (build) | Worker de filas | - |
| **scheduler** | pagdesk-scheduler (build) | Cron Laravel | - |
| **redis** | redis:7-alpine | Cache/Sessões/Filas | - |
| **prometheus** | prom/prometheus | Métricas | 9090 (restrito) |
| **grafana** | grafana/grafana | Dashboards | 3000 (restrito) |
| **node-exporter** | prom/node-exporter | Métricas do host | - |
| **cadvisor** | gcr.io/cadvisor | Métricas containers | - |

### Dependências entre Serviços

```
redis ─────────────┐
                   ├──▶ app ──▶ nginx
MySQL (externo) ───┘      │
                          ├──▶ queue
                          └──▶ scheduler

prometheus ──▶ grafana
     │
     ├──▶ node-exporter
     └──▶ cadvisor
```

### Healthchecks

Todos os serviços possuem healthcheck configurado:

| Serviço | Healthcheck |
|---------|-------------|
| app | `pgrep php-fpm` |
| nginx | `curl -f http://localhost/health` |
| queue | `pgrep php` |
| scheduler | `pgrep -f scheduler.sh` |
| redis | `redis-cli ping` |

---

## 4. Banco de Dados

### 4.1 MySQL Primário

**Função:** Banco principal que recebe todas as escritas e leituras da aplicação.

**Configuração:**
```
Servidor: VPS dedicada
IP Privado: 10.0.0.20
Porta: 3306
Versão: MySQL 8.0
```

**Acesso:**
- Apenas IPs da rede privada (10.0.0.0/24)
- Firewall bloqueando acesso externo
- Usuário específico para a aplicação

**Variáveis no .env de produção:**
```env
DB_CONNECTION=mysql
DB_HOST=10.0.0.20
DB_PORT=3306
DB_DATABASE=pagdesk_production
DB_USERNAME=pagdesk_app
DB_PASSWORD=<senha-forte-gerada>
```

---

### 4.2 MySQL Réplica

**Função:** Cópia em tempo real do banco primário para:
- Standby em caso de falha do primário
- Leituras pesadas (relatórios, se configurado)
- Backup sem impactar produção

**Configuração:**
```
Servidor: VPS dedicada
IP Privado: 10.0.0.21
Porta: 3306
Versão: MySQL 8.0
Tipo: Replica assíncrona
```

**O que é replicado:**
- Todos os bancos de dados
- Todas as tabelas
- Todos os dados
- Em tempo quase real (segundos de atraso)

**Monitoramento da replicação:**
```sql
-- Executar na réplica
SHOW REPLICA STATUS\G

-- Verificar:
-- Replica_IO_Running: Yes
-- Replica_SQL_Running: Yes
-- Seconds_Behind_Source: 0 (ou próximo)
```

---

### 4.3 Failover Manual

**Quando usar:** Quando o MySQL primário ficar indisponível.

**Procedimento de contingência:**

1. **Verificar se o primário realmente está fora:**
   ```bash
   mysql -h 10.0.0.20 -u pagdesk_app -p -e "SELECT 1"
   ```

2. **Verificar estado da réplica:**
   ```bash
   mysql -h 10.0.0.21 -u root -p -e "SHOW REPLICA STATUS\G"
   ```

3. **Promover réplica a primário:**
   ```sql
   -- Na réplica (10.0.0.21)
   STOP REPLICA;
   RESET REPLICA ALL;
   ```

4. **Atualizar aplicação:**
   ```bash
   # No servidor de app, editar .env
   DB_HOST=10.0.0.21
   
   # Reiniciar containers
   docker compose restart app queue scheduler
   ```

5. **Validar funcionamento:**
   - Acessar aplicação
   - Verificar logs
   - Testar operações de escrita

6. **Após recuperação do primário original:**
   - Reconstruir réplica a partir do novo primário
   - Ou inverter os papéis

**Tempo estimado de failover:** 5-15 minutos (manual)

---

### 4.4 Configuração de Replicação

**No servidor primário (10.0.0.20):**

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_do_db = pagdesk_production
bind-address = 10.0.0.20
```

```sql
-- Criar usuário de replicação
CREATE USER 'replicator'@'10.0.0.21' IDENTIFIED BY '<senha-forte>';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'10.0.0.21';
FLUSH PRIVILEGES;

-- Obter posição do binlog
SHOW MASTER STATUS;
```

**No servidor réplica (10.0.0.21):**

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
server-id = 2
relay-log = /var/log/mysql/mysql-relay-bin.log
read_only = 1
bind-address = 10.0.0.21
```

```sql
-- Configurar replicação
CHANGE REPLICATION SOURCE TO
  SOURCE_HOST='10.0.0.20',
  SOURCE_USER='replicator',
  SOURCE_PASSWORD='<senha-forte>',
  SOURCE_LOG_FILE='mysql-bin.000001',
  SOURCE_LOG_POS=<posição>;

START REPLICA;
```

---

## 5. Redis e Filas

### Configuração

O Redis roda como container Docker e é usado para:

| Função | Configuração |
|--------|--------------|
| **Cache** | CACHE_DRIVER=redis |
| **Sessões** | SESSION_DRIVER=redis |
| **Filas** | QUEUE_CONNECTION=redis |

### Persistência

Redis configurado com `appendonly yes` para persistência em disco.

### Monitoramento

```bash
# Ver filas pendentes
docker exec pagdesk-redis redis-cli LLEN queues:default

# Ver jobs falhados
docker exec pagdesk-app php artisan queue:failed

# Reprocessar jobs falhados
docker exec pagdesk-app php artisan queue:retry all
```

### Queue Worker

O container `pagdesk-queue` processa as filas automaticamente:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

**Configurações:**
- `--sleep=3`: Aguarda 3s se não houver jobs
- `--tries=3`: Tenta 3x antes de marcar como falho
- `--max-time=3600`: Reinicia worker a cada 1h

---

## 6. Scheduler

### Função

Executa tarefas agendadas do Laravel automaticamente:
- Marcar parcelas como vencidas (diário às 00:00)
- Heartbeat do scheduler (a cada minuto)
- Limpeza de registros antigos (semanalmente)
- Outras tarefas agendadas em `app/Console/Kernel.php`

### Como Funciona

O container `pagdesk-scheduler` executa um loop infinito:

```bash
while true; do
    php artisan schedule:run --verbose --no-interaction
    sleep 60
done
```

### Registro de Execuções

Todas as tarefas são registradas automaticamente usando o `SchedulerLogger`:

```php
// app/Console/Kernel.php
$schedule->command('parcelas:marcar-atrasadas')
    ->daily()
    ->at('00:00')
    ->before(function () {
        SchedulerLogger::start('parcelas:marcar-atrasadas');
    })
    ->onSuccess(function () {
        SchedulerLogger::success('parcelas:marcar-atrasadas', 'Executed successfully');
    })
    ->onFailure(function () {
        SchedulerLogger::failed('parcelas:marcar-atrasadas', 'Task failed');
    });
```

### Tabela scheduled_task_runs

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | bigint | ID único |
| task_name | varchar(100) | Nome da tarefa |
| started_at | datetime | Início da execução |
| finished_at | datetime | Fim da execução |
| status | varchar(20) | running, success, failed |
| message | text | Mensagem de sucesso ou erro |

### Monitoramento

**Via endpoint /health:**
```json
"scheduler": {
  "status": "ok",
  "last_run": "2026-03-12 00:00:00",
  "last_task": "parcelas:marcar-atrasadas",
  "last_status": "success"
}
```

**Via banco de dados:**
```bash
docker exec pagdesk-app php artisan tinker
>>> DB::table('scheduled_task_runs')->orderBy('started_at', 'desc')->first()
```

**Via arquivo de heartbeat:**
```bash
docker exec pagdesk-app cat /var/www/html/storage/framework/scheduler-heartbeat
```

### Alertas do Scheduler

O endpoint `/health` retorna `status: warning` se:
- Última execução foi há mais de 5 minutos
- Última execução falhou

Isso permite configurar alertas no Grafana/Prometheus baseados no endpoint.

---

## 7. Monitoramento

### 7.1 Stack de Monitoramento

```
┌─────────────────────────────────────────────────────────────┐
│                        GRAFANA                               │
│                    (Dashboards/Alertas)                      │
└─────────────────────────────┬───────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                       PROMETHEUS                             │
│                    (Coleta de Métricas)                      │
└───────┬─────────────────────┬───────────────────────┬───────┘
        │                     │                       │
        ▼                     ▼                       ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────────┐
│ node-exporter │    │   cadvisor    │    │ App /health       │
│ (Host)        │    │ (Containers)  │    │ (Laravel)         │
└───────────────┘    └───────────────┘    └───────────────────┘
```

### 7.2 Métricas Coletadas

**Infraestrutura (node-exporter):**
- CPU, RAM, Disco
- Network I/O
- Load average

**Containers (cadvisor):**
- CPU por container
- Memória por container
- Restart count

**Aplicação (endpoint /health):**
- Status do banco
- Status do Redis
- Tamanho da fila
- Jobs falhados
- Última execução do scheduler

### 7.3 Endpoints de Health Check

A aplicação expõe 3 endpoints de health check para diferentes propósitos:

#### GET /health - Status Completo

Retorna status detalhado de todos os serviços. Usado para dashboards e monitoramento.

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
      "latency_ms": 0.19,
      "response": true
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

**Códigos HTTP:**
- `200` = healthy ou degraded (aplicação funcionando)
- `503` = unhealthy (falha crítica)

#### GET /health/live - Verificação de Disponibilidade

Verifica se a aplicação está "viva" (processo PHP respondendo). Usado para verificação básica e restart automático.

```json
{
  "status": "ok",
  "timestamp": "2026-03-12T00:33:06-03:00"
}
```

**Códigos HTTP:**
- `200` = sempre (se PHP responder)

#### GET /health/ready - Verificação de Prontidão

Verifica se a aplicação está "pronta" para receber tráfego. Usado por load balancers e validação pós-deploy.

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

**Códigos HTTP:**
- `200` = ready (pode receber tráfego)
- `503` = not_ready (não enviar tráfego)

#### Comparativo dos Endpoints

| Endpoint | Uso | Verifica | HTTP 503 se |
|----------|-----|----------|-------------|
| `/health` | Dashboards, monitoramento | Tudo | Falha crítica |
| `/health/live` | Verificação básica | Apenas PHP | Nunca |
| `/health/ready` | Load balancers, deploy | DB, Redis, Cache | Serviço crítico falha |

#### Arquitetura dos Health Checks

```
app/Services/Health/
├── HealthCheckInterface.php   # Interface padrão
├── HealthService.php          # Orquestra todos os checks
├── DatabaseHealthCheck.php    # Verifica MySQL
├── RedisHealthCheck.php       # Verifica Redis
├── CacheHealthCheck.php       # Verifica Cache
├── QueueHealthCheck.php       # Verifica filas
└── SchedulerHealthCheck.php   # Verifica scheduler
```

### 7.4 Alertas Configurados

| Alerta | Condição | Severidade |
|--------|----------|------------|
| App indisponível | /health falha por 2 min | Crítico |
| Fila acumulando | queue_size > 100 por 5 min | Warning |
| Jobs falhando | failed_jobs > 0 | Warning |
| Container reiniciando | restart_count > 3 em 10 min | Warning |
| CPU alta | > 80% por 5 min | Warning |
| Disco cheio | > 90% | Crítico |
| MySQL replication lag | > 60s | Warning |
| Redis indisponível | ping falha | Crítico |

### 7.5 Acessando Grafana

**Produção:**
- URL: https://grafana.pagdesk.com.br (ou IP:3000 restrito)
- Usuário: admin
- Senha: **definida no deploy** (não usar admin)

**Dashboards disponíveis:**
- System Overview (CPU, RAM, Disco)
- Docker Containers
- Application Health
- MySQL (se configurado)

### 7.6 Monitoramento MySQL (mysqld_exporter)

Para monitorar o MySQL de produção, use o **mysqld_exporter** do Prometheus.

#### Instalação no Servidor MySQL

```bash
# Baixar mysqld_exporter
wget https://github.com/prometheus/mysqld_exporter/releases/download/v0.15.1/mysqld_exporter-0.15.1.linux-amd64.tar.gz
tar xvzf mysqld_exporter-0.15.1.linux-amd64.tar.gz
sudo mv mysqld_exporter-0.15.1.linux-amd64/mysqld_exporter /usr/local/bin/

# Criar usuário MySQL para monitoramento
mysql -u root -p
```

```sql
CREATE USER 'exporter'@'localhost' IDENTIFIED BY '<senha-forte>';
GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'localhost';
FLUSH PRIVILEGES;
```

```bash
# Criar arquivo de configuração
sudo mkdir -p /etc/mysqld_exporter
sudo tee /etc/mysqld_exporter/.my.cnf << EOF
[client]
user=exporter
password=<senha-forte>
EOF
sudo chmod 600 /etc/mysqld_exporter/.my.cnf

# Criar serviço systemd
sudo tee /etc/systemd/system/mysqld_exporter.service << EOF
[Unit]
Description=MySQL Exporter
After=network.target

[Service]
User=prometheus
ExecStart=/usr/local/bin/mysqld_exporter --config.my-cnf=/etc/mysqld_exporter/.my.cnf
Restart=always

[Install]
WantedBy=multi-user.target
EOF

# Iniciar serviço
sudo systemctl daemon-reload
sudo systemctl enable mysqld_exporter
sudo systemctl start mysqld_exporter
```

#### Configurar Prometheus

Adicionar ao `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'mysql'
    static_configs:
      - targets: ['10.0.0.20:9104']  # IP do MySQL primário
        labels:
          instance: 'mysql-primary'
      - targets: ['10.0.0.21:9104']  # IP da réplica
        labels:
          instance: 'mysql-replica'
```

#### Métricas Importantes

| Métrica | Descrição | Alerta |
|---------|-----------|--------|
| `mysql_up` | MySQL está acessível | = 0 → Crítico |
| `mysql_slave_status_seconds_behind_master` | Lag de replicação | > 60s → Warning |
| `mysql_global_status_threads_connected` | Conexões ativas | > 80% max → Warning |
| `mysql_global_status_slow_queries` | Queries lentas | Aumento súbito → Warning |
| `mysql_global_status_questions` | Queries/segundo | Baseline para anomalias |
| `mysql_global_status_innodb_buffer_pool_reads` | Leituras do disco | Alto = buffer pequeno |

#### Alertas Recomendados

```yaml
# prometheus/alerts/mysql.yml
groups:
  - name: mysql
    rules:
      - alert: MySQLDown
        expr: mysql_up == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "MySQL está indisponível"

      - alert: MySQLReplicationLag
        expr: mysql_slave_status_seconds_behind_master > 60
        for: 2m
        labels:
          severity: warning
        annotations:
          summary: "Replicação MySQL atrasada {{ $value }}s"

      - alert: MySQLTooManyConnections
        expr: mysql_global_status_threads_connected / mysql_global_variables_max_connections > 0.8
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "MySQL com {{ $value | humanizePercentage }} das conexões"

      - alert: MySQLSlowQueries
        expr: rate(mysql_global_status_slow_queries[5m]) > 0.1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Muitas slow queries ({{ $value }}/s)"
```

#### Dashboard Grafana

Importar dashboard oficial: **ID 7362** (MySQL Overview)

1. Grafana → Dashboards → Import
2. ID: `7362`
3. Selecionar datasource Prometheus

---

## 8. Backup e Recuperação

### 8.1 Política de Backup

| Tipo | Frequência | Retenção | Destino |
|------|------------|----------|---------|
| Dump MySQL (completo) | Diário 3:00 AM | 30 dias | S3/Storage externo |
| Snapshot VPS App | Semanal | 4 semanas | Provedor VPS |
| Snapshot VPS MySQL | Semanal | 4 semanas | Provedor VPS |
| Teste de Restore | Mensal | - | Ambiente isolado |

### 8.2 Script de Backup MySQL

Localização: `/opt/scripts/backup-mysql.sh` (no servidor MySQL)

```bash
#!/bin/bash
# Backup diário do MySQL

DATE=$(date +%Y-%m-%d)
BACKUP_DIR="/backups/mysql"
RETENTION_DAYS=30

# Criar backup (usa credenciais de ~/.my.cnf)
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  pagdesk_production | gzip > "$BACKUP_DIR/pagdesk_$DATE.sql.gz"

# Enviar para storage externo (exemplo S3)
aws s3 cp "$BACKUP_DIR/pagdesk_$DATE.sql.gz" s3://pagdesk-backups/mysql/

# Limpar backups antigos
find $BACKUP_DIR -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete
```

> **Configuração de credenciais:** Crie o arquivo `~/.my.cnf` com permissão 600:
> ```ini
> [client]
> user=backup_user
> password=<senha-segura>
> ```

**Cron (no servidor MySQL):**
```bash
0 3 * * * /opt/scripts/backup-mysql.sh >> /var/log/backup-mysql.log 2>&1
```

### 8.3 Restore

**1. Baixar backup:**
```bash
aws s3 cp s3://pagdesk-backups/mysql/pagdesk_2026-01-24.sql.gz ./
gunzip pagdesk_2026-01-24.sql.gz
```

**2. Restaurar:**
```bash
mysql -u root -p pagdesk_production < pagdesk_2026-01-24.sql
```

**3. Verificar:**
```bash
mysql -u root -p -e "SELECT COUNT(*) FROM pagdesk_production.users;"
```

### 8.4 Checklist de Recuperação de Desastre

1. [ ] Identificar último backup válido
2. [ ] Provisionar nova infraestrutura (se necessário)
3. [ ] Restaurar MySQL do backup
4. [ ] Verificar integridade dos dados
5. [ ] Atualizar DNS/configurações
6. [ ] Deploy da aplicação
7. [ ] Verificar funcionamento
8. [ ] Notificar stakeholders
9. [ ] Documentar incidente

---

## 9. Segurança

### 9.1 Firewall (UFW)

**Servidor de App (VPS 1):**
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw allow from 10.0.0.0/24  # Rede privada
sudo ufw enable
```

**Servidor MySQL (VPS 2 e 3):**
```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp                    # SSH
sudo ufw allow from 10.0.0.10 to any port 3306  # App server
sudo ufw allow from 10.0.0.21 to any port 3306  # Réplica (no primário)
sudo ufw allow from 10.0.0.20 to any port 3306  # Primário (na réplica)
sudo ufw enable
```

### 9.2 SSH

**Obrigatório:**
- Acesso apenas por chave (desabilitar senha)
- Usuário root desabilitado
- Porta 22 (ou customizada)

**Configuração (`/etc/ssh/sshd_config`):**
```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

### 9.3 Credenciais e Secrets

**Regras:**
- Nunca commitar `.env` no repositório
- Usar GitHub Secrets para CI/CD
- Senhas fortes (mínimo 16 caracteres)
- Rotacionar credenciais periodicamente

**GitHub Secrets necessários:**
```
SSH_HOST        # IP do servidor
SSH_PORT        # Porta SSH
SSH_USER        # Usuário SSH
SSH_PRIVATE_KEY # Chave privada
```

### 9.4 HTTPS

**Obrigatório em produção.**

**Opções:**
- Cloudflare (proxy/CDN)
- Let's Encrypt (certbot)
- Certificado comercial

**Configuração Nginx:**
```nginx
server {
    listen 80;
    server_name pagdesk.com.br;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name pagdesk.com.br;
    
    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    
    # ... resto da configuração
}
```

### 9.5 Permissões Laravel

**Correto:**
```bash
# Dono dos arquivos
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Permissões
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache
```

**Nunca usar em produção:**
```bash
chmod -R 777 ...  # ERRADO - muito permissivo
```

### 9.6 Grafana e Prometheus

**Grafana:**
- Alterar senha padrão (admin) no primeiro acesso
- Ou definir via variável: `GF_SECURITY_ADMIN_PASSWORD`

**Prometheus:**
- Não expor publicamente (porta 9090)
- Acessar apenas via rede privada ou VPN

### 9.7 Hardening de Produção

#### Servidor (Linux)

**fail2ban - Proteção contra brute force:**
```bash
# Instalar
sudo apt install fail2ban -y

# Configurar
sudo tee /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
EOF

sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

**Usuário deploy separado:**
```bash
# Criar usuário
sudo adduser deploy
sudo usermod -aG docker deploy

# Configurar chave SSH
sudo mkdir -p /home/deploy/.ssh
sudo cp ~/.ssh/authorized_keys /home/deploy/.ssh/
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys

# Dar permissão ao diretório da aplicação
sudo chown -R deploy:deploy /opt/pagdesk
```

**Atualizações automáticas de segurança:**
```bash
# Instalar unattended-upgrades
sudo apt install unattended-upgrades -y

# Configurar
sudo dpkg-reconfigure -plow unattended-upgrades
```

**Desabilitar login root:**
```bash
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no
AllowUsers deploy
```

#### Banco de Dados MySQL

**Usuário da aplicação com permissões mínimas:**
```sql
-- Usuário para aplicação (apenas o necessário)
CREATE USER 'pagdesk_app'@'10.0.0.%' IDENTIFIED BY '<senha-forte>';
GRANT SELECT, INSERT, UPDATE, DELETE ON pagdesk_production.* TO 'pagdesk_app'@'10.0.0.%';
FLUSH PRIVILEGES;

-- Usuário readonly para relatórios (opcional)
CREATE USER 'pagdesk_readonly'@'10.0.0.%' IDENTIFIED BY '<outra-senha>';
GRANT SELECT ON pagdesk_production.* TO 'pagdesk_readonly'@'10.0.0.%';
FLUSH PRIVILEGES;
```

**Audit log (opcional):**
```sql
-- Habilitar log de queries
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/log/mysql/general.log';
```

#### Rotação de Logs

**Configurar logrotate para Laravel:**
```bash
sudo tee /etc/logrotate.d/pagdesk << EOF
/opt/pagdesk/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
EOF
```

**Configurar logrotate para Docker:**
```bash
# /etc/docker/daemon.json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
```

#### Criptografia de Backups

**Criptografar backups antes de enviar para S3:**
```bash
# No script de backup
gpg --symmetric --cipher-algo AES256 \
    --batch --passphrase-file /etc/backup-key \
    -o "$BACKUP_FILE.gpg" "$BACKUP_FILE"

# Upload do arquivo criptografado
aws s3 cp "$BACKUP_FILE.gpg" s3://pagdesk-backups/mysql/

# Para descriptografar
gpg --decrypt --batch --passphrase-file /etc/backup-key \
    -o backup.sql.gz backup.sql.gz.gpg
```

#### Checklist de Hardening

- [ ] fail2ban instalado e configurado
- [ ] Usuário deploy criado (não usar root)
- [ ] SSH apenas por chave
- [ ] Atualizações automáticas habilitadas
- [ ] MySQL com usuário de permissões mínimas
- [ ] Logs com rotação configurada
- [ ] Docker com limite de logs
- [ ] Backups criptografados
- [ ] Firewall ativo (UFW)
- [ ] Prometheus não exposto publicamente
- [ ] Grafana com senha forte

---

## 10. Deploy

### 10.1 CI/CD com GitHub Actions

**Fluxo:**

```
┌─────────────┐    ┌─────────────┐    ┌─────────────────┐
│  feature/*  │───▶│  Pull Request│───▶│    CI (testes)  │
└─────────────┘    └──────┬──────┘    └─────────────────┘
                          │ merge
                          ▼
                   ┌─────────────┐    ┌─────────────────┐
                   │     dev     │───▶│ Deploy Staging  │
                   └──────┬──────┘    └─────────────────┘
                          │ merge
                          ▼
                   ┌─────────────┐    ┌─────────────────┐
                   │    main     │───▶│ Deploy Produção │
                   └─────────────┘    └─────────────────┘
```

**Arquivos:**
- `.github/workflows/ci.yml` - Testes em PRs
- `.github/workflows/deploy-staging.yml` - Deploy em staging
- `.github/workflows/deploy-prod.yml` - Deploy em produção

### 10.2 Deploy Manual (Emergência)

**1. Conectar no servidor:**
```bash
ssh deploy@pagdesk-server
```

**2. Ir para o diretório:**
```bash
cd /opt/pagdesk
```

**3. Atualizar código:**
```bash
git pull origin main
```

**4. Rebuild e restart:**
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

**5. Migrations:**
```bash
docker exec pagdesk-app php artisan migrate --force
```

**6. Cache:**
```bash
docker exec pagdesk-app php artisan optimize
```

**7. Verificar:**
```bash
docker compose ps
curl -f https://pagdesk.com.br/health
```

### 10.3 Rollback de Aplicação

> ⚠️ **Importante:** Rollback de deploy é apenas da **aplicação**, não do banco de dados.
> Migrations são **forward-only** em produção. Veja [Política de Migrations](#103-política-de-migrations).

**1. Identificar versão anterior:**
```bash
git log --oneline -10
# Ou listar tags/imagens disponíveis
docker images | grep pagdesk
```

**2. Voltar para versão anterior:**

*Opção A - Via Git (rebuild local):*
```bash
git checkout <commit-hash>
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

*Opção B - Via imagem Docker anterior (mais rápido):*
```bash
# Editar .env ou docker-compose para usar tag anterior
IMAGE_TAG=v1.2.3  # versão anterior

docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

**3. Verificar funcionamento:**
```bash
docker compose ps
curl -f https://pagdesk.com.br/health
```

**4. E o banco de dados?**

O banco **NÃO é revertido automaticamente**. Se a migration causou problemas:
- Veja seção [Política de Migrations](#103-política-de-migrations)
- Analise o impacto manualmente
- Crie migration corretiva se necessário

---

### 10.4 Política de Migrations

#### Regra Principal: Forward-Only

Em produção, migrations são **sempre para frente**. Nunca use `migrate:rollback` automaticamente.

```
❌ ERRADO (perigoso)
php artisan migrate:rollback

✅ CORRETO
# Criar nova migration corretiva
php artisan make:migration fix_problema_xyz
```

#### Por que não usar rollback?

| Situação | Risco do Rollback |
|----------|-------------------|
| Migration adicionou coluna | Baixo, mas pode quebrar código |
| Migration removeu coluna | **PERDA DE DADOS IRREVERSÍVEL** |
| Migration alterou tipo | Pode corromper dados |
| Migration dropou tabela | **PERDA DE DADOS IRREVERSÍVEL** |

#### Procedimento para Migrations Problemáticas

Se uma migration causar problema em produção:

**1. Parar e avaliar:**
```bash
# Não execute mais nada no banco
# Pare o deploy se estiver em andamento
```

**2. Voltar aplicação (não o banco):**
```bash
# Usar versão anterior da aplicação
git checkout <commit-anterior>
docker compose up -d --build
```

**3. Analisar impacto:**
- A migration removeu dados?
- A migration é reversível sem perda?
- Existe backup recente?

**4. Decidir ação:**

| Situação | Ação |
|----------|------|
| Migration não destrutiva | Pode considerar rollback manual |
| Migration removeu dados | Restaurar backup + nova migration |
| Dados corrompidos | Restaurar backup |

**5. Criar migration corretiva:**
```bash
php artisan make:migration fix_migration_problema
# Implementar correção
# Testar em staging
# Aplicar em produção
```

#### Quando Rollback é Aceitável

Rollback de migration **pode** ser usado apenas se **TODAS** as condições forem verdadeiras:

- [ ] Migration foi executada há poucos minutos
- [ ] Migration **não removeu** dados (apenas adicionou)
- [ ] Migration **não alterou** dados existentes
- [ ] Você verificou manualmente o método `down()` da migration
- [ ] Existe backup recente validado
- [ ] Análise manual foi feita por desenvolvedor sênior

```bash
# APENAS após análise manual e confirmação de segurança
docker exec pagdesk-app php artisan migrate:rollback --step=1
```

#### Boas Práticas de Migrations

**Antes de criar:**
- [ ] Migration é realmente necessária?
- [ ] Pode ser feita sem downtime?
- [ ] Foi revisada por outro desenvolvedor?

**Ao criar:**
```php
// ✅ BOM: Adicionar coluna nullable (sem lock)
$table->string('nova_coluna')->nullable();

// ⚠️ CUIDADO: Adicionar coluna NOT NULL em tabela grande
$table->string('coluna')->default('valor');

// ❌ EVITAR: Dropar coluna diretamente
$table->dropColumn('coluna_antiga');

// ✅ MELHOR: Processo gradual
// 1. Parar de usar a coluna no código
// 2. Deploy sem a coluna
// 3. Depois de validar, criar migration para remover
```

**Antes do deploy:**
- [ ] Testada em ambiente local
- [ ] Testada em staging com dados similares
- [ ] Backup de produção executado
- [ ] Plano de contingência definido

**Migrations em tabelas grandes:**
```php
// Para tabelas com milhões de registros, considere:
// - Executar fora do horário de pico
// - Usar pt-online-schema-change (Percona)
// - Dividir em múltiplas migrations menores
```

---

## 11. Troubleshooting

### Container não inicia

```bash
# Ver logs
docker logs pagdesk-app

# Ver eventos
docker events --filter container=pagdesk-app
```

### Erro de conexão com MySQL

```bash
# Testar conexão de dentro do container
docker exec pagdesk-app php artisan tinker
>>> DB::select('SELECT 1')
```

### Fila parada

```bash
# Ver status do worker
docker logs pagdesk-queue

# Ver jobs pendentes
docker exec pagdesk-app php artisan queue:work --once

# Ver jobs falhados
docker exec pagdesk-app php artisan queue:failed
```

### Scheduler não executa

```bash
# Ver logs do scheduler
docker logs pagdesk-scheduler

# Executar manualmente
docker exec pagdesk-app php artisan schedule:run
```

### Erro de permissão

```bash
# Verificar dono dos arquivos
docker exec pagdesk-app ls -la storage/

# Corrigir permissões
docker exec pagdesk-app chown -R www-data:www-data storage bootstrap/cache
```

### Alto uso de memória/CPU

```bash
# Ver consumo por container
docker stats

# Ver processos
docker exec pagdesk-app top
```

---

## 12. Checklist de Produção

### Antes do Go-Live

#### Aplicação
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` gerada e única
- [ ] `APP_URL` com domínio correto
- [ ] Migrations executadas
- [ ] `php artisan optimize` executado

#### Banco de Dados
- [ ] MySQL primário configurado
- [ ] MySQL réplica configurado
- [ ] Replicação funcionando (`Seconds_Behind_Source: 0`)
- [ ] Usuário da aplicação criado (não usar root)
- [ ] Firewall do MySQL configurado
- [ ] Backup diário ativo
- [ ] Restore testado com sucesso

#### Infraestrutura
- [ ] HTTPS ativo com certificado válido
- [ ] Redis funcionando
- [ ] Queue worker ativo
- [ ] Scheduler ativo e executando
- [ ] Storage com permissões corretas
- [ ] DNS configurado

#### Segurança
- [ ] SSH apenas por chave
- [ ] Firewall (UFW) ativo
- [ ] MySQL sem acesso público
- [ ] Redis sem acesso público
- [ ] Grafana com senha forte (não admin/admin)
- [ ] Prometheus não exposto publicamente
- [ ] Secrets configurados no GitHub Actions
- [ ] `.env` fora do repositório

#### Monitoramento
- [ ] Prometheus coletando métricas
- [ ] Grafana com dashboards configurados
- [ ] Alertas configurados e testados
- [ ] Endpoint `/health` funcionando
- [ ] Canal de alertas definido (email, Slack, etc)

#### Backup
- [ ] Script de backup configurado
- [ ] Backup automático (cron) ativo
- [ ] Storage externo configurado
- [ ] Restore testado em ambiente isolado
- [ ] Procedimento de DR documentado

#### Documentação
- [ ] Runbook de operações
- [ ] Contatos de emergência atualizados
- [ ] Procedimento de rollback documentado
- [ ] Credenciais em local seguro

---

## Contatos e Recursos

### Equipe

| Função | Contato |
|--------|---------|
| DevOps/Infra | - |
| Desenvolvimento | - |
| Suporte N1 | - |

### Links Úteis

| Recurso | URL |
|---------|-----|
| Repositório | github.com/... |
| Grafana Prod | https://grafana.pagdesk.com.br |
| Status Page | - |

---

*Última atualização: Janeiro 2026*
