# Instalação da Infraestrutura de Produção - PagDesk

Este documento detalha o processo de instalação da infraestrutura de produção do sistema PagDesk.

## Visão Geral da Arquitetura

| Servidor | Função | IP Público | IP Privado (VPC) | Especificações |
|----------|--------|------------|------------------|----------------|
| **pagdesk-app** | Aplicação Docker | 172.235.157.83 | 10.0.0.2 | Linode Dedicated 4GB |
| **pagdesk-db-primary** | MySQL Principal | 172.235.157.93 | 10.0.0.3 | Linode Dedicated 4GB |
| **pagdesk-db-replica** | MySQL Réplica | 172.235.157.113 | 10.0.0.4 | Linode Dedicated 4GB |

**Provedor:** Linode (Akamai)  
**Região:** US, Miami, FL (us-mia)  
**Sistema Operacional:** Ubuntu 24.04 LTS  
**Custo Total:** $108/mês ($36 x 3)

---

## 1. Criação da VPC

Antes de criar os servidores, foi criada uma VPC para comunicação privada entre eles.

**Configuração da VPC:**
- **Nome:** `pagdesk-vpc`
- **Região:** US, Miami, FL (us-mia)
- **Subnet:** `pagdesk-subnet`
- **Range IPv4:** `10.0.0.0/24`

---

## 2. Criação dos Linodes

Cada Linode foi criado com as seguintes configurações:

- **Image:** Ubuntu 24.04 LTS
- **Region:** US, Miami, FL (us-mia)
- **Plan:** Dedicated 4GB ($36/mês)
- **Networking:**
  - Network Connection: VPC
  - VPC: `pagdesk-vpc`
  - Subnet: `pagdesk-subnet`
  - Auto-assign VPC IPv4: ✅
  - Allow public IPv4 access (1:1 NAT): ✅

**Add-ons:**
- `pagdesk-db-primary`: Backup automático habilitado ($5/mês)

---

## 3. Configuração Inicial (Todos os Servidores)

Executado em cada servidor como `root`:

```bash
# Atualizar sistema
apt update && apt upgrade -y

# Instalar pacotes essenciais
apt install -y curl wget git ufw fail2ban htop

# Criar usuário deploy
adduser deploy --disabled-password --gecos ""

# Adicionar ao grupo sudo
usermod -aG sudo deploy

# Permitir sudo sem senha para deploy
echo "deploy ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers.d/deploy

# Criar diretório SSH para o usuário deploy
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chown deploy:deploy /home/deploy/.ssh
```

---

## 4. Configuração de Chave SSH

### No computador local (Mac):

```bash
# Verificar/criar chave SSH
cat ~/.ssh/id_ed25519.pub
# ou criar: ssh-keygen -t ed25519 -C "seu-email@exemplo.com"
```

### Em cada servidor (como root):

```bash
# Adicionar chave pública
echo "ssh-ed25519 AAAAC3... seu-email@exemplo.com" >> /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
```

### Teste de conexão:

```bash
ssh deploy@172.235.157.83    # app
ssh deploy@172.235.157.93    # db-primary
ssh deploy@172.235.157.113   # db-replica
```

---

## 5. Configuração do Firewall (UFW)

### No `pagdesk-app`:

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow from 10.0.0.0/24 comment 'VPC Internal'
ufw --force enable
```

**Portas abertas:**
- 22/tcp (SSH)
- 80/tcp (HTTP)
- 443/tcp (HTTPS)
- 10.0.0.0/24 (VPC Internal)

### No `pagdesk-db-primary` e `pagdesk-db-replica`:

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow from 10.0.0.0/24 to any port 3306 comment 'MySQL from VPC'
ufw allow from 10.0.0.0/24 comment 'VPC Internal'
ufw --force enable
```

**Portas abertas:**
- 22/tcp (SSH)
- 3306 apenas da VPC (MySQL)
- 10.0.0.0/24 (VPC Internal)

---

## 6. Desabilitar Login por Senha (SSH)

Executado em todos os servidores:

```bash
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd
```

---

## 7. Reinicialização dos Servidores

Após as configurações iniciais:

```bash
reboot
```

---

## 8. Instalação do MySQL no `pagdesk-db-primary`

