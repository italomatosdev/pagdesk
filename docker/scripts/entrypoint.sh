#!/bin/sh
# =============================================================================
# ENTRYPOINT - Script de Inicialização do Container
# PagDesk - Laravel Application
# =============================================================================

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "${GREEN}========================================${NC}"
echo "${GREEN}  PagDesk - Inicializando...${NC}"
echo "${GREEN}========================================${NC}"

# -----------------------------------------------------------------------------
# 1. Verificar se .env existe
# -----------------------------------------------------------------------------
if [ ! -f /var/www/html/.env ]; then
    echo "${YELLOW}[AVISO] Arquivo .env não encontrado${NC}"
    if [ -f /var/www/html/.env.example ]; then
        echo "${YELLOW}[INFO] Copiando .env.example para .env${NC}"
        cp /var/www/html/.env.example /var/www/html/.env
    fi
fi

# -----------------------------------------------------------------------------
# 2. Gerar APP_KEY se não existir
# -----------------------------------------------------------------------------
if [ -z "$APP_KEY" ] && [ -f /var/www/html/.env ]; then
    if grep -q "^APP_KEY=$" /var/www/html/.env 2>/dev/null; then
        echo "${YELLOW}[INFO] Gerando APP_KEY...${NC}"
        php /var/www/html/artisan key:generate --force
    fi
fi

# -----------------------------------------------------------------------------
# 3. Criar diretórios necessários
# -----------------------------------------------------------------------------
echo "${GREEN}[INFO] Verificando diretórios...${NC}"
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# -----------------------------------------------------------------------------
# 4. Aguardar banco de dados (se configurado)
# -----------------------------------------------------------------------------
if [ -n "$DB_HOST" ]; then
    echo "${GREEN}[INFO] Aguardando banco de dados em ${DB_HOST}:${DB_PORT:-3306}...${NC}"
    
    max_attempts=30
    attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if php -r "
            \$host = getenv('DB_HOST');
            \$port = getenv('DB_PORT') ?: 3306;
            \$conn = @fsockopen(\$host, \$port, \$errno, \$errstr, 5);
            if (\$conn) { fclose(\$conn); exit(0); }
            exit(1);
        " 2>/dev/null; then
            echo "${GREEN}[OK] Banco de dados disponível!${NC}"
            break
        fi
        
        echo "${YELLOW}[AGUARDANDO] Tentativa $attempt/$max_attempts...${NC}"
        sleep 2
        attempt=$((attempt + 1))
    done
    
    if [ $attempt -gt $max_attempts ]; then
        echo "${RED}[ERRO] Banco de dados não disponível após $max_attempts tentativas${NC}"
        echo "${YELLOW}[AVISO] Continuando mesmo assim...${NC}"
    fi
fi

# -----------------------------------------------------------------------------
# 5. Aguardar Redis (se configurado)
# -----------------------------------------------------------------------------
if [ -n "$REDIS_HOST" ]; then
    echo "${GREEN}[INFO] Aguardando Redis em ${REDIS_HOST}:${REDIS_PORT:-6379}...${NC}"
    
    max_attempts=15
    attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if php -r "
            \$host = getenv('REDIS_HOST');
            \$port = getenv('REDIS_PORT') ?: 6379;
            \$conn = @fsockopen(\$host, \$port, \$errno, \$errstr, 5);
            if (\$conn) { fclose(\$conn); exit(0); }
            exit(1);
        " 2>/dev/null; then
            echo "${GREEN}[OK] Redis disponível!${NC}"
            break
        fi
        
        echo "${YELLOW}[AGUARDANDO] Tentativa $attempt/$max_attempts...${NC}"
        sleep 1
        attempt=$((attempt + 1))
    done
fi

# -----------------------------------------------------------------------------
# 6. Limpar caches antigos (seguro)
# -----------------------------------------------------------------------------
echo "${GREEN}[INFO] Limpando caches...${NC}"
php /var/www/html/artisan config:clear 2>/dev/null || true
php /var/www/html/artisan route:clear 2>/dev/null || true
php /var/www/html/artisan view:clear 2>/dev/null || true

# -----------------------------------------------------------------------------
# 7. Otimizar para produção (se APP_ENV=production)
# -----------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ]; then
    echo "${GREEN}[INFO] Otimizando para produção...${NC}"
    php /var/www/html/artisan config:cache
    php /var/www/html/artisan route:cache
    php /var/www/html/artisan view:cache
    php /var/www/html/artisan event:cache 2>/dev/null || true
fi

# -----------------------------------------------------------------------------
# 8. Criar link simbólico do storage
# -----------------------------------------------------------------------------
if [ ! -L /var/www/html/public/storage ]; then
    echo "${GREEN}[INFO] Criando link do storage...${NC}"
    php /var/www/html/artisan storage:link 2>/dev/null || true
fi

# -----------------------------------------------------------------------------
# 9. Informações finais
# -----------------------------------------------------------------------------
echo "${GREEN}========================================${NC}"
echo "${GREEN}  Inicialização concluída!${NC}"
echo "${GREEN}  Ambiente: ${APP_ENV:-local}${NC}"
echo "${GREEN}========================================${NC}"

# -----------------------------------------------------------------------------
# 10. Executar comando passado (php-fpm por padrão)
# -----------------------------------------------------------------------------
exec "$@"
