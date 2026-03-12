# Guia de Instalação e Configuração

## 📋 Pré-requisitos

- PHP 8.2 ou superior
- Composer
- Node.js e NPM
- PostgreSQL ou MySQL
- Extensões PHP: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

## 🚀 Instalação Passo a Passo

### 1. Instalar Dependências

```bash
# Instalar dependências PHP
composer install

# Instalar dependências NPM
npm install
```

### 2. Configurar Ambiente

```bash
# Copiar arquivo de ambiente
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate
```

### 3. Configurar Banco de Dados

Edite o arquivo `.env`:

```env
DB_CONNECTION=pgsql  # ou mysql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_cred
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### 4. Executar Migrations

```bash
# Executar migrations
php artisan migrate
```

### 5. Executar Seeders

```bash
# Executar seeders (cria papéis, permissões, usuários e operações)
php artisan db:seed
```

**Usuários criados:**
- **Super Admin**: `superadmin@sistema-cred.com` / `12345678`
- **Admin**: `admin@sistema-cred.com` / `12345678`
- **Gestor**: `gestor@sistema-cred.com` / `12345678`
- **Consultor**: `consultor@sistema-cred.com` / `12345678`

### 6. Compilar Assets

```bash
# Desenvolvimento (watch mode)
npm run dev

# Produção
npm run build
```

### 7. Configurar Storage

```bash
# Criar link simbólico para storage
php artisan storage:link
```

### 8. Iniciar Servidor

```bash
# Servidor de desenvolvimento
php artisan serve
```

Acesse: `http://localhost:8000`

## ⚙️ Configurações Adicionais

### Cache e Filas (Recomendado para Produção)

Para melhor desempenho do dashboard e processamento de tarefas pesadas em background, configure Redis:

```bash
# No arquivo .env, altere:
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# E configure a conexão Redis (se ainda não estiver configurada):
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Nota:** 
- O sistema funciona com `CACHE_DRIVER=file` e `QUEUE_CONNECTION=sync` (padrão), mas Redis oferece melhor performance em alta carga.
- O cache do dashboard tem TTL de 60 segundos, mantendo os dados quase em tempo real.
- Para usar filas (jobs), instale o Laravel Horizon: `composer require laravel/horizon && php artisan horizon:install`
- **Veja `docs/CONFIGURAR_REDIS.md` para instruções detalhadas de instalação e configuração do Redis.**
- Veja `docs/FILAS_E_HORIZON.md` para mais detalhes sobre filas e Horizon.

### Scheduler (Tarefas Agendadas)

Para que o sistema marque parcelas atrasadas automaticamente, configure o cron:

```bash
# Adicionar ao crontab
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

### Permissões de Arquivos

```bash
# Dar permissões necessárias
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## 🧪 Testar Instalação

1. Acesse `http://localhost:8000`
2. Faça login com `admin@sistema-cred.com` / `12345678`
3. Verifique se os menus aparecem corretamente
4. Teste criar um cliente
5. Teste criar um empréstimo

## 🔧 Comandos Úteis

```bash
# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Recriar banco (CUIDADO: apaga todos os dados)
php artisan migrate:fresh --seed

# Executar comando manual de parcelas atrasadas
php artisan parcelas:marcar-atrasadas
```

## 🏥 Health check e backup

- **Health check:** `GET /api/health` – retorna status da aplicação (banco, cache, fila). Útil para load balancers e monitoramento. Veja `docs/BACKUP_E_SAUDE.md`.
- **Backup:** Estratégia de backup do banco e arquivos está documentada em `docs/BACKUP_E_SAUDE.md`.

## 📝 Notas

- O sistema usa português-BR em toda interface
- CPF deve ser informado apenas com números (11 dígitos)
- O sistema valida automaticamente dívidas ativas e limites de crédito
- Empréstimos podem ser aprovados automaticamente ou ficarem pendentes
