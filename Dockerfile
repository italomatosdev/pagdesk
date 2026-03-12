# =============================================================================
# DOCKERFILE - PagDesk (Laravel)
# Multi-stage build otimizado para produção
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer - Instalar dependências PHP
# -----------------------------------------------------------------------------
FROM composer:2.6 AS composer

WORKDIR /app

# Copiar arquivos de dependências primeiro (cache layer)
COPY composer.json composer.lock ./

# Instalar dependências sem scripts e sem dev
# --ignore-platform-reqs porque pcntl será instalado no stage final
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs

# Copiar código fonte
COPY . .

# Gerar autoloader otimizado
RUN composer dump-autoload --optimize --no-dev

# -----------------------------------------------------------------------------
# Stage 2: Node - Compilar assets frontend
# -----------------------------------------------------------------------------
FROM node:20-alpine AS node

WORKDIR /app

# Copiar arquivos de dependências primeiro (cache layer)
COPY package.json package-lock.json* ./

# Instalar dependências
RUN npm ci --silent

# Copiar código fonte necessário para build
COPY resources ./resources
COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./

# Build dos assets
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 3: Production - Imagem final otimizada
# -----------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS production

# Argumentos de build
ARG APP_ENV=production
ARG APP_DEBUG=false

# Variáveis de ambiente
ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=${APP_DEBUG} \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# Instalar dependências do sistema
RUN apk add --no-cache \
    # Bibliotecas necessárias
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    # Ferramentas
    nginx \
    supervisor \
    curl \
    # Timezone
    tzdata

# Configurar timezone
ENV TZ=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Instalar extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Instalar Redis via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Copiar configurações PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Criar diretório da aplicação
WORKDIR /var/www/html

# Copiar código do stage composer (com vendor)
COPY --from=composer /app .

# Copiar assets compilados do stage node
COPY --from=node /app/public/build ./public/build

# Criar diretórios necessários e ajustar permissões
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Copiar scripts de inicialização
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/scripts/scheduler.sh /usr/local/bin/scheduler.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/scheduler.sh

# Expor porta do PHP-FPM
EXPOSE 9000

# Usuário não-root
USER www-data

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Comando padrão
CMD ["php-fpm"]