```bash
# Instalar MySQL
apt update
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Configurar para aceitar conexões da VPC
sed -i 's/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf

# Configurar server-id para replicação (primary = 1)
echo -e "\n[mysqld]\nserver-id = 1\nlog_bin = /var/log/mysql/mysql-bin.log" >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Reiniciar MySQL
systemctl restart mysql
```

### Criar banco e usuários:

```bash
mysql -u root <<EOF
-- Criar banco
CREATE DATABASE pagdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usuário da aplicação (acesso do App server via VPC)
CREATE USER 'pagdesk'@'10.0.0.%' IDENTIFIED BY 'Pg2020dsk*pd';
GRANT ALL PRIVILEGES ON pagdesk.* TO 'pagdesk'@'10.0.0.%';

-- Usuário de replicação (para o replica server)
CREATE USER 'replicator'@'10.0.0.4' IDENTIFIED BY 'Pg2020dsk*pd';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'10.0.0.4';

FLUSH PRIVILEGES;
EOF
```

### Verificar status do binlog:

```bash
mysql -u root -e "SHOW MASTER STATUS\G"
```

Resultado:
```
File: mysql-bin.000002
Position: 157
```

---

## 9. Instalação do MySQL no `pagdesk-db-replica`

```bash
# Instalar MySQL
apt update
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Permitir conexões da VPC
sed -i 's/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf

# Configurar server-id para replicação (replica = 2)
echo -e "\n[mysqld]\nserver-id = 2\nrelay-log = /var/log/mysql/mysql-relay-bin.log" >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Reiniciar MySQL
systemctl restart mysql

# Criar banco (necessário antes da replicação)
mysql -u root -e "CREATE DATABASE pagdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Configurar replicação:

```bash
mysql -u root <<EOF
CHANGE REPLICATION SOURCE TO
    SOURCE_HOST='10.0.0.3',
    SOURCE_USER='replicator',
    SOURCE_PASSWORD='Pg2020dsk*pd',
    SOURCE_LOG_FILE='mysql-bin.000002',
    SOURCE_LOG_POS=157;

START REPLICA;
EOF
```

### Verificar status da replicação:

```bash
mysql -u root -e "SHOW REPLICA STATUS\G" | grep -E "Replica_IO_Running|Replica_SQL_Running|Seconds_Behind"
```

Resultado esperado:
```
Replica_IO_Running: Yes
Replica_SQL_Running: Yes
Seconds_Behind_Source: 0
```

### Testar replicação:

**No primary:**
```bash
mysql -u root -e "USE pagdesk; CREATE TABLE teste_rep (id INT); INSERT INTO teste_rep VALUES (999);"
```

**No replica:**
```bash
mysql -u root -e "USE pagdesk; SELECT * FROM teste_rep;"
# Deve retornar: 999
```

**Limpar tabela de teste:**
```bash
mysql -u root -e "USE pagdesk; DROP TABLE teste_rep;"
```

---

## 10. Credenciais de Acesso

### SSH

| Servidor | Comando |
|----------|---------|
| App | `ssh deploy@172.235.157.83` |
| DB Primary | `ssh deploy@172.235.157.93` |
| DB Replica | `ssh deploy@172.235.157.113` |

### MySQL (Aplicação)

| Parâmetro | Valor |
|-----------|-------|
| DB_HOST | `10.0.0.3` |
| DB_PORT | `3306` |
| DB_DATABASE | `pagdesk` |
| DB_USERNAME | `pagdesk` |
| DB_PASSWORD | `Pg2020dsk*pd` |

### MySQL (Replicação)

| Parâmetro | Valor |
|-----------|-------|
| HOST | `10.0.0.3` |
| USER | `replicator` |
| PASSWORD | `Pg2020dsk*pd` |

---

## 11. Instalação do Docker no `pagdesk-app`

Conectar no servidor de aplicação:

```bash
ssh deploy@172.235.157.83
sudo su -
```

### Instalar Docker:

```bash
# Adicionar repositório oficial do Docker
apt update
apt install -y ca-certificates curl gnupg
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instalar Docker
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Adicionar usuário deploy ao grupo docker
usermod -aG docker deploy
```

### Verificar instalação:

```bash
docker --version
docker compose version
```

Resultado esperado:
```
Docker version 29.3.0, build 5927d80
Docker Compose version v5.1.0
```

### Criar diretório da aplicação:

```bash
mkdir -p /var/www/pagdesk
chown deploy:deploy /var/www/pagdesk
```

---

## 12. Configuração do Repositório GitHub

### Criar repositório no GitHub:

1. Acessar https://github.com/new
2. Nome do repositório: `pagdesk`
3. Visibilidade: **Private**
4. Criar repositório

### Configurar Personal Access Token (PAT):

1. Acessar https://github.com/settings/tokens
2. **Generate new token (classic)**
3. Configurações:
   - **Note:** `pagdesk-deploy`
   - **Expiration:** 90 days (ou mais)
   - **Scopes:**
     - ✅ `repo` (Full control of private repositories)
     - ✅ `workflow` (Update GitHub Action workflows)
     - ✅ `write:packages` (Upload packages to GitHub Package Registry)
     - ✅ `read:packages` (Download packages from GitHub Package Registry)
4. Gerar e salvar o token em local seguro

### No computador local - Preparar repositório:

```bash
cd /Users/italomatos/Documents/Projetos/sistema-cred

