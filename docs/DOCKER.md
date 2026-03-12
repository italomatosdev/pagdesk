# Docker - PagDesk (Desenvolvimento Local)

Este documento descreve como configurar e executar o ambiente de **desenvolvimento local** com Docker.

> **Para produção e staging**, consulte o arquivo [INFRAESTRUTURA.md](./INFRAESTRUTURA.md).

## Índice

1. [Visão Geral](#visão-geral)
2. [Estrutura de Arquivos](#estrutura-de-arquivos)
3. [Serviços](#serviços)
4. [Desenvolvimento Local](#desenvolvimento-local)
5. [Variáveis de Ambiente](#variáveis-de-ambiente)
6. [Comandos Úteis](#comandos-úteis)
7. [Troubleshooting](#troubleshooting)

---

## Visão Geral

O projeto utiliza Docker para containerização, com uma arquitetura de **10 serviços**:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              DOCKER COMPOSE                                  │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐           │
│  │  nginx  │  │   app   │  │  queue  │  │scheduler│  │  redis  │           │
│  │  :8080  │→ │ php-fpm │  │ worker  │  │  cron   │  │  :6379  │           │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘           │
│                                                                             │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐           │
│  │grafana  │  │promethe.│  │  node   │  │cadvisor │  │ mailpit │           │
│  │  :3000  │  │  :9090  │  │exporter │  │         │  │  :8025  │           │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘           │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                         ┌──────────┴──────────┐
                         │   MySQL Local       │
                         │   (host.docker.     │
                         │    internal:3306)   │
                         └─────────────────────┘
```

---

## Estrutura de Arquivos

```
sistema-cred/
├── Dockerfile                    # Build multi-stage da aplicação
├── docker-compose.yml            # Configuração principal (produção)
├── docker-compose.dev.yml        # Override para desenvolvimento
├── .dockerignore                 # Arquivos ignorados no build
│
├── docker/
│   ├── nginx/
│   │   └── default.conf          # Configuração do Nginx
│   │
│   ├── php/
│   │   ├── php.ini               # Configurações PHP
│   │   └── opcache.ini           # Configurações OPcache
│   │
│   ├── scripts/
│   │   ├── entrypoint.sh         # Script de inicialização
│   │   └── scheduler.sh          # Script do cron Laravel
│   │
│   ├── prometheus/
│   │   └── prometheus.yml        # Configuração Prometheus
│   │
│   └── grafana/
│       └── provisioning/
│           ├── datasources/
│           │   └── prometheus.yml
│           └── dashboards/
│               ├── dashboards.yml
│               └── sistema.json
│
├── .github/
│   └── workflows/
│       ├── ci.yml                # Pipeline de CI (testes)
│       └── deploy.yml            # Pipeline de CD (deploy)
│
└── docs/
    ├── DEPLOY.md                 # Guia de deploy em produção
    └── DOCKER.md                 # Este arquivo
```

---

## Serviços

### Aplicação

| Serviço | Imagem | Função | Porta |
|---------|--------|--------|-------|
| **app** | pagdesk-app | PHP-FPM com Laravel | 9000 (interno) |
| **nginx** | nginx:1.25-alpine | Servidor web | 8080 (HTTP), 8443 (HTTPS) |
| **queue** | pagdesk-queue | Worker de filas Laravel | - |
| **scheduler** | pagdesk-scheduler | Cron do Laravel (schedule:run) | - |
| **redis** | redis:7-alpine | Cache, sessões e filas | 6379 (interno) |

### Monitoramento

| Serviço | Imagem | Função | Porta |
|---------|--------|--------|-------|
| **grafana** | grafana/grafana:10.1.0 | Dashboards | 3000 |
| **prometheus** | prom/prometheus:v2.47.0 | Coleta de métricas | 9090 |
| **node-exporter** | prom/node-exporter:v1.6.1 | Métricas do host | 9100 (interno) |
| **cadvisor** | gcr.io/cadvisor/cadvisor:v0.47.2 | Métricas dos containers | 8080 (interno) |

### Desenvolvimento

| Serviço | Imagem | Função | Porta |
|---------|--------|--------|-------|
| **mailpit** | axllent/mailpit | Captura de e-mails | 8025 (web), 1025 (SMTP) |

---

## Desenvolvimento Local

### Pré-requisitos

- Docker Desktop instalado
- MySQL rodando localmente (porta 3306)
- Git

### Primeiro Setup

```bash
# 1. Clone o repositório
git clone <repo-url>
cd sistema-cred

# 2. Copie o .env
cp .env.example .env

# 3. Configure as variáveis no .env
# - DB_HOST=host.docker.internal
# - REDIS_HOST=redis
# - NGINX_HTTP_PORT=8080 (se porta 80 ocupada)

# 4. Suba os containers
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build

# 5. Aguarde todos ficarem healthy
docker compose -f docker-compose.yml -f docker-compose.dev.yml ps

# 6. Execute as migrations (se necessário)
docker exec pagdesk-app php artisan migrate
```

### Acessos

| Serviço | URL |
|---------|-----|
| **Aplicação** | http://localhost:8080 |
| **Health Check** | http://localhost:8080/health |
| **Liveness** | http://localhost:8080/health/live |
| **Readiness** | http://localhost:8080/health/ready |
| **Grafana** | http://localhost:3000 (admin/admin - apenas dev) |
| **Prometheus** | http://localhost:9090 |
| **Mailpit** | http://localhost:8025 |

### Comandos do Dia a Dia

```bash
# Iniciar ambiente
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Parar ambiente
docker compose -f docker-compose.yml -f docker-compose.dev.yml down

# Ver logs de um serviço
docker logs -f pagdesk-app
docker logs -f pagdesk-nginx
docker logs -f pagdesk-queue

# Executar artisan
docker exec pagdesk-app php artisan <comando>

# Executar composer
docker exec pagdesk-app composer <comando>

# Acessar shell do container
docker exec -it pagdesk-app sh

# Limpar caches
docker exec pagdesk-app php artisan cache:clear
docker exec pagdesk-app php artisan view:clear
docker exec pagdesk-app php artisan config:clear

# Rebuild após mudanças no Dockerfile
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

---

## Variáveis de Ambiente

### Essenciais para Docker

```env
# Nome do projeto (prefixo dos containers)
COMPOSE_PROJECT_NAME=pagdesk

# Portas (ajuste se conflitar)
NGINX_HTTP_PORT=8080
NGINX_HTTPS_PORT=8443

# Banco de dados (apontar para host local)
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=cred
DB_USERNAME=root
DB_PASSWORD=

# Redis (nome do serviço no Docker)
REDIS_HOST=redis
REDIS_PORT=6379

# Drivers
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Variáveis Opcionais (Desenvolvimento)

```env
# Grafana (APENAS para desenvolvimento local)
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=admin

# Prometheus
PROMETHEUS_PORT=9090

# Grafana
GRAFANA_PORT=3000
```

> ⚠️ **Produção:** Use senhas fortes. Veja [INFRAESTRUTURA.md](./INFRAESTRUTURA.md).

---

## Troubleshooting

### Porta já em uso

**Erro:** `bind: address already in use`

**Solução:** Altere a porta no `.env`:
```env
NGINX_HTTP_PORT=8081
```

### MySQL não conecta

**Erro:** `Connection refused` ou `Host not found`

**Solução:** Use `host.docker.internal` como DB_HOST:
```env
DB_HOST=host.docker.internal
```

### Erro de views/cache

**Erro:** `filemtime(): stat failed for /var/www/html/storage/framework/views/...`

**Solução:** Limpe os caches:
```bash
docker exec pagdesk-app php artisan view:clear
docker exec pagdesk-app php artisan cache:clear
```

### Container unhealthy

**Verificar logs:**
```bash
docker logs pagdesk-app
```

**Verificar healthcheck:**
```bash
docker inspect pagdesk-app | grep -A 20 "Health"
```

### Permissões de storage

**Erro:** `Permission denied` no storage

**Solução (desenvolvimento):**
```bash
docker exec pagdesk-app chown -R www-data:www-data /var/www/html/storage
docker exec pagdesk-app chmod -R 775 /var/www/html/storage
```

> ⚠️ **Nunca use `chmod 777` em produção.** Veja [INFRAESTRUTURA.md](./INFRAESTRUTURA.md) para permissões corretas.

### Containers órfãos

**Limpar tudo:**
```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml down -v
docker system prune -f
```

---

## Arquitetura do Dockerfile

O Dockerfile usa **multi-stage build** para otimização:

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Stage 1:      │     │   Stage 2:      │     │   Stage 3:      │
│   composer      │     │     node        │     │   production    │
│                 │     │                 │     │                 │
│ - composer.json │     │ - package.json  │     │ - PHP 8.2-FPM   │
│ - composer.lock │     │ - resources/    │     │ - Extensions    │
│ - vendor/       │     │ - public/build/ │     │ - App code      │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         └───────────────────────┴───────────────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │    Imagem Final         │
                    │    ~150MB               │
                    │                         │
                    │ - PHP-FPM Alpine        │
                    │ - Vendor otimizado      │
                    │ - Assets compilados     │
                    │ - Sem dev dependencies  │
                    └─────────────────────────┘
```

### Extensões PHP Instaladas

- pdo_mysql
- redis
- opcache
- pcntl (para Horizon)
- bcmath
- gd
- zip
- intl

---

## Volumes em Desenvolvimento

O `docker-compose.dev.yml` monta o código local para hot-reload, mas **exclui**:

| Diretório | Motivo |
|-----------|--------|
| `/var/www/html/vendor` | Usa vendor do container (--no-dev) |
| `/var/www/html/node_modules` | Não necessário em runtime |
| `/var/www/html/bootstrap/cache` | Evita conflito de cache services.php |
| `/var/www/html/storage/framework` | Evita conflito de views compiladas |

Isso garante que o container use suas próprias dependências otimizadas enquanto permite editar o código fonte localmente.

---

## Diferenças Dev vs Produção

| Aspecto | Desenvolvimento | Produção |
|---------|-----------------|----------|
| **Código** | Montado via volume | Copiado na imagem |
| **Vendor** | --no-dev no container | --no-dev na imagem |
| **OPcache** | validate_timestamps=1 | validate_timestamps=0 |
| **Debug** | APP_DEBUG=true | APP_DEBUG=false |
| **Cache** | Desabilitado | config:cache, route:cache |
| **MySQL** | host.docker.internal | Servidor externo |
| **Mailpit** | Sim | Não |

---

## Documentação Relacionada

| Documento | Descrição |
|-----------|-----------|
| [INFRAESTRUTURA.md](./INFRAESTRUTURA.md) | Arquitetura completa, staging e produção |
| [BACKUP.md](./BACKUP.md) | Política de backup e recuperação |
| [DEPLOY.md](./DEPLOY.md) | Guia de deploy em produção |
| [OBSERVABILIDADE.md](./OBSERVABILIDADE.md) | Health checks, métricas e alertas |

---

## Importante: Ambiente Local ≠ Produção

Este documento é **apenas para desenvolvimento local**. Em produção:

- **Não use** `host.docker.internal` para MySQL
- **Não use** `APP_DEBUG=true`
- **Não use** senhas fracas (admin/admin)
- **Use** banco MySQL externo com réplica
- **Use** HTTPS obrigatório
- **Use** backups automáticos

Consulte [INFRAESTRUTURA.md](./INFRAESTRUTURA.md) para configuração de produção.
