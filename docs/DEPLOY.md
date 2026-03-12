# Guia de Deploy - PagDesk

Este documento descreve como fazer deploy do PagDesk em produção usando Docker.

## Índice

1. [Arquitetura](#arquitetura)
2. [Requisitos](#requisitos)
3. [Configuração do Servidor](#configuração-do-servidor)
4. [Configuração do MySQL](#configuração-do-mysql)
5. [Primeiro Deploy](#primeiro-deploy)
6. [Deploy Automático (CI/CD)](#deploy-automático-cicd)
7. [Configuração de HTTPS](#configuração-de-https)
8. [Monitoramento](#monitoramento)
9. [Comandos Úteis](#comandos-úteis)
10. [Troubleshooting](#troubleshooting)
11. [Checklist de Segurança](#checklist-de-segurança)

---

## Arquitetura

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SERVIDOR VPS                                    │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         DOCKER COMPOSE                                 │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐     │  │
│  │  │  nginx  │  │   app   │  │  queue  │  │scheduler│  │  redis  │     │  │
│  │  │  :80    │→ │ php-fpm │  │ worker  │  │  cron   │  │  :6379  │     │  │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘     │  │
│  │                                                                       │  │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐                   │  │
│  │  │grafana  │  │promethe.│  │  node   │  │cadvisor │                   │  │
│  │  │  :3000  │  │  :9090  │  │exporter │  │  :8080  │                   │  │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘                   │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────────────┘
                                     │
                          ┌──────────┴──────────┐
                          │   SERVIDOR MySQL    │
                          │   (Separado)        │
                          └─────────────────────┘
```

### Serviços

| Serviço | Função | Porta |
|---------|--------|-------|
| nginx | Servidor web | 80, 443 |
| app | PHP-FPM (Laravel) | 9000 (interno) |
| queue | Worker de filas | - |
| scheduler | Cron do Laravel | - |
| redis | Cache e filas | 6379 (interno) |
| prometheus | Coleta de métricas | 9090 |
| grafana | Dashboards | 3000 |
| node-exporter | Métricas do host | 9100 (interno) |
| cadvisor | Métricas dos containers | 8080 (interno) |

---

## Requisitos

### Servidor de Aplicação
- Ubuntu 22.04 LTS
- 4GB RAM (mínimo)
- 2 vCPU
- 80GB SSD
- Docker e Docker Compose instalados

### Servidor de Banco de Dados
- Ubuntu 22.04 LTS ou Managed MySQL
- 4GB RAM (mínimo)
- MySQL 8.0

---

## Configuração do Servidor

### 1. Atualizar Sistema

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Instalar Docker

```bash
# Dependências
sudo apt install -y ca-certificates curl gnupg

# Adicionar chave GPG do Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Adicionar repositório
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instalar Docker
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Verificar instalação
docker --version
docker compose version
```

### 3. Configurar Usuário Deploy

```bash
# Criar usuário
sudo adduser deploy
sudo usermod -aG docker deploy
sudo usermod -aG sudo deploy

# Configurar SSH para o usuário
sudo mkdir -p /home/deploy/.ssh
sudo cp ~/.ssh/authorized_keys /home/deploy/.ssh/
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

### 4. Configurar Firewall

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
sudo ufw status
```

### 5. Configurar Swap (para servidores pequenos)

```bash
sudo fallocate -l 4G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 6. Criar Estrutura de Diretórios

```bash
sudo mkdir -p /opt/pagdesk
sudo chown deploy:deploy /opt/pagdesk
```

---

## Configuração do MySQL

### No servidor MySQL separado:

```bash
# Instalar MySQL
sudo apt update
sudo apt install -y mysql-server

# Configurar segurança
sudo mysql_secure_installation

# Acessar MySQL
sudo mysql -u root -p
```

```sql
-- Criar banco de dados
CREATE DATABASE pagdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Criar usuário (substitua IP_DO_APP_SERVER pelo IP real)
CREATE USER 'pagdesk'@'IP_DO_APP_SERVER' IDENTIFIED BY 'SENHA_SEGURA_AQUI';

-- Dar permissões
GRANT ALL PRIVILEGES ON pagdesk.* TO 'pagdesk'@'IP_DO_APP_SERVER';
FLUSH PRIVILEGES;
```

### Configurar acesso remoto:

```bash
# Editar configuração
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Alterar bind-address para o IP do servidor ou 0.0.0.0
bind-address = 0.0.0.0

# Reiniciar MySQL
sudo systemctl restart mysql

# Liberar porta no firewall
sudo ufw allow from IP_DO_APP_SERVER to any port 3306
```

---

## Primeiro Deploy

### 1. Clonar Repositório

```bash
cd /opt/pagdesk
git clone git@github.com:seu-usuario/pagdesk.git .
```

### 2. Configurar Variáveis de Ambiente

```bash
cp .env.production.example .env
nano .env
```

Preencher obrigatoriamente:
- `APP_KEY` (será gerado automaticamente)
- `APP_URL`
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `GRAFANA_ADMIN_PASSWORD`

### 3. Login no Registry

```bash
docker login ghcr.io -u seu-usuario
# Usar token de acesso pessoal como senha
```

### 4. Subir Containers

```bash
# Baixar imagens
docker compose pull

# Subir em modo detached
docker compose up -d

# Verificar status
docker compose ps
```

### 5. Executar Setup Inicial

```bash
# Gerar APP_KEY
docker compose exec app php artisan key:generate

# Executar migrations
docker compose exec app php artisan migrate --force

# Otimizar
docker compose exec app php artisan optimize

# Criar link do storage
docker compose exec app php artisan storage:link

# Criar usuário admin (se necessário)
docker compose exec app php artisan tinker
# >>> App\Models\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('senha')]);
```

### 6. Verificar

```bash
# Health check
curl http://localhost/health

# Logs
docker compose logs -f app
docker compose logs -f nginx
```

---

## Deploy Automático (CI/CD)

### Configurar GitHub Secrets

No repositório GitHub, vá em `Settings > Secrets and variables > Actions` e adicione:

| Secret | Valor |
|--------|-------|
| `SSH_HOST` | IP do servidor |
| `SSH_PORT` | 22 |
| `SSH_USER` | deploy |
| `SSH_PRIVATE_KEY` | Chave SSH privada (conteúdo completo) |

### Gerar Chave SSH para Deploy

```bash
# No seu computador local
ssh-keygen -t ed25519 -C "deploy@pagdesk" -f ~/.ssh/pagdesk-deploy

# Copiar chave pública para o servidor
ssh-copy-id -i ~/.ssh/pagdesk-deploy.pub deploy@IP_DO_SERVIDOR

# Copiar conteúdo da chave privada para o GitHub Secret
cat ~/.ssh/pagdesk-deploy
```

### Fluxo de Deploy

1. Push para `main` → Trigger workflow
2. Executa testes
3. Build da imagem Docker
4. Push para GitHub Container Registry
5. SSH no servidor
6. `docker compose pull`
7. `docker compose up -d`
8. Migrations e otimizações
9. Verifica health check

---

## Configuração de HTTPS

### Opção 1: Cloudflare (Recomendado)

1. Adicionar domínio no Cloudflare
2. Apontar DNS para IP do servidor
3. Habilitar SSL/TLS em modo "Full"
4. Cloudflare gerencia certificado automaticamente

### Opção 2: Let's Encrypt + Certbot

```bash
# Instalar Certbot
sudo apt install -y certbot

# Gerar certificado (com containers parados)
docker compose stop nginx
sudo certbot certonly --standalone -d seu-dominio.com

# Criar diretório para certificados
mkdir -p /opt/pagdesk/docker/nginx/ssl

# Copiar certificados
sudo cp /etc/letsencrypt/live/seu-dominio.com/fullchain.pem /opt/pagdesk/docker/nginx/ssl/
sudo cp /etc/letsencrypt/live/seu-dominio.com/privkey.pem /opt/pagdesk/docker/nginx/ssl/
sudo chown -R deploy:deploy /opt/pagdesk/docker/nginx/ssl

# Editar docker-compose.yml para montar certificados
# Descomentar linhas de SSL no nginx/default.conf

# Reiniciar
docker compose up -d nginx
```

### Renovação Automática (Cron)

```bash
# Adicionar ao crontab do root
sudo crontab -e

# Adicionar linha:
0 3 * * * certbot renew --quiet && cp /etc/letsencrypt/live/seu-dominio.com/*.pem /opt/pagdesk/docker/nginx/ssl/ && docker compose -f /opt/pagdesk/docker-compose.yml restart nginx
```

---

## Monitoramento

### Acessar Grafana

```
URL: http://seu-dominio:3000
Usuário: admin
Senha: (definida em GRAFANA_ADMIN_PASSWORD)
```

### Dashboard Disponível

O dashboard "PagDesk - Overview" mostra:
- Uso de CPU
- Uso de Memória
- Uso de Disco
- Tráfego de Rede
- CPU por Container
- Memória por Container

### Acessar Prometheus

```
URL: http://seu-dominio:9090
```

### Métricas Disponíveis

- `node_cpu_seconds_total` - CPU do host
- `node_memory_MemAvailable_bytes` - Memória disponível
- `node_filesystem_avail_bytes` - Espaço em disco
- `container_cpu_usage_seconds_total` - CPU por container
- `container_memory_usage_bytes` - Memória por container

---

## Comandos Úteis

### Gerenciamento de Containers

```bash
# Status dos containers
docker compose ps

# Logs em tempo real
docker compose logs -f [serviço]

# Reiniciar serviço
docker compose restart [serviço]

# Parar tudo
docker compose down

# Subir tudo
docker compose up -d

# Rebuild forçado
docker compose up -d --build --force-recreate
```

### Laravel (dentro do container)

```bash
# Acessar shell do container
docker compose exec app sh

# Ou executar comandos diretamente:
docker compose exec app php artisan [comando]

# Exemplos:
docker compose exec app php artisan migrate --force
docker compose exec app php artisan queue:restart
docker compose exec app php artisan cache:clear
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan tinker
```

### Manutenção

```bash
# Limpar imagens antigas
docker image prune -af

# Limpar tudo não utilizado
docker system prune -af

# Ver uso de disco
docker system df

# Backup do Redis
docker compose exec redis redis-cli BGSAVE
```

---

## Troubleshooting

### Container não inicia

```bash
# Verificar logs
docker compose logs [serviço]

# Verificar eventos
docker compose events

# Verificar configuração
docker compose config
```

### Erro de conexão com banco

1. Verificar se MySQL está rodando no servidor de banco
2. Verificar se firewall permite conexão
3. Testar conexão: `mysql -h IP_DO_MYSQL -u usuario -p`
4. Verificar variáveis DB_* no .env

### Erro de permissão no storage

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/storage
docker compose exec app chmod -R 775 /var/www/html/storage
```

### Fila não processa jobs

```bash
# Verificar worker
docker compose logs -f queue

# Reiniciar worker
docker compose restart queue

# Verificar Redis
docker compose exec redis redis-cli PING
```

### Scheduler não executa

```bash
# Verificar logs
docker compose logs -f scheduler

# Executar manualmente
docker compose exec scheduler php artisan schedule:run
```

---

## Checklist de Segurança

### Servidor

- [ ] SSH apenas por chave (senha desabilitada)
- [ ] Firewall configurado (UFW)
- [ ] Atualizações de segurança automáticas
- [ ] Fail2ban instalado
- [ ] Swap configurado

### Aplicação

- [ ] APP_DEBUG=false em produção
- [ ] APP_ENV=production
- [ ] HTTPS configurado
- [ ] .env não está no repositório
- [ ] APP_KEY único e seguro

### Banco de Dados

- [ ] Senha forte
- [ ] Acesso apenas do IP do app server
- [ ] Backups automáticos configurados
- [ ] Usuário com permissões mínimas

### Docker

- [ ] Imagens de fontes oficiais
- [ ] Containers com restart policy
- [ ] Health checks configurados
- [ ] Logs rotacionados

### Monitoramento

- [ ] Grafana com senha forte
- [ ] Prometheus acessível apenas internamente
- [ ] Alertas configurados (opcional)

---

## Atualizações

### Atualizar Aplicação (Manual)

```bash
cd /opt/pagdesk
git pull
docker compose pull
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan queue:restart
```

### Atualizar Aplicação (CI/CD)

Basta fazer push para a branch `main`. O GitHub Actions executará o deploy automaticamente.

---

## Suporte

Em caso de problemas:

1. Verificar logs: `docker compose logs -f`
2. Verificar health: `curl localhost/health`
3. Verificar status: `docker compose ps`
4. Consultar este documento
