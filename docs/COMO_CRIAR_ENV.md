# Como Criar o Arquivo .env

## 📍 Localização

O arquivo `.env` deve ficar na **raiz do projeto**:
```
/Users/italomatos/Documents/Projetos/sistema-cred/
├── .env              ← AQUI (na raiz)
├── .env.example      ← Template
├── composer.json
└── ...
```

## 🔧 Opção 1: Usar o .env.example do Template

O template Webadmin já tem um `.env.example` em:
```
template-webadmin/Admin/.env.example
```

### Passos:

1. **Copiar do template para a raiz**:
   ```bash
   cd /Users/italomatos/Documents/Projetos/sistema-cred
   cp template-webadmin/Admin/.env.example .env
   ```

2. **Gerar a chave da aplicação**:
   ```bash
   php artisan key:generate
   ```

3. **Ajustar configurações** no arquivo `.env`:
   - Banco de dados
   - Nome da aplicação
   - Locale (português-BR)

## 🔧 Opção 2: Criar Manualmente

Se preferir criar manualmente, use este conteúdo base:

```env
APP_NAME="Sistema de Crédito"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=America/Sao_Paulo
APP_URL=http://localhost

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_cred
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

LOG_CHANNEL=stack
LOG_LEVEL=debug

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@sistema-cred.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
```

Depois execute:
```bash
php artisan key:generate
```

## ⚙️ Configurações Importantes

### Banco de Dados

**Para MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_cred
DB_USERNAME=root
DB_PASSWORD=sua_senha
```

**Para PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_cred
DB_USERNAME=postgres
DB_PASSWORD=sua_senha
```

### Aplicação (Português-BR)
```env
APP_NAME="Sistema de Crédito"
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo
```

## ✅ Verificar se está correto

Após criar o `.env`, teste:

```bash
# Verificar se a chave foi gerada
php artisan key:generate

# Testar conexão com banco
php artisan migrate:status
```

## 📝 Notas

- O arquivo `.env` **NÃO** deve ser commitado no Git (está no .gitignore)
- O arquivo `.env.example` **DEVE** ser commitado (é o template)
- Sempre use `.env.example` como base
- Nunca compartilhe o arquivo `.env` (contém senhas)