# Inicializar Git (se necessário)
git init

# Configurar branch principal
git branch -M main

# Adicionar remote
git remote add origin https://github.com/italomatosdev/pagdesk.git
# ou se já existir:
git remote set-url origin https://github.com/italomatosdev/pagdesk.git
```

### Garantir que arquivos sensíveis estão no .gitignore:

O `.gitignore` deve conter:

```gitignore
# Laravel
/vendor/
/node_modules/
/public/build/
/public/hot
/public/storage
/storage/*.key
/.env
/.env.*
!/.env.example
!/.env.production.example
!/.env.staging.example

# IDE
/.idea
/.vscode
*.swp
*.swo
.DS_Store

# Logs
*.log

# Testing
/coverage
.phpunit.result.cache

# Docker
docker-compose.override.yml

# Arquivos grandes/desnecessários
*.zip
*.tar.gz
template-webadmin/
```

### Fazer primeiro push:

```bash
# Verificar status
git status

# Adicionar arquivos
git add .

# Commit inicial
git commit -m "Initial commit: PagDesk application"

# Push para GitHub
git push -u origin main
```

> **Nota:** Ao fazer push, use o **username** do GitHub e o **PAT** como senha.

---

## 13. Clone do Repositório no Servidor

### No servidor `pagdesk-app`:

```bash
ssh deploy@172.235.157.83
cd /var/www/pagdesk

# Clonar repositório
git clone https://github.com/italomatosdev/pagdesk.git .
```

> **Nota:** Use o PAT como senha quando solicitado.

### Verificar clone:

```bash
ls -la
```

Deve listar os arquivos do projeto (Dockerfile, docker-compose.yml, app/, etc.)

---

## 14. Arquivo .env de Produção

No servidor `pagdesk-app`, foi criado o arquivo `.env`:

```bash
ssh deploy@172.235.157.83
cd /var/www/pagdesk
nano .env
```

Conteúdo do `.env` de produção:

```env
APP_NAME=PagDesk
APP_ENV=production
APP_KEY=base64:AlJG6lvjCjM/3zNkbdPy2yctEDVrD9abF5d7lq7RxxA=
APP_DEBUG=false
APP_TIMEZONE=America/Sao_Paulo
APP_URL=https://pagdesk.com

DB_CONNECTION=mysql
DB_HOST=10.0.0.3
DB_PORT=3306
DB_DATABASE=pagdesk
DB_USERNAME=pagdesk
DB_PASSWORD=Pg2020dsk*pd

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

LOG_CHANNEL=stack
LOG_LEVEL=error

COMPOSE_PROJECT_NAME=pagdesk
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443

GRAFANA_ADMIN_PASSWORD=PgDesk2026Grafana!
```

---

## 15. Build e Deploy

```bash
cd /var/www/pagdesk

# Build das imagens
docker compose build

# Subir containers
docker compose up -d

# Verificar containers
docker ps
```

Resultado:
```
✔ Container pagdesk-redis         Healthy
✔ Container pagdesk-app           Healthy
✔ Container pagdesk-nginx         Started
✔ Container pagdesk-scheduler     Started
✔ Container pagdesk-queue         Started
✔ Container pagdesk-prometheus    Started
✔ Container pagdesk-grafana       Started
✔ Container pagdesk-node-exporter Started
✔ Container pagdesk-cadvisor      Started
```

---

## 16. Execução das Migrations

```bash
docker exec pagdesk-app php artisan migrate --force
```

> **Nota:** Algumas migrations precisaram de correções de ordem/sintaxe durante a execução inicial. Todas as correções foram commitadas no repositório.

### Limpar caches após migrations:

```bash
docker exec pagdesk-app php artisan config:clear
docker exec pagdesk-app php artisan route:clear
docker exec pagdesk-app php artisan view:clear
docker exec pagdesk-app php artisan cache:clear
```

### Verificar health check:

```bash
curl http://localhost/health
```

Resultado:
```json
{
  "status": "healthy",
  "app": "PagDesk",
  "version": "1.0.0",
  "environment": "production",
  "checks": {
    "database": {"status": "ok"},
    "redis": {"status": "ok"},
    "cache": {"status": "ok"},
    "queue": {"status": "ok"},
    "scheduler": {"status": "no_data"}
  }
}
```

### Executar Seeders Essenciais

> **IMPORTANTE:** Os seeders não são executados automaticamente no deploy. Rode-os manualmente após a primeira instalação.

```bash
# Criar papéis (administrador, gestor, consultor, cliente)
docker exec pagdesk-app php artisan db:seed --class=RoleSeeder --force

# Criar permissões associadas aos papéis
docker exec pagdesk-app php artisan db:seed --class=PermissionSeeder --force
```

**Verificar se os papéis foram criados:**
```bash
docker exec pagdesk-app php artisan tinker --execute="print_r(\App\Modules\Core\Models\Role::pluck('name')->toArray());"
```

Resultado esperado:
```
Array ( [0] => administrador [1] => cliente [2] => consultor [3] => gestor )
```

> **Nota:** Sem os seeders, funcionalidades como criação de usuários com papéis não funcionarão corretamente.

---

## 17. Configuração do Domínio (Cloudflare)

### DNS

| Tipo | Nome | Conteúdo | Proxy |
|------|------|----------|-------|
| A | pagdesk.com | 172.235.157.83 | Com proxy (laranja) |
| CNAME | www | pagdesk.com | Com proxy (laranja) |

### SSL/TLS

- **Modo:** Flexível
- Isso habilita HTTPS entre visitante ↔ Cloudflare, e HTTP entre Cloudflare ↔ servidor

### Acesso

- **URL:** https://pagdesk.com
- **Health Check:** https://pagdesk.com/health

---

## 18. Criação do Super Admin

O sistema possui dois níveis de acesso:

| Tipo | Descrição |
|------|-----------|
| **Super Admin** | `is_super_admin=true`, sem empresa, acesso total ao sistema |
| **Roles** | `administrador`, `gestor`, `consultor` - dentro de uma empresa/operação |

### Criar Super Admin personalizado:

```bash
docker exec pagdesk-app php artisan tinker --execute="
\App\Models\User::create([
    'name' => 'Super Admin',
    'email' => 'sadmin@pagdesk.com',
    'password' => bcrypt('SuaSenhaForte'),
    'email_verified_at' => now(),
    'is_super_admin' => true,
    'empresa_id' => null,
]);
echo 'Super Admin criado com sucesso!';
"
```

### Alternativa: Rodar Seeders (usuários de exemplo)

```bash
docker exec pagdesk-app php artisan db:seed --class=RoleSeeder
docker exec pagdesk-app php artisan db:seed --class=UserSeeder
```

Usuários criados pelos seeders:

| Usuário | Email | Senha |
|---------|-------|-------|
| Super Admin | superadmin@sistema-cred.com | 12345678 |
| Admin | admin@sistema-cred.com | 12345678 |
| Gestor | gestor@sistema-cred.com | 12345678 |
| Consultor | consultor@sistema-cred.com | 12345678 |

---

## 19. Backup Automático (Linode Object Storage)

### Configuração do Object Storage

| Item | Valor |
|------|-------|
| **Bucket** | `pagdesk-backups` |
| **Região** | us-mia-1 (Miami) |
| **Endpoint** | us-mia-1.linodeobjects.com |

### Instalação no servidor DB Primary

```bash
ssh deploy@172.235.157.93
sudo su -

# Instalar s3cmd
apt install -y s3cmd

# Configurar credenciais
cat > /root/.s3cfg << 'EOF'
[default]
access_key = SUA_ACCESS_KEY
secret_key = SUA_SECRET_KEY
host_base = us-mia-1.linodeobjects.com
host_bucket = %(bucket)s.us-mia-1.linodeobjects.com
use_https = True
EOF
```

### Script de backup

Localização: `/root/backup-mysql.sh`

```bash
#!/bin/bash
DB_NAME="pagdesk"
BACKUP_DIR="/root/backups"
S3_BUCKET="s3://pagdesk-backups"
RETENTION_DAYS=30
DATE=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_FILE="pagdesk_${DATE}.sql.gz"

mkdir -p $BACKUP_DIR
echo "[$(date)] Iniciando backup..."
mysqldump -u root $DB_NAME | gzip > $BACKUP_DIR/$BACKUP_FILE

if [ -f "$BACKUP_DIR/$BACKUP_FILE" ]; then
    s3cmd put $BACKUP_DIR/$BACKUP_FILE $S3_BUCKET/
    rm -f $BACKUP_DIR/$BACKUP_FILE
fi
echo "[$(date)] Backup concluído!"
```

### Cron (diário às 3h UTC)

```bash
crontab -e
# Adicionar:
0 3 * * * /root/backup-mysql.sh >> /var/log/backup-mysql.log 2>&1
```

### Comandos úteis

```bash
# Listar backups
s3cmd ls s3://pagdesk-backups/

# Download de backup
s3cmd get s3://pagdesk-backups/pagdesk_2026-03-12_17-07-23.sql.gz

# Restore
gunzip -c pagdesk_2026-03-12_17-07-23.sql.gz | mysql -u root pagdesk
```

---

---

## Comandos Úteis

### Docker (no servidor de aplicação):

```bash
# Ver containers rodando
docker ps

# Ver logs da aplicação
docker compose logs -f app

# Ver logs de todos os serviços
docker compose logs -f

# Reiniciar containers
docker compose restart

# Parar todos os containers
docker compose down

# Subir containers
docker compose up -d

# Executar comando no container
docker exec -it pagdesk-app php artisan migrate:status
```

### Verificar status dos serviços:

```bash
# MySQL
systemctl status mysql

# Docker
systemctl status docker

# Firewall
ufw status

# Fail2ban
systemctl status fail2ban
```

### Verificar replicação MySQL:

```bash
# No replica
mysql -u root -e "SHOW REPLICA STATUS\G"
```

### Logs importantes:

```bash
# MySQL
tail -f /var/log/mysql/error.log

# Sistema
tail -f /var/log/syslog

# Auth/SSH
tail -f /var/log/auth.log
```

---

## Troubleshooting

### Replicação não conecta

1. Verificar conectividade:
```bash
mysql -h 10.0.0.3 -u replicator -p'Pg2020dsk*pd' -e "SELECT 1"
```

2. Verificar firewall no primary:
```bash
ufw status
```

3. Verificar logs:
```bash
tail -20 /var/log/mysql/error.log
```

### Erro de autenticação na replicação

Se aparecer erro `caching_sha2_password`, alterar para `mysql_native_password`:

```bash
# No primary
mysql -u root -e "ALTER USER 'replicator'@'10.0.0.4' IDENTIFIED WITH mysql_native_password BY 'Pg2020dsk*pd'; FLUSH PRIVILEGES;"

# No replica
mysql -u root -e "STOP REPLICA; START REPLICA;"
```

---

## 21. CI/CD com GitHub Actions

### Fluxo de Branches

```
[dev] ──(trabalho)──▶ [main] ──(push)──▶ [GitHub Actions] ──▶ [Produção]
```

| Branch | Propósito |
|--------|-----------|
| `dev` | Desenvolvimento (não dispara deploy) |
| `main` | Produção (dispara deploy automático) |

### Workflows Configurados

| Arquivo | Nome | Trigger | Descrição |
|---------|------|---------|-----------|
| `deploy.yml` | Deploy Production | Push para `main` | Deploy automático para produção |
| `ci.yml` | CI | PR para `main` | Testes e validação (apenas em PRs) |
| `deploy-staging.yml` | Deploy Staging | Manual | Desabilitado (sem servidor staging) |

> **Nota:** Push para `dev` não dispara nenhuma action. Isso permite trabalhar livremente na branch de desenvolvimento.

### Secrets Configurados no GitHub

Localização: `Settings > Secrets and variables > Actions`

| Secret | Valor |
|--------|-------|
| `SSH_HOST` | `172.235.157.83` |
| `SSH_PORT` | `22` |
| `SSH_USER` | `deploy` |
| `SSH_PRIVATE_KEY` | Chave privada SSH (~/.ssh/id_ed25519) |

### Pré-requisitos no Servidor (IMPORTANTE)

Antes do CI/CD funcionar, execute no servidor `pagdesk-app`:

```bash
ssh deploy@172.235.157.83

# 1. Adicionar safe.directory para o Git
git config --global --add safe.directory /var/www/pagdesk

# 2. Garantir permissões corretas
sudo chown -R deploy:deploy /var/www/pagdesk
```

> **Sem esses comandos, o deploy automático falhará com erros de permissão.**

### O que o Deploy Faz

1. `git pull origin main` - Atualiza código
2. `docker compose build` - Reconstrói imagens
3. `docker compose up -d` - Atualiza containers
4. `php artisan migrate --force` - Executa migrations
5. Limpa caches (config, route, view, cache)
6. `docker restart pagdesk-app` - Reinicia container para aplicar limpeza
7. `php artisan queue:restart` - Reinicia workers
8. Health check automático - Verifica se está saudável

> **Importante:** O restart do container após limpar caches evita problemas de views compiladas corrompidas.

### Fluxo de Trabalho Diário

```bash
# 1. Trabalhar na dev
git checkout dev
# ... fazer alterações ...
git add .
git commit -m "feat: nova funcionalidade"
git push origin dev

# 2. Testar localmente (docker compose up)

# 3. Quando pronto, merge para main
git checkout main
git merge dev
git push origin main
# → Deploy automático acontece!

# 4. Acompanhar deploy
# https://github.com/italomatosdev/pagdesk/actions
```

### Troubleshooting CI/CD

**Erro: `dubious ownership in repository`**
```bash
git config --global --add safe.directory /var/www/pagdesk
```

**Erro: `Permission denied`**
```bash
sudo chown -R deploy:deploy /var/www/pagdesk
```

**Erro: Health check falhou**
```bash
# Verificar logs no servidor
ssh deploy@172.235.157.83
docker compose logs --tail=50 app
```

**Erro: Uploads/imagens não aparecem (404)**

Se arquivos de storage retornam 404, verifique:

```bash
# 1. Verificar se os arquivos existem no container app
docker exec pagdesk-app ls -la /var/www/html/storage/app/public/

# 2. Verificar se nginx consegue acessar (deve mostrar os mesmos arquivos)
docker exec pagdesk-nginx ls -la /var/www/html/storage/app/public/

# 3. Testar acesso interno
docker exec pagdesk-nginx curl -I http://localhost/storage/PASTA/ARQUIVO.jpg
```

**Causa comum:** A configuração do Nginx precisa ter `^~` no location `/storage`:

```nginx
# CORRETO - ^~ dá prioridade sobre regex de assets
location ^~ /storage {
    alias /var/www/html/storage/app/public;
}

# INCORRETO - regex de assets (.jpg, .png) captura antes
location /storage {
    alias /var/www/html/storage/app/public;
}
```

Se fizer alteração no `default.conf`, reinicie o nginx:
```bash
docker compose restart nginx
```

**Como Funciona o Storage de Arquivos Públicos:**

O Nginx serve arquivos de `/storage/*` diretamente usando a diretiva `alias`:

```nginx
# Em docker/nginx/default.conf
location ^~ /storage {
    alias /var/www/html/storage/app/public;
    try_files $uri =404;
}
```

| URL | Caminho Real no Container |
|-----|---------------------------|
| `/storage/clientes/foto.jpg` | `/var/www/html/storage/app/public/clientes/foto.jpg` |
| `/storage/comprovantes/doc.pdf` | `/var/www/html/storage/app/public/comprovantes/doc.pdf` |

> **Importante:** O `^~` é necessário para dar prioridade ao location `/storage` sobre a regex de assets estáticos (`.jpg`, `.png`, etc.). Sem ele, a regex captura a requisição antes e causa 404.

---

## 22. Alertas no Grafana

### 22.1 Configuração do Email (SendGrid)

O Grafana foi configurado para enviar alertas por email usando SendGrid.

**Configuração no `docker-compose.yml`:**
```yaml
grafana:
  environment:
    - GF_SMTP_ENABLED=true
    - GF_SMTP_HOST=smtp.sendgrid.net:2525
    - GF_SMTP_USER=apikey
    - GF_SMTP_PASSWORD=${SENDGRID_API_KEY}
    - GF_SMTP_FROM_ADDRESS=${GRAFANA_SMTP_FROM:-noreply@pagdesk.com}
    - GF_SMTP_FROM_NAME=PagDesk Alertas
```

**Variáveis no `.env` de produção:**
```bash
SENDGRID_API_KEY=SG.xxx...
GRAFANA_SMTP_FROM=noreply@pagdesk.com
```

> **Nota:** Usamos porta 2525 pois a porta 587 é bloqueada pelo provider.

### 22.2 Contact Point

Foi criado um Contact Point de email para receber alertas:
- **Nome:** Email
- **Tipo:** Email
- **Destinatário:** italomatos@live.com

### 22.3 Alertas Configurados

| Alerta | Query | Threshold | Pending |
|--------|-------|-----------|---------|
| CPU Alta | `100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)` | > 80% | 5m |
| Memória Alta | `100 - ((node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes) * 100)` | > 85% | 5m |
| Disco Cheio | `100 - ((node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100)` | > 85% | 5m |
| Container Reiniciando | `increase(container_last_seen{name=~"pagdesk.*"}[1h]) < 1` | < 1 | 5m |

### 22.4 Estrutura dos Alertas

- **Folder:** PagDesk
- **Evaluation Group:** Infraestrutura (intervalo 5m)

### 22.5 Acesso ao Grafana

```
URL: http://172.235.157.83:3000 (via SSH tunnel)
     ssh -L 3000:localhost:3000 deploy@172.235.157.83

Usuário: admin
Senha: (definida no .env - GRAFANA_ADMIN_PASSWORD)
```

---

## 23. Próximos Passos

- [x] ~~Instalar Docker no `pagdesk-app`~~
- [x] ~~Configurar repositório GitHub~~
- [x] ~~Clone do código no servidor~~
- [x] ~~Criar arquivo `.env` de produção~~
- [x] ~~Build e deploy dos containers~~
- [x] ~~Executar migrations~~
- [x] ~~Configurar domínio e DNS (Cloudflare)~~
- [x] ~~Configurar HTTPS (Cloudflare Flexível)~~
- [x] ~~Criar Super Admin~~
- [x] ~~Configurar backups automáticos~~
- [x] ~~Configurar CI/CD com GitHub Actions~~
- [x] ~~Configurar alertas no Grafana~~

**Infraestrutura de produção completa!**

---

## Histórico de Instalação

| Data | Ação |
|------|------|
| 2026-03-12 | Criação da VPC `pagdesk-vpc` |
| 2026-03-12 | Criação dos 3 Linodes (us-mia) |
| 2026-03-12 | Configuração inicial (usuário deploy, SSH, UFW, fail2ban) |
| 2026-03-12 | Instalação MySQL Primary com replicação |
| 2026-03-12 | Instalação MySQL Replica e configuração da replicação |
| 2026-03-12 | Teste de replicação bem-sucedido |
| 2026-03-12 | Instalação Docker no servidor de aplicação |
| 2026-03-12 | Criação do repositório GitHub (privado) |
| 2026-03-12 | Clone do repositório no servidor |
| 2026-03-12 | Criação do `.env` de produção |
| 2026-03-12 | Build e deploy dos containers Docker |
| 2026-03-12 | Execução das migrations (com correções de ordem) |
| 2026-03-12 | Configuração do domínio pagdesk.com no Cloudflare |
| 2026-03-12 | Ativação do HTTPS via Cloudflare (modo Flexível) |
| 2026-03-12 | **Aplicação online em https://pagdesk.com** |
| 2026-03-12 | Criação do Super Admin |
| 2026-03-12 | Configuração de backup automático (Linode Object Storage) |
| 2026-03-12 | Configuração CI/CD com GitHub Actions |
| 2026-03-12 | Configuração SMTP SendGrid no Grafana (porta 2525) |
| 2026-03-12 | Configuração alertas: CPU, Memória, Disco, Container |
| 2026-03-12 | Correção Nginx: `^~` no location /storage para servir uploads |
| 2026-03-12 | **Infraestrutura de produção completa!** |

---

**Documento atualizado em:** 2026-03-12
# Teste CI - Thu Mar 12 16:06:32 -03 2026
